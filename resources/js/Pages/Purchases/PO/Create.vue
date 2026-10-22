<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { showSuccess, showWarning, showError } from '@/Utils/swal';

const props = defineProps({
    suppliers: Array,
    medicines: Array,
});

const selectedSupplier = ref(props.suppliers[0] || null);

const poForm = useForm({
    supplier_id: props.suppliers[0]?.id || 1,
    warehouse_name: 'Gudang Utama',
    order_date: new Date().toISOString().slice(0, 10),
    expected_delivery_date: new Date(Date.now() + 3 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
    payment_term_days: props.suppliers[0]?.payment_terms_days || 30,
    notes_supplier: 'Mohon kirim barang dengan expired date minimal 12 bulan.',
    notes_internal: '',
    items: [],
});

const searchQuery = ref('');

const filteredMedicines = computed(() => {
    if (!searchQuery.value) return [];
    return props.medicines.filter(m => 
        m.nama.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
        m.kode.toLowerCase().includes(searchQuery.value.toLowerCase())
    ).slice(0, 8);
});

const onSupplierChange = () => {
    const supp = props.suppliers.find(s => s.id === poForm.supplier_id);
    if (supp) {
        selectedSupplier.value = supp;
        poForm.payment_term_days = supp.payment_terms_days || 30;
    }
};

const addItem = (med) => {
    const existing = poForm.items.find(i => i.medicine_id === med.kode);
    if (existing) {
        existing.order_quantity += 1;
    } else {
        poForm.items.push({
            medicine_id: med.kode,
            medicine_name: med.nama,
            medicine_unit: med.jenis_obat || 'PCS',
            unit_id: med.satuan_pembelian_id || null,
            order_quantity: 10,
            notes: '',
        });
    }
    searchQuery.value = '';
};

const removeItem = (index) => {
    poForm.items.splice(index, 1);
};

// Smart Reorder Engine Integration
const isLoadingRecommendation = ref(false);

const loadSmartRecommendations = async () => {
    isLoadingRecommendation.value = true;
    try {
        const res = await axios.get(route('purchases.po.recommendations'));
        if (res.data.success && res.data.data.length > 0) {
            let addedCount = 0;
            res.data.data.forEach(rec => {
                const existing = poForm.items.find(i => i.medicine_id === rec.medicine_id);
                if (!existing) {
                    poForm.items.push({
                        medicine_id: rec.medicine_id,
                        medicine_name: rec.medicine_name,
                        medicine_unit: 'PCS',
                        unit_id: null,
                        order_quantity: rec.recommended_qty || 10,
                        notes: 'Rekomendasi stok minimum',
                    });
                    addedCount++;
                }
            });
            showSuccess('Smart Reorder Berhasil!', `Berhasil menambahkan ${addedCount} obat rekomendasi (stok di bawah minimum) ke dalam Surat Pesanan.`);
        } else {
            showWarning('Stok Aman', 'Semua stok obat saat ini masih mencukupi atau di atas batas minimum.');
        }
    } catch (err) {
        showError('Gagal', 'Gagal mengambil rekomendasi stok obat.');
    } finally {
        isLoadingRecommendation.value = false;
    }
};

const totalOrderedItems = computed(() => {
    return poForm.items.length;
});

const totalOrderedQuantity = computed(() => {
    return poForm.items.reduce((sum, item) => sum + (Number(item.order_quantity) || 0), 0);
});

const submitPo = () => {
    if (poForm.items.length === 0) {
        showWarning('PO Kosong!', 'Tambahkan minimal 1 item obat ke dalam Surat Pesanan (PO).');
        return;
    }

    for (let i = 0; i < poForm.items.length; i++) {
        const item = poForm.items[i];
        if (!item.order_quantity || item.order_quantity <= 0) {
            showWarning('Kuantitas Belum Valid', `Jumlah pesanan untuk ${item.medicine_name} harus lebih dari 0.`);
            return;
        }
    }

    poForm.post(route('purchases.po.store'));
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4 po-create-module">
            <div class="content mt-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge-module-tag">
                                <i class="bx bx-cart-download me-1"></i> Surat Pesanan (SP)
                            </span>
                        </div>
                        <h2 class="fw-bold page-title mb-1">
                            <i class="bx bx-file-blank text-primary me-2"></i>Buat Purchase Order (Surat Pesanan)
                        </h2>
                        <p class="page-subtitle mb-0">
                            Pemesanan kuantitas obat resmi ke PBF/Supplier tanpa perlu input harga.
                        </p>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-warning text-dark fw-bold shadow-sm" @click="loadSmartRecommendations" :disabled="isLoadingRecommendation">
                            <i class="bx bx-bulb me-1"></i> 💡 Rekomendasi Stok Min.
                        </button>
                        <Link :href="route('purchases.po.index')" class="btn btn-nav-secondary">
                            ← Batal / Kembali
                        </Link>
                    </div>
                </div>

                <!-- ALERT NOTICE: HARGA TIDAK PERLU DIINPUT KARENA DAPAT BERUBAH SEWAKTU-WAKTU -->
                <div class="alert-po-notice mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="notice-icon">
                            <i class="bx bx-bulb"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                <h6 class="fw-bold text-white mb-0">Alur Standar SP Apotek: Cukup Input Jumlah (Quantity) Obat</h6>
                                <span class="badge bg-warning text-dark fw-bold">HARGA TIDAK PERLU DIINPUTKAN</span>
                            </div>
                            <p class="text-slate-200 small mb-0">
                                Sesuai prosedur pemesanan obat ke PBF, di bagian Purchase Order (PO) <strong>hanya menginputkan jumlah pesanan (kuantitas) obat</strong>. Harga tidak perlu diinputkan karena harga dari PBF dapat berubah sewaktu-waktu. Harga resmi, diskon, dan PPN akan dicatat saat pengisian <strong>Faktur Pembelian</strong> ketika barang fisik dan faktur tiba.
                            </p>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitPo">
                    <div class="row g-3">
                        <!-- Left Column: Supplier & Options -->
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm p-3 mb-3 form-card">
                                <h6 class="fw-bold text-cyan mb-3">
                                    <i class="bx bx-buildings me-1"></i>1. Informasi PBF / Supplier
                                </h6>

                                <div class="mb-3">
                                    <label class="form-label text-slate-300 small fw-bold">Pilih PBF / Supplier <span class="text-danger">*</span></label>
                                    <select class="form-select po-input" v-model="poForm.supplier_id" @change="onSupplierChange" required>
                                        <option v-for="s in suppliers" :key="s.id" :value="s.id">
                                            {{ s.name }} ({{ s.code }})
                                        </option>
                                    </select>
                                </div>

                                <template v-if="selectedSupplier">
                                    <div class="p-3 rounded small mb-3 supplier-preview-box">
                                        <div class="mb-1"><strong class="text-white">Alamat:</strong> <span class="text-slate-300">{{ selectedSupplier.address || '-' }}</span></div>
                                        <div class="mb-1"><strong class="text-white">Kontak:</strong> <span class="text-slate-300">{{ selectedSupplier.contact_person || '-' }} ({{ selectedSupplier.phone || '-' }})</span></div>
                                        <div><strong class="text-white">Termin Bayar:</strong> <span class="badge bg-secondary">{{ selectedSupplier.payment_terms_days || 30 }} Hari</span></div>
                                    </div>
                                </template>

                                <div class="mb-3">
                                    <label class="form-label text-slate-300 small fw-bold">Gudang / Lokasi Tujuan</label>
                                    <select class="form-select form-select-sm po-input" v-model="poForm.warehouse_name" required>
                                        <option value="Gudang Utama">Gudang Utama</option>
                                        <option value="Gudang Belakang">Gudang Belakang</option>
                                        <option value="Apotek Pelayanan">Apotek Pelayanan</option>
                                    </select>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label text-slate-300 small fw-bold">Tanggal PO / SP</label>
                                        <input type="date" class="form-control form-control-sm po-input" v-model="poForm.order_date" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label text-slate-300 small fw-bold">Estimasi Tiba</label>
                                        <input type="date" class="form-control form-control-sm po-input" v-model="poForm.expected_delivery_date">
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label text-slate-300 small fw-bold">Catatan untuk PBF</label>
                                    <textarea class="form-control form-control-sm po-input" v-model="poForm.notes_supplier" rows="3" placeholder="Contoh: Mohon kirim expired date minimal 12 bulan..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Item Selection & Quantity Table -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm p-3 mb-3 form-card">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                    <h6 class="fw-bold text-cyan mb-0">
                                        <i class="bx bx-capsule me-1"></i>2. Item Obat yang Dipesan
                                    </h6>
                                    <span class="badge bg-primary fs-6">{{ totalOrderedItems }} Item Dipesan</span>
                                </div>

                                <!-- Fast Search Box -->
                                <div class="position-relative mb-3">
                                    <div class="input-group po-search-group">
                                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                                        <input type="text" class="form-control" v-model="searchQuery" placeholder="Ketik nama obat atau barcode untuk menambahkan ke Surat Pesanan...">
                                    </div>

                                    <!-- Dropdown Autocomplete Result -->
                                    <div v-if="filteredMedicines.length > 0" class="list-group position-absolute w-100 shadow-lg z-3 mt-1 po-autocomplete">
                                        <button v-for="m in filteredMedicines" :key="m.kode" type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" @click="addItem(m)">
                                            <div>
                                                <strong class="text-white">{{ m.nama }}</strong>
                                                <small class="text-slate-400 ms-2 font-monospace">({{ m.kode }})</small>
                                            </div>
                                            <span class="badge bg-info text-dark">Stok Saat Ini: {{ m.stok }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- PO Items Table (Kuantitas & Satuan Saja) -->
                                <div class="table-responsive mb-3">
                                    <table class="table po-item-table align-middle">
                                        <thead>
                                            <tr>
                                                <th style="width: 40px;" class="text-center">#</th>
                                                <th>Nama Obat / Produk</th>
                                                <th style="width: 140px;" class="text-center">Satuan Pesanan</th>
                                                <th style="width: 130px;" class="text-center">Jumlah Pesan (Qty)</th>
                                                <th>Keterangan / Catatan</th>
                                                <th style="width: 50px;" class="text-center">Hapus</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, idx) in poForm.items" :key="item.medicine_id">
                                                <td class="text-center text-slate-400 small">{{ idx + 1 }}</td>
                                                <td>
                                                    <div class="fw-bold text-white">{{ item.medicine_name }}</div>
                                                    <small class="text-slate-400 font-monospace">{{ item.medicine_id }}</small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge-unit">{{ item.medicine_unit || 'PCS' }}</span>
                                                </td>
                                                <td>
                                                    <input type="number" 
                                                        class="form-control form-control-sm text-center fw-bold po-qty-input" 
                                                        v-model.number="item.order_quantity" 
                                                        min="1"
                                                        placeholder="Qty">
                                                </td>
                                                <td>
                                                    <input type="text" 
                                                        class="form-control form-control-sm po-input" 
                                                        v-model="item.notes" 
                                                        placeholder="Keterangan (opsional)">
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-action-delete" @click="removeItem(idx)" title="Hapus obat">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr v-if="poForm.items.length === 0">
                                                <td colspan="6" class="text-center py-5">
                                                    <div class="text-slate-400">
                                                        <i class="bx bx-cart fs-1 d-block mb-2 opacity-50"></i>
                                                        Belum ada obat yang ditambahkan ke Surat Pesanan.<br>
                                                        <small class="text-slate-400">Gunakan kolom pencarian di atas atau klik tombol 💡 <strong>Rekomendasi Stok Min</strong>.</small>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Summary & Submit Bar -->
                                <div class="po-summary-box p-3 rounded-3">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                        <div>
                                            <div class="text-slate-400 small mb-1">Total Akumulasi Pesanan:</div>
                                            <div class="d-flex align-items-center gap-3">
                                                <div>
                                                    <strong class="fs-4 text-white font-monospace">{{ totalOrderedItems }}</strong>
                                                    <span class="text-slate-400 small ms-1">Item Obat</span>
                                                </div>
                                                <div class="text-slate-400">|</div>
                                                <div>
                                                    <strong class="fs-4 text-cyan font-monospace">{{ totalOrderedQuantity }}</strong>
                                                    <span class="text-slate-400 small ms-1">Total Quantity</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary btn-lg fw-bold px-4 shadow-sm" :disabled="poForm.processing">
                                                <i class="bx bx-check-circle me-1"></i> Terbitkan Surat Pesanan (PO)
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </LegacyLayout>
</template>

<style scoped>
/* ============================================================ */
/* ULTRA HIGH CONTRAST DARK MODE THEME FOR PO CREATE            */
/* ============================================================ */
.po-create-module {
    color: #f8fafc;
}

.badge-module-tag {
    background: rgba(56, 189, 248, 0.15);
    color: #38bdf8;
    border: 1px solid rgba(56, 189, 248, 0.35);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 3px 10px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
}

.page-title {
    color: #ffffff !important;
    font-size: 1.65rem;
    letter-spacing: -0.01em;
}

.page-subtitle {
    color: #94a3b8 !important;
    font-size: 0.9rem;
}

:global(body:not(.dark-mode)) .page-title {
    color: #0f172a !important;
}

:global(body:not(.dark-mode)) .page-subtitle {
    color: #475569 !important;
}

.btn-nav-secondary {
    background: #1e293b;
    color: #cbd5e1 !important;
    border: 1px solid #334155;
    padding: 8px 16px;
    border-radius: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
    font-size: 0.88rem;
    display: inline-flex;
    align-items: center;
}

.btn-nav-secondary:hover {
    background: #334155;
    color: #ffffff !important;
    border-color: #475569;
}

:global(body:not(.dark-mode)) .btn-nav-secondary {
    background: #f1f5f9;
    color: #334155 !important;
    border-color: #cbd5e1;
}

/* Notice Banner */
.alert-po-notice {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.98));
    border: 1px solid #f59e0b;
    border-left: 6px solid #f59e0b;
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
}

