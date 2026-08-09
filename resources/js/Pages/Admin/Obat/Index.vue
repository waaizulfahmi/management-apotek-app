<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import RupiahInput from '@/Components/RupiahInput.vue';
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import { showConfirm } from '@/Utils/swal';

const props = defineProps({
    obats: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');

const handleSearch = () => {
    router.get(route('admin.obat.index'), { search: search.value }, { preserveState: true, replace: true });
};

const generateKodeObat = () => {
    return 'OBT-' + Math.floor(1000 + Math.random() * 9000);
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
});

const openEditModal = (obat) => {
    editObatData.value = obat;
    editForm.nama = obat.nama;
    editForm.jenis_obat = obat.jenis_obat;
    editForm.kategori = obat.kategori;
    editForm.harga = obat.harga;
    editForm.stok = obat.stok;
    editForm.min_stok = obat.min_stok || 10;
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
    showConfirm("Hapus Obat", "Apakah Anda yakin ingin menghapus data obat ini?", () => {
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
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark mb-0">Data Obat</h2>

                    <div class="d-flex align-items-center gap-3">
                        <div class="input-group" style="max-width: 220px;">
                            <span class="input-group-text bg-primary text-white"><i class="bx bx-search"></i></span>
                            <input type="text" class="form-control" v-model="search" @keyup.enter="handleSearch" placeholder="Cari Obat...">
                        </div>

                        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tambahObatModal">
                            <i class="bx bx-plus"></i> Tambah
                        </button>
                    </div>
                </div>

                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>Gambar</th>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Stok Fisik</th>
                                <th>Stok Min</th>
                                <th>Jenis Obat</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="obat in obats.data" :key="obat.kode">
                                <td>
                                    <img :src="`/Assets/Obat/${obat.gambar}`" @error="(e) => e.target.src = '/Assets/img/default-medicine.png'" alt="gambar obat" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                                </td>
                                <td class="fw-bold text-primary">{{ obat.kode }}</td>
                                <td class="fw-bold text-dark">{{ obat.nama }}</td>
                                <td>
                                    <span class="fw-bold fs-6" :class="obat.stok <= (obat.min_stok || 10) ? 'text-danger' : 'text-success'">
                                        {{ obat.stok }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">Min: {{ obat.min_stok || 10 }}</span>
                                </td>
                                <td>{{ obat.jenis_obat }}</td>
                                <td>{{ obat.kategori }}</td>
                                <td>{{ formatCurrency(obat.harga) }}</td>
                                <td class="text-center">
                                    <button class="btn btn-warning btn-sm rounded-2 me-1 text-white" @click="openEditModal(obat)">
                                        <i class="bx bx-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm rounded-2" @click="deleteObat(obat.kode)">
                                        <i class="bx bx-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!obats.data || obats.data.length === 0">
                                <td colspan="8" class="text-center py-4 text-muted">Tidak ada data obat.</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination Links -->
                    <div class="d-flex justify-content-center mt-4" v-if="obats.links">
                        <nav>
                            <ul class="pagination">
                                <li v-for="(link, index) in obats.links" :key="index" class="page-item" :class="{ 'active': link.active, 'disabled': !link.url }">
                                    <Link class="page-link" :href="link.url || '#'" v-html="link.label"></Link>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </section>

        <!-- Modal Tambah Obat -->
        <div class="modal fade" id="tambahObatModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Tambah Obat Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitCreate">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Kode Obat (Otomatis)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control fw-bold font-monospace text-primary" v-model="createForm.kode" required>
                                    <button class="btn btn-outline-secondary" type="button" @click="createForm.kode = generateKodeObat()">
                                        <i class="bx bx-refresh me-1"></i> Generate Ulang
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nama Obat</label>
                                <input type="text" class="form-control" v-model="createForm.nama" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Gambar Obat</label>
                                <input type="file" class="form-control" @input="createForm.gambar = $event.target.files[0]" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Jenis Obat</label>
                                <select class="form-select" v-model="createForm.jenis_obat" required>
                                    <option value="Tablet">Tablet</option>
                                    <option value="Kapsul">Kapsul</option>
                                    <option value="Sirup">Sirup</option>
                                    <option value="Salep">Salep</option>
                                    <option value="Injeksi">Injeksi</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Kategori Obat</label>
                                <select class="form-select" v-model="createForm.kategori" required>
                                    <option value="Antibiotik">Antibiotik</option>
                                    <option value="Antipiretik">Antipiretik</option>
                                    <option value="Analgesik">Analgesik</option>
                                    <option value="Antihistamin">Antihistamin</option>
                                    <option value="Vitamin">Vitamin</option>
                                    <option value="Antiseptik">Antiseptik</option>
                                    <option value="Herbal">Herbal</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Harga (Rp)</label>
                                <RupiahInput v-model="createForm.harga" placeholder="Masukkan harga..." required />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Stok Minimum (Warning Level)</label>
                                <input type="number" class="form-control" v-model="createForm.min_stok" placeholder="Contoh: 10" required>
                                <small class="text-muted d-block mt-1"><i class="bx bx-info-circle me-1"></i>Stok fisik obat awal otomatis bernilai 0, dan akan bertambah dari Penerimaan PBF / Stock Opname.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                            <button type="submit" class="btn btn-primary" :disabled="createForm.processing">Simpan Obat</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Obat -->
        <div class="modal fade" id="editObatModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title">Edit Data Obat</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitEdit">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nama Obat</label>
                                <input type="text" class="form-control" v-model="editForm.nama" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Gambar Obat (Biarkan kosong jika tidak diubah)</label>
                                <input type="file" class="form-control" @input="editForm.gambar = $event.target.files[0]">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Jenis Obat</label>
                                <select class="form-select" v-model="editForm.jenis_obat" required>
                                    <option value="Tablet">Tablet</option>
                                    <option value="Kapsul">Kapsul</option>
                                    <option value="Sirup">Sirup</option>
                                    <option value="Salep">Salep</option>
                                    <option value="Injeksi">Injeksi</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Kategori Obat</label>
                                <select class="form-select" v-model="editForm.kategori" required>
                                    <option value="Antibiotik">Antibiotik</option>
                                    <option value="Antipiretik">Antipiretik</option>
                                    <option value="Analgesik">Analgesik</option>
                                    <option value="Antihistamin">Antihistamin</option>
                                    <option value="Vitamin">Vitamin</option>
                                    <option value="Antiseptik">Antiseptik</option>
                                    <option value="Herbal">Herbal</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Harga (Rp)</label>
                                <RupiahInput v-model="editForm.harga" placeholder="Masukkan harga..." required />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Stok Minimum (Warning Level)</label>
                                <input type="number" class="form-control" v-model="editForm.min_stok" required>
                                <small class="text-muted d-block mt-1"><i class="bx bx-info-circle me-1"></i>Penambahan/pengurangan stok fisik dilakukan melalui modul Penerimaan Barang PBF / Stock Opname.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                            <button type="submit" class="btn btn-warning text-white" :disabled="editForm.processing">Update Obat</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
