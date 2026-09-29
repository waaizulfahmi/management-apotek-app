<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import RupiahInput from '@/Components/RupiahInput.vue';
import PriceUnitModal from './Partials/PriceUnitModal.vue';
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import { showConfirm } from '@/Utils/swal';

const props = defineProps({
    obats: Object,
    suppliers: Array,
    units: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const showPriceModal = ref(false);
const selectedObat = ref(null);

const handleSearch = () => {
    router.get(route('admin.obat.index'), { search: search.value }, { preserveState: true, replace: true });
};

const generateKodeObat = () => {
    return 'OBT-' + Math.floor(1000 + Math.random() * 9000);
};

// Price & Unit Modal Handlers
const openPriceModal = (obat) => {
    selectedObat.value = obat;
    showPriceModal.value = true;
};

const closePriceModal = () => {
    showPriceModal.value = false;
    selectedObat.value = null;
};

const handlePriceUpdated = () => {
    router.reload({ preserveScroll: true });
};

// Import Modal State & Handlers
const showImportModal = ref(false);
const importPreviewRows = ref([]);
const isImporting = ref(false);
const importDone = ref(false);
const importSummary = ref(null);

const importForm = useForm({
    file: null,
    mode: 'update',
});

const openImportModal = () => {
    importForm.reset();
    importForm.clearErrors();
    importPreviewRows.value = [];
    isImporting.value = false;
    importDone.value = false;
    importSummary.value = null;
    showImportModal.value = true;
};

const closeImportModal = () => {
    showImportModal.value = false;
    importForm.reset();
    importPreviewRows.value = [];
    isImporting.value = false;
    importDone.value = false;
    importSummary.value = null;
};

const handleFileChange = (e) => {
    const file = e.target.files[0];
    importForm.file = file;
    if (!file) {
        importPreviewRows.value = [];
        return;
    }

    const reader = new FileReader();
    reader.onload = (evt) => {
        const text = evt.target.result;
        const lines = text.split(/\r\n|\n/);
        if (lines.length <= 1) return;

        // Auto-detect delimiter
        const sampleLine = lines[0];
        const delim = sampleLine.includes(';') ? ';' : ',';

        const headers = sampleLine.split(delim).map(h => h.trim().toLowerCase().replace(/["']/g, ''));
        const rows = [];

        for (let i = 1; i < lines.length; i++) {
            if (!lines[i].trim()) continue;
            const cols = lines[i].split(delim).map(c => c.trim().replace(/["']/g, ''));
            const rowObj = {};
            headers.forEach((h, idx) => {
                rowObj[h] = cols[idx] || '';
            });
            if (rowObj.nama || rowObj.nama_obat || rowObj.name) {
                rows.push({
                    kode: rowObj.kode || rowObj.kode_obat || rowObj.code || '',
                    nama: rowObj.nama || rowObj.nama_obat || rowObj.name || '',
                    jenis_obat: rowObj.jenis_obat || rowObj.jenis || rowObj.satuan || 'Tablet',
                    kategori: rowObj.kategori || rowObj.category || 'Antibiotik',
                    harga: rowObj.harga || rowObj.harga_jual || rowObj.price || '0',
                });
            }
        }
        importPreviewRows.value = rows;
    };
    reader.readAsText(file);
};

const submitImport = () => {
    if (!importForm.file) return;
    isImporting.value = true;
    importDone.value = false;

    importForm.post(route('admin.obat.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (page) => {
            isImporting.value = false;
            importDone.value = true;
            importSummary.value = page.props.flash?.import_summary || {
                total: importPreviewRows.value.length,
                inserted: importPreviewRows.value.length,
                updated: 0,
                skipped: 0
            };
        },
        onError: () => {
            isImporting.value = false;
            importDone.value = false;
        }
    });
};

// Form Tambah
const createForm = useForm({
    kode: generateKodeObat(),
    nama: '',
    gambar: null,
    jenis_obat: 'Tablet',
    kategori: 'Antibiotik',
    harga: 0,
    stok: 0,
    min_stok: 10,
    supplier_id: '',
    supplier_name: '',
    merk: '',
    satuan_dasar_id: '',
    satuan_pembelian_id: '',
    satuan_penjualan_id: '',
});

const submitCreate = () => {
    createForm.post(route('admin.obat.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.kode = generateKodeObat();
            const modalEl = document.getElementById('tambahObatModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        },
    });
};

// Form Edit
const editObatData = ref(null);
const editForm = useForm({
    _method: 'PUT',
    nama: '',
    gambar: null,
    jenis_obat: 'Tablet',
    kategori: 'Antibiotik',
    harga: 0,
    stok: 0,
    min_stok: 10,
    supplier_id: '',
    supplier_name: '',
    merk: '',
    satuan_dasar_id: '',
    satuan_pembelian_id: '',
    satuan_penjualan_id: '',
});

const openEditModal = (obat) => {
    editObatData.value = obat;
    editForm.nama = obat.nama;
    editForm.jenis_obat = obat.jenis_obat;
    editForm.kategori = obat.kategori;
    editForm.harga = obat.harga;
    editForm.stok = obat.stok;
    editForm.min_stok = obat.min_stok || 10;
    editForm.supplier_id = obat.supplier_id || '';
    editForm.supplier_name = obat.supplier_name || '';
    editForm.merk = obat.merk || '';
    editForm.satuan_dasar_id = obat.satuan_dasar_id || '';
    editForm.satuan_pembelian_id = obat.satuan_pembelian_id || '';
    editForm.satuan_penjualan_id = obat.satuan_penjualan_id || '';
    editForm.gambar = null;

    const modalEl = document.getElementById('editObatModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
};

const submitEdit = () => {
    if (!editObatData.value) return;
    editForm.post(route('admin.obat.update', editObatData.value.kode), {
        onSuccess: () => {
            const modalEl = document.getElementById('editObatModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        },
    });
};

const deleteObat = (kode) => {
    showConfirm("Hapus Obat", "Apakah Anda yakin ingin menghapus (soft delete) data obat ini?", () => {
        router.delete(route('admin.obat.destroy', kode));
    });
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold text-dark mb-0">Master Obat / Produk</h2>
                        <p class="text-muted small mb-0">Kelola informasi obat, stok dasar, konversi & harga bertingkat</p>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="input-group" style="max-width: 260px;">
                            <span class="input-group-text bg-primary text-white"><i class="bx bx-search"></i></span>
                            <input type="text" class="form-control" v-model="search" @keyup.enter="handleSearch" placeholder="Cari Kode/Nama Obat...">
                        </div>

                        <button class="btn btn-outline-success shadow-sm rounded-3 px-3 py-2 d-flex align-items-center gap-2" @click="openImportModal">
                            <i class="bx bx-upload fs-5"></i>
                            <span>Import CSV / Excel</span>
                        </button>

                        <button class="btn btn-primary shadow-sm rounded-3 px-3 py-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#tambahObatModal">
                            <i class="bx bx-plus fs-5"></i>
                            <span>Tambah Obat</span>
                        </button>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 bg-white p-3 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small">
                            <thead class="bg-light text-uppercase text-muted">
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Obat</th>
                                    <th>Satuan Dasar</th>
                                    <th>Harga Beli</th>
                                    <th>Harga Jual</th>
                                    <th>Margin</th>
                                    <th>Stok (Base)</th>
                                    <th class="text-center" style="width: 220px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="obat in obats.data" :key="obat.kode">
                                    <td class="fw-bold text-primary">{{ obat.kode }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img :src="`/Assets/Obat/${obat.gambar}`" @error="(e) => e.target.src = '/Assets/img/default-medicine.png'" alt="gambar obat" class="rounded-2" style="width: 48px; height: 48px; object-fit: cover;">
                                            <div>
                                                <span class="fw-bold text-dark d-block fs-6">{{ obat.nama }}</span>
                                                <span class="text-muted small">
                                                    {{ obat.kategori }} | {{ obat.supplier_name || obat.supplier?.name || 'Tanpa Supplier' }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-6">
                                            {{ obat.satuan_dasar?.name || obat.jenis_obat }}
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark">
                                        {{ obat.purchase_price_formatted }}
                                    </td>
                                    <td class="fw-bold text-success">
                                        {{ obat.selling_price_formatted }}
                                    </td>
                                    <td class="fw-semibold text-info">
                                        {{ obat.margin_formatted }}
                                    </td>
                                    <td>
                                        <span class="fw-bold fs-6" :class="obat.stok <= (obat.min_stok || 10) ? 'text-danger' : 'text-success'">
                                            {{ obat.stok }} {{ obat.satuan_dasar?.name || 'Tablet' }}
                                        </span>
                                        <div class="text-muted" style="font-size: 0.75rem;">Min: {{ obat.min_stok || 10 }}</div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-end gap-1">
                                            <button class="btn btn-outline-primary btn-sm rounded-3" @click="openPriceModal(obat)" title="Kelola Harga & Konversi Satuan">
                                                <i class="bx bx-purchase-tag-alt me-1"></i> Harga & Satuan
                                            </button>
                                            <button class="btn btn-light btn-sm rounded-circle p-2" @click="openEditModal(obat)" title="Edit Obat">
                                                <i class="bx bx-edit text-warning" style="font-size: 1.1rem;"></i>
                                            </button>
                                            <button class="btn btn-light btn-sm rounded-circle p-2" @click="deleteObat(obat.kode)" title="Soft Delete Obat">
                                                <i class="bx bx-trash text-danger" style="font-size: 1.1rem;"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!obats.data || obats.data.length === 0">
                                    <td colspan="8" class="text-center py-5 text-muted">Belum ada data obat.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links -->
                    <div class="d-flex justify-content-between align-items-center mt-3 px-2" v-if="obats.links && obats.links.length > 3">
                        <span class="text-muted small">Menampilkan {{ obats.from || 0 }} - {{ obats.to || 0 }} dari {{ obats.total || 0 }} data</span>
                        <div class="pagination-buttons d-flex gap-1">
                            <button
                                v-for="(link, index) in obats.links"
                                :key="index"
                                class="btn btn-sm rounded-3"
                                :class="link.active ? 'btn-primary' : 'btn-light'"
                                :disabled="!link.url"
                                @click="router.get(link.url)"
                                v-html="link.label"
                            ></button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Price & Unit Management Modal -->
        <PriceUnitModal
            :show="showPriceModal"
            :product="selectedObat"
            :allUnits="units"
            @close="closePriceModal"
            @updated="handlePriceUpdated"
        />

        <!-- Modal Tambah Obat -->
        <div class="modal fade" id="tambahObatModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header bg-primary text-white border-0 py-3">
                        <h5 class="modal-title fw-bold">Tambah Obat Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitCreate">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Kode Obat (Otomatis)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control fw-bold font-monospace text-primary rounded-start-3" v-model="createForm.kode" required>
                                    <button class="btn btn-outline-secondary rounded-end-3" type="button" @click="createForm.kode = generateKodeObat()">
                                        <i class="bx bx-refresh me-1"></i> Generate Ulang
                                    </button>
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-semibold">Nama Obat <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control rounded-3" v-model="createForm.nama" required placeholder="Contoh: Paracetamol 500mg">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Merk / Pabrik</label>
                                    <input type="text" class="form-control rounded-3" v-model="createForm.merk" placeholder="Kalbe, Sanbe...">
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Satuan Dasar (Stok)</label>
                                    <select class="form-select rounded-3" v-model="createForm.satuan_dasar_id">
                                        <option value="">-- Pilih Satuan Dasar --</option>
                                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Satuan Pembelian (PO)</label>
                                    <select class="form-select rounded-3" v-model="createForm.satuan_pembelian_id">
                                        <option value="">-- Pilih Satuan PO --</option>
                                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Satuan Penjualan (POS)</label>
                                    <select class="form-select rounded-3" v-model="createForm.satuan_penjualan_id">
                                        <option value="">-- Pilih Satuan POS --</option>
                                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Supplier / PBF Penyuplai</label>
                                <select class="form-select rounded-3" v-model="createForm.supplier_id">
                                    <option value="">-- Tanpa Supplier / Pilih Supplier --</option>
                                    <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">{{ sup.name }} ({{ sup.code }})</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Gambar Obat</label>
                                <input type="file" class="form-control rounded-3" @input="createForm.gambar = $event.target.files[0]" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Jenis Obat</label>
                                    <select class="form-select rounded-3" v-model="createForm.jenis_obat" required>
                                        <option value="Tablet">Tablet</option>
                                        <option value="Kapsul">Kapsul</option>
                                        <option value="Sirup">Sirup</option>
                                        <option value="Salep">Salep</option>
                                        <option value="Injeksi">Injeksi</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Kategori Obat</label>
                                    <select class="form-select rounded-3" v-model="createForm.kategori" required>
                                        <option value="Antibiotik">Antibiotik</option>
                                        <option value="Antipiretik">Antipiretik</option>
                                        <option value="Analgesik">Analgesik</option>
                                        <option value="Antihistamin">Antihistamin</option>
                                        <option value="Vitamin">Vitamin</option>
                                        <option value="Antiseptik">Antiseptik</option>
                                        <option value="Herbal">Herbal</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Harga Jual Default (Rp)</label>
                                <RupiahInput v-model="createForm.harga" placeholder="Masukkan harga..." required />
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Stok Minimum (Warning Level)</label>
                                <input type="number" class="form-control rounded-3" v-model="createForm.min_stok" placeholder="Contoh: 10" required>
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-light rounded-3 px-3" data-bs-dismiss="modal">Tutup</button>
                            <button type="submit" class="btn btn-primary rounded-3 px-4" :disabled="createForm.processing">Simpan Obat</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Obat -->
        <div class="modal fade" id="editObatModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header bg-warning text-white border-0 py-3">
                        <h5 class="modal-title fw-bold">Edit Data Obat</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitEdit">
                        <div class="modal-body p-4">
                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-semibold">Nama Obat</label>
                                    <input type="text" class="form-control rounded-3" v-model="editForm.nama" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Merk / Pabrik</label>
                                    <input type="text" class="form-control rounded-3" v-model="editForm.merk">
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Satuan Dasar (Stok)</label>
                                    <select class="form-select rounded-3" v-model="editForm.satuan_dasar_id">
                                        <option value="">-- Pilih Satuan Dasar --</option>
                                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Satuan Pembelian (PO)</label>
                                    <select class="form-select rounded-3" v-model="editForm.satuan_pembelian_id">
                                        <option value="">-- Pilih Satuan PO --</option>
                                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Satuan Penjualan (POS)</label>
                                    <select class="form-select rounded-3" v-model="editForm.satuan_penjualan_id">
                                        <option value="">-- Pilih Satuan POS --</option>
                                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Supplier / PBF Penyuplai</label>
                                <select class="form-select rounded-3" v-model="editForm.supplier_id">
                                    <option value="">-- Tanpa Supplier / Pilih Supplier --</option>
                                    <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">{{ sup.name }} ({{ sup.code }})</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Gambar Obat (Biarkan kosong jika tidak diubah)</label>
                                <input type="file" class="form-control rounded-3" @input="editForm.gambar = $event.target.files[0]">
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Jenis Obat</label>
                                    <select class="form-select rounded-3" v-model="editForm.jenis_obat" required>
                                        <option value="Tablet">Tablet</option>
                                        <option value="Kapsul">Kapsul</option>
                                        <option value="Sirup">Sirup</option>
                                        <option value="Salep">Salep</option>
                                        <option value="Injeksi">Injeksi</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Kategori Obat</label>
                                    <select class="form-select rounded-3" v-model="editForm.kategori" required>
                                        <option value="Antibiotik">Antibiotik</option>
                                        <option value="Antipiretik">Antipiretik</option>
                                        <option value="Analgesik">Analgesik</option>
                                        <option value="Antihistamin">Antihistamin</option>
                                        <option value="Vitamin">Vitamin</option>
                                        <option value="Antiseptik">Antiseptik</option>
                                        <option value="Herbal">Herbal</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Harga Jual (Rp)</label>
                                <RupiahInput v-model="editForm.harga" placeholder="Masukkan harga..." required />
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Stok Minimum (Warning Level)</label>
                                <input type="number" class="form-control rounded-3" v-model="editForm.min_stok" required>
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-light rounded-3 px-3" data-bs-dismiss="modal">Tutup</button>
                            <button type="submit" class="btn btn-warning text-white rounded-3 px-4" :disabled="editForm.processing">Update Obat</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Import Master Obat -->
        <div v-if="showImportModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header border-0 bg-success bg-opacity-10 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bx bx-file-find text-success fs-3"></i>
                            <div>
                                <h5 class="modal-title fw-bold text-dark mb-0">Import Data Master Obat</h5>
                                <small class="text-muted">Upload file CSV/Excel berisi daftar kode obat dan nama obat</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" @click="closeImportModal" :disabled="isImporting"></button>
                    </div>

                    <!-- 1. ANIMATION: PROCESSING IMPORT -->
                    <div v-if="isImporting" class="modal-body p-5 text-center">
                        <div class="mb-4">
                            <div class="spinner-border text-success" style="width: 3.5rem; height: 3.5rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">Sedang Memproses Import Obat...</h4>
                        <p class="text-muted small mb-4">Sistem sedang memvalidasi dan menyimpan data obat ke database. Mohon tidak menutup halaman ini.</p>
                        <div class="progress rounded-pill overflow-hidden shadow-xs" style="height: 12px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success w-100" role="progressbar"></div>
                        </div>
                    </div>

                    <!-- 2. ANIMATION: IMPORT SUCCESS / COMPLETED -->
                    <div v-else-if="importDone" class="modal-body p-5 text-center">
                        <div class="mb-3">
                            <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle p-3 shadow-xs" style="width: 80px; height: 80px;">
                                <i class="bx bx-check-circle fs-1"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Import Data Obat Selesai!</h3>
                        <p class="text-muted small mb-4">Data master obat berhasil diproses dan disimpan ke database.</p>

                        <!-- Summary Cards -->
                        <div class="row g-3 mb-4">
                            <div class="col-4">
                                <div class="p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 text-center">
                                    <div class="text-success small fw-semibold">Obat Baru</div>
                                    <h3 class="fw-bold text-success mb-0">+{{ importSummary?.inserted || 0 }}</h3>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 bg-info bg-opacity-10 border border-info border-opacity-25 rounded-3 text-center">
                                    <div class="text-info small fw-semibold">Diupdate</div>
                                    <h3 class="fw-bold text-info mb-0">{{ importSummary?.updated || 0 }}</h3>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3 text-center">
                                    <div class="text-warning small fw-semibold">Dilewati</div>
                                    <h3 class="fw-bold text-warning mb-0">{{ importSummary?.skipped || 0 }}</h3>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-success btn-lg px-5 fw-bold shadow-sm rounded-3" @click="closeImportModal">
                            <i class="bx bx-check me-1"></i> Selesai & Lihat Master Obat
                        </button>
                    </div>

                    <!-- 3. FORM STATE (NORMAL IMPORT INPUT) -->
                    <form v-else @submit.prevent="submitImport">
                        <div class="modal-body p-4">
                            <!-- Download Template Section -->
                            <div class="alert alert-info border-0 rounded-3 d-flex justify-content-between align-items-center p-3 mb-4">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bx bx-download fs-4 text-info"></i>
                                    <div>
                                        <div class="fw-bold small">Belum Punya Format CSV/Excel?</div>
                                        <div class="text-muted style-sm" style="font-size: 0.78rem;">Download template resmi agar struktur kolom (kode, nama, harga, dll) sesuai.</div>
                                    </div>
                                </div>
                                <a :href="route('admin.obat.import-template')" target="_blank" class="btn btn-sm btn-info text-white fw-bold px-3 rounded-2">
                                    <i class="bx bx-download me-1"></i> Download Template CSV
                                </a>
                            </div>

                            <!-- Upload File Input -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold small">Pilih File CSV / Excel (.csv)</label>
                                <input 
                                    type="file" 
                                    class="form-control form-control-lg rounded-3" 
                                    accept=".csv,.txt" 
                                    @change="handleFileChange"
                                    required
                                >
                                <small class="text-muted">Mendukung file .csv (Pemisah koma `,` atau titik koma `;` terdeteksi otomatis)</small>
                            </div>

                            <!-- Import Mode Option -->
                            <div class="mb-4 bg-light p-3 rounded-3">
                                <label class="form-label fw-semibold small d-block mb-2">Penanganan Jika Kode Obat Sudah Ada</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" value="update" v-model="importForm.mode" id="modeUpdate">
                                        <label class="form-check-label small" for="modeUpdate">
                                            <strong>Update Data</strong> (Perbarui nama/harga jika kode cocok)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" value="skip" v-model="importForm.mode" id="modeSkip">
                                        <label class="form-check-label small" for="modeSkip">
                                            <strong>Lewati</strong> (Abaikan jika kode obat sudah ada)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Table -->
                            <div v-if="importPreviewRows.length > 0" class="mb-3">
                                <h6 class="fw-bold small text-dark mb-2">
                                    <i class="bx bx-table text-primary me-1"></i>Preview Data ({{ importPreviewRows.length }} Obat Terdeteksi)
                                </h6>
                                <div class="table-responsive border rounded-3" style="max-height: 200px; overflow-y: auto;">
                                    <table class="table table-sm table-striped align-middle mb-0" style="font-size: 0.8rem;">
                                        <thead class="bg-light sticky-top">
                                            <tr>
                                                <th>#</th>
                                                <th>Kode Obat</th>
                                                <th>Nama Obat</th>
                                                <th>Jenis</th>
                                                <th>Kategori</th>
                                                <th>Harga</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(row, idx) in importPreviewRows.slice(0, 50)" :key="idx">
                                                <td>{{ idx + 1 }}</td>
                                                <td><span class="badge bg-secondary font-monospace">{{ row.kode || 'AUTO' }}</span></td>
                                                <td class="fw-bold">{{ row.nama }}</td>
                                                <td>{{ row.jenis_obat || 'Tablet' }}</td>
                                                <td>{{ row.kategori || 'Antibiotik' }}</td>
                                                <td>{{ formatCurrency(row.harga || 0) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <small v-if="importPreviewRows.length > 50" class="text-muted d-block mt-1 style-xs" style="font-size: 0.72rem;">
                                    * Menampilkan 50 data pertama dari total {{ importPreviewRows.length }} baris.
                                </small>
                            </div>
                        </div>

                        <div class="modal-footer border-0 bg-light rounded-bottom-4 py-3">
                            <button type="button" class="btn btn-light px-4 rounded-3" @click="closeImportModal">Batal</button>
                            <button type="submit" class="btn btn-success text-white px-4 fw-bold rounded-3" :disabled="importForm.processing || !importForm.file">
                                <i class="bx bx-upload me-1"></i> Mulai Proses Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