:global(body:not(.dark-mode)) .alert-po-notice {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-left: 6px solid #f59e0b;
}

:global(body:not(.dark-mode)) .alert-po-notice h6 {
    color: #92400e !important;
}

:global(body:not(.dark-mode)) .alert-po-notice p {
    color: #78350f !important;
}

.notice-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: rgba(245, 158, 11, 0.2);
    border: 1px solid rgba(245, 158, 11, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fbbf24;
    font-size: 1.6rem;
    flex-shrink: 0;
}

/* Cards & Inputs */
.form-card {
    background: #1e293b !important;
    border: 1px solid #334155 !important;
    border-radius: 12px;
}

:global(body:not(.dark-mode)) .form-card {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
}

.text-cyan {
    color: #38bdf8 !important;
}

:global(body:not(.dark-mode)) .text-cyan {
    color: #0284c7 !important;
}

.supplier-preview-box {
    background: #0f172a;
    border: 1px solid #334155;
}

:global(body:not(.dark-mode)) .supplier-preview-box {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.po-input {
    background-color: #0f172a !important;
    border-color: #334155 !important;
    color: #ffffff !important;
}

:global(body:not(.dark-mode)) .po-input {
    background-color: #ffffff !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}

.po-search-group .input-group-text {
    background: #0f172a !important;
    border-color: #334155 !important;
    color: #94a3b8 !important;
}

.po-search-group .form-control {
    background: #0f172a !important;
    border-color: #334155 !important;
    color: #ffffff !important;
}

:global(body:not(.dark-mode)) .po-search-group .input-group-text,
:global(body:not(.dark-mode)) .po-search-group .form-control {
    background-color: #ffffff !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}

.po-autocomplete {
    background: #1e293b;
    border: 1px solid #475569;
    border-radius: 8px;
    max-height: 280px;
    overflow-y: auto;
}

.po-autocomplete button {
    background: #1e293b;
    border-color: #334155;
    color: #f8fafc;
}

.po-autocomplete button:hover {
    background: #334155;
    color: #ffffff;
}

/* Items Table */
.po-item-table thead th {
    background-color: #0f172a !important;
    color: #f8fafc !important;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px solid #475569 !important;
    padding: 12px 10px !important;
}

:global(body:not(.dark-mode)) .po-item-table thead th {
    background-color: #f1f5f9 !important;
    color: #334155 !important;
    border-bottom-color: #cbd5e1 !important;
}

.po-item-table tbody td {
    background-color: #1e293b !important;
    border-bottom: 1px solid #334155 !important;
    padding: 12px 10px !important;
}

:global(body:not(.dark-mode)) .po-item-table tbody td {
    background-color: #ffffff !important;
    border-bottom-color: #f1f5f9 !important;
}

.badge-unit {
    background: rgba(148, 163, 184, 0.2);
    color: #f1f5f9 !important;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 0.75rem;
    font-weight: 600;
}

.po-qty-input {
    background-color: #0f172a !important;
    border-color: #38bdf8 !important;
    color: #38bdf8 !important;
    font-size: 1rem;
}

.btn-action-delete {
    background: rgba(239, 68, 68, 0.18);
    color: #f87171 !important;
    border: 1px solid rgba(239, 68, 68, 0.4);
    border-radius: 8px;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.btn-action-delete:hover {
    background: #dc2626;
    color: #ffffff !important;
}

.po-summary-box {
    background: #0f172a;
    border: 1px solid #334155;
}

:global(body:not(.dark-mode)) .po-summary-box {
    background: #f8fafc;
    border-color: #e2e8f0;
}
</style>
