<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { showSuccess, showWarning, showError } from '@/Utils/swal';

const props = defineProps({
    movements: Object,
    summary: Object,
    medicines: Array,
    categories: Array,
    suppliers: Array,
    users: Array,
    selectedMedicineDetail: Object,
    filters: Object,
});

const filterForm = ref({
    date_preset: props.filters?.date_preset || 'this_month',
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || '',
    medicine_id: props.filters?.medicine_id || '',
    kategori: props.filters?.kategori || '',
    warehouse_name: props.filters?.warehouse_name || '',
    batch_number: props.filters?.batch_number || '',
    expired_status: props.filters?.expired_status || '',
    supplier_id: props.filters?.supplier_id || '',
    movement_type: props.filters?.movement_type || '',
    user_id: props.filters?.user_id || '',
    search: props.filters?.search || '',
    quick_filter: props.filters?.quick_filter || '',
});

const applyFilters = () => {
    router.get(route('inventory.movements'), filterForm.value, { preserveState: true, replace: true });
};

const setQuickFilter = (type) => {
    filterForm.value.quick_filter = filterForm.value.quick_filter === type ? '' : type;
    applyFilters();
};

const resetFilters = () => {
    filterForm.value = {
        date_preset: 'this_month',
        start_date: '',
        end_date: '',
        medicine_id: '',
        kategori: '',
        warehouse_name: '',
        batch_number: '',
        expired_status: '',
        supplier_id: '',
        movement_type: '',
        user_id: '',
        search: '',
        quick_filter: '',
    };
    applyFilters();
};

// Count active filters for badge
const activeFilterCount = computed(() => {
    let count = 0;
    if (filterForm.value.medicine_id) count++;
    if (filterForm.value.kategori) count++;
    if (filterForm.value.warehouse_name) count++;
    if (filterForm.value.batch_number) count++;
    if (filterForm.value.supplier_id) count++;
    if (filterForm.value.movement_type) count++;
    if (filterForm.value.user_id) count++;
    if (filterForm.value.expired_status) count++;
    return count;
});

// Adjustment Form Modal
const adjustmentForm = useForm({
    medicine_id: '',
    movement_type: 'ADJUSTMENT_IN',
    quantity: 1,
    warehouse_name: 'Gudang Utama',
    batch_number: '',
    expired_date: '',
    supplier_id: '',
    notes: '',
});

const submitAdjustment = () => {
    adjustmentForm.post(route('inventory.movements.adjustment'), {
        onSuccess: () => {
            showSuccess('Berhasil!', 'Penyesuaian stok manual berhasil disimpan.');
            adjustmentForm.reset();
            const modalEl = document.getElementById('adjustmentModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        },
        onError: () => {
            showError('Gagal!', 'Periksa kembali data penyesuaian stok.');
        }
    });
};

// Reconcile Audit Engine
const isReconciling = ref(false);
const reconcileResults = ref([]);

const runReconcileAudit = async () => {
    isReconciling.value = true;
    try {
        const res = await axios.get(route('inventory.movements.reconcile'), {
            params: { medicine_id: filterForm.value.medicine_id }
        });
        if (res.data.success) {
            reconcileResults.value = res.data.data;
            const modal = new bootstrap.Modal(document.getElementById('reconcileModal'));
            modal.show();
        }
    } catch (err) {
        showError('Gagal Audit!', 'Gagal menjalankan rekonsiliasi stok.');
    } finally {
        isReconciling.value = false;
    }
};

const formatNumber = (val) => {
    return new Intl.NumberFormat('id-ID').format(val || 0);
};

const getMovementLabel = (type) => {
    const labels = {
        'PURCHASE'       : 'Pembelian',
        'SALE'           : 'Penjualan',
        'SALE_RETURN'    : 'Retur Jual',
        'PURCHASE_RETURN': 'Retur Beli',
        'STOCK_OPNAME'   : 'Stock Opname',
        'ADJUSTMENT_IN'  : 'Penyesuaian +',
        'ADJUSTMENT_OUT' : 'Penyesuaian -',
        'DAMAGED'        : 'Rusak',
        'EXPIRED'        : 'Kedaluwarsa',
        'LOST'           : 'Hilang',
        'BONUS'          : 'Bonus',
        'TRANSFER_IN'    : 'Transfer Masuk',
        'TRANSFER_OUT'   : 'Transfer Keluar',
        'INITIAL_STOCK'  : 'Stok Awal',
        'REVERSAL'       : 'Reversal',
    };
    return labels[type] || type;
};

const printStockCard = () => {
    window.print();
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-transfer text-primary me-2"></i>Kartu Stok Obat & Histori Mutasi</h2>
                        <p class="text-muted small mb-0">Single Source of Truth Pelacakan Seluruh Pergerakan Inventory Apotek</p>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary fw-bold shadow-xs" @click="runReconcileAudit" :disabled="isReconciling">
                            <i class="bx bx-check-shield me-1"></i> 🔍 Reconcile Audit Stok
                        </button>
                        <button class="btn btn-warning text-dark fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#adjustmentModal">
                            <i class="bx bx-plus-circle me-1"></i> + Input Penyesuaian / Rusak
                        </button>
                        <button class="btn btn-light border fw-bold shadow-xs" @click="printStockCard">
                            <i class="bx bx-printer me-1"></i> Cetak / Print
                        </button>
                    </div>
                </div>

                <!-- Top Summary Metrics Card -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Stok Awal Periode</span>
                            <h3 class="fw-bold text-secondary mb-0">{{ formatNumber(summary.opening_stock) }}</h3>
                            <small class="text-muted" style="font-size: 0.72rem;">Sebelum {{ filterForm.start_date }}</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Total Barang Masuk (+)</span>
                            <h3 class="fw-bold text-success mb-0">+{{ formatNumber(summary.period_in) }}</h3>
                            <small class="text-muted" style="font-size: 0.72rem;">Pembelian, Retur, Adjust In</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Total Barang Keluar (-)</span>
                            <h3 class="fw-bold text-danger mb-0">-{{ formatNumber(summary.period_out) }}</h3>
                            <small class="text-muted" style="font-size: 0.72rem;">POS Penjualan, Expired, Adjust Out</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Stok Akhir Periode</span>
                            <h3 class="fw-bold text-primary mb-0">{{ formatNumber(summary.closing_stock) }}</h3>
                            <small class="text-muted" style="font-size: 0.72rem;">Per {{ filterForm.end_date }}</small>
                        </div>
                    </div>
                </div>

                <!-- Single Medicine Quick View Detail if Selected -->
                <div v-if="selectedMedicineDetail" class="card bg-primary bg-opacity-10 border-primary border-opacity-25 p-3 mb-4 rounded-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="badge bg-primary me-2">{{ selectedMedicineDetail.kategori }}</span>
                            <h4 class="fw-bold text-dark d-inline mb-0">{{ selectedMedicineDetail.nama }}</h4>
                            <span class="text-muted ms-2">({{ selectedMedicineDetail.kode }})</span>
                        </div>
                        <div class="d-flex gap-3 text-center">
                            <div>
                                <span class="text-muted small d-block">Stok Fisik Master</span>
                                <strong class="fs-5 text-primary">{{ selectedMedicineDetail.stok }}</strong>
                            </div>
                            <div>
                                <span class="text-muted small d-block">Min / Max Stok</span>
                                <strong class="fs-6 text-dark">{{ selectedMedicineDetail.min_stok }} / {{ selectedMedicineDetail.max_stok }}</strong>
                            </div>
                            <div>
                                <span class="text-muted small d-block">Jumlah Batch Aktif</span>
                                <strong class="fs-5 text-success">{{ selectedMedicineDetail.batches.length }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter & Quick Buttons Bar -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-3 rounded-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <!-- Quick Filter Chips -->
                        <div class="d-flex gap-1 flex-wrap">
                            <button class="btn btn-xs btn-sm" :class="filterForm.quick_filter === '' ? 'btn-primary' : 'btn-outline-secondary'" @click="setQuickFilter('')">
                                Semua
                            </button>
                            <button class="btn btn-xs btn-sm" :class="filterForm.quick_filter === 'in' ? 'btn-success' : 'btn-outline-success'" @click="setQuickFilter('in')">
                                + Barang Masuk
                            </button>
                            <button class="btn btn-xs btn-sm" :class="filterForm.quick_filter === 'out' ? 'btn-danger' : 'btn-outline-danger'" @click="setQuickFilter('out')">
                                - Barang Keluar
                            </button>
                            <button class="btn btn-xs btn-sm" :class="filterForm.quick_filter === 'return' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark'" @click="setQuickFilter('return')">
                                Retur
                            </button>
                            <button class="btn btn-xs btn-sm" :class="filterForm.quick_filter === 'opname' ? 'btn-info text-dark' : 'btn-outline-info text-dark'" @click="setQuickFilter('opname')">
                                Stock Opname
                            </button>
                            <button class="btn btn-xs btn-sm" :class="filterForm.quick_filter === 'damaged_expired' ? 'btn-dark' : 'btn-outline-dark'" @click="setQuickFilter('damaged_expired')">
                                Expired / Rusak
                            </button>
                        </div>

                        <!-- Date Preset & Search Box -->
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <div class="input-group input-group-sm" style="max-width: 220px;">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text" class="form-control" v-model="filterForm.search" @keyup.enter="applyFilters" placeholder="Cari obat, ref, batch...">
                            </div>

                            <select class="form-select form-select-sm" style="width: 140px;" v-model="filterForm.date_preset" @change="applyFilters">
                                <option value="today">Hari Ini</option>
                                <option value="yesterday">Kemarin</option>
                                <option value="7days">7 Hari Terakhir</option>
                                <option value="30days">30 Hari Terakhir</option>
                                <option value="this_month">Bulan Ini</option>
                                <option value="last_month">Bulan Lalu</option>
                                <option value="this_year">Tahun Ini</option>
                                <option value="custom">Custom Date</option>
                            </select>

                            <button class="btn btn-sm btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#advancedFilterModal">
                                <i class="bx bx-slider-alt me-1"></i> Advanced Filter
                                <span v-if="activeFilterCount > 0" class="badge bg-danger ms-1">{{ activeFilterCount }}</span>
                            </button>

                            <button class="btn btn-sm btn-outline-secondary" @click="resetFilters" title="Reset Filter">
                                <i class="bx bx-reset"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Table Kartu Stok -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>Tanggal & Jam</th>
                                    <th>Obat</th>
                                    <th>Batch / Expired</th>
                                    <th>Jenis Transaksi</th>
                                    <th>No. Referensi</th>
                                    <th>Transaksi (Qty & Satuan)</th>
                                    <th class="text-end text-success">Masuk (+)</th>
                                    <th class="text-end text-danger">Keluar (-)</th>
                                    <th class="text-end fw-bold">Saldo Base Stock</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in movements.data" :key="m.id">
                                    <td class="small">{{ m.created_at }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ m.medicine_name }}</div>
                                        <small class="text-muted">{{ m.medicine_id }} | {{ m.medicine_category }}</small>
                                    </td>
                                    <td class="small">
                                        <div><span class="badge bg-light text-dark border">{{ m.batch_number || '-' }}</span></div>
                                        <small class="text-muted">Exp: {{ m.expired_date || '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge" :class="{
                                            'bg-success': m.movement_type === 'PURCHASE' || m.movement_type === 'BONUS',
                                            'bg-primary': m.movement_type === 'SALE',
                                            'bg-warning text-dark': m.movement_type === 'STOCK_OPNAME' || m.movement_type === 'PURCHASE_RETURN' || m.movement_type === 'SALE_RETURN',
                                            'bg-danger': m.movement_type === 'DAMAGED' || m.movement_type === 'EXPIRED' || m.movement_type === 'LOST',
                                            'bg-info text-dark': m.movement_type === 'TRANSFER_IN' || m.movement_type === 'TRANSFER_OUT' || m.movement_type === 'INITIAL_STOCK'
                                        }">
                                            {{ getMovementLabel(m.movement_type) }}
                                        </span>
                                    </td>
                                    <td class="small font-monospace fw-bold text-primary">{{ m.reference_number || m.movement_number }}</td>
                                    
                                    <!-- Transaksi (Qty & Satuan Asli) -->
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace">
                                            {{ m.type === 'in' ? '+' : '-' }}{{ m.transaction_quantity || m.quantity }} {{ m.unit_name || 'Unit' }}
                                        </span>
                                        <small class="d-block text-muted" v-if="m.conversion_to_base > 1" style="font-size: 0.72rem;">
                                            (1 {{ m.unit_name }} = {{ m.conversion_to_base }} Base)
                                        </small>
                                    </td>

                                    <!-- Masuk (+) Base -->
                                    <td class="text-end fw-bold">
                                        <span v-if="m.movement_type === 'STOCK_OPNAME' && m.quantity === 0" class="text-muted font-monospace">
                                            0
                                        </span>
                                        <span v-else-if="m.type === 'in'" class="text-success">
                                            +{{ formatNumber(m.quantity) }}
                                        </span>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                    <!-- Keluar (-) Base -->
                                    <td class="text-end fw-bold">
                                        <span v-if="m.type === 'out'" class="text-danger">
                                            -{{ formatNumber(m.quantity) }}
                                        </span>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                    <!-- Saldo Base -->
                                    <td class="text-end fw-bold text-primary fs-6">
                                        {{ formatNumber(m.stock_after) }}
                                    </td>
                                    <td class="small text-muted">{{ m.user_name }}</td>
                                </tr>
                                <tr v-if="!movements.data || movements.data.length === 0">
                                    <td colspan="10" class="text-center py-4 text-muted">Belum ada data pergerakan stok (Stock Movement) sesuai filter ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links -->
                    <Pagination :links="movements.links" />
                </div>
            </div>
        </section>

        <!-- Advanced Filter Modal -->
        <div class="modal fade" id="advancedFilterModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bx bx-slider-alt me-1"></i> Advanced Filter Kartu Stok</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label font-weight-bold">Pilih Obat</label>
                                <select class="form-select" v-model="filterForm.medicine_id">
                                    <option value="">Semua Obat</option>
                                    <option v-for="med in medicines" :key="med.kode" :value="med.kode">{{ med.nama }} ({{ med.kode }})</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-weight-bold">Kategori Obat</label>
                                <select class="form-select" v-model="filterForm.kategori">
                                    <option value="">Semua Kategori</option>
                                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label font-weight-bold">Gudang</label>
                                <select class="form-select" v-model="filterForm.warehouse_name">
                                    <option value="">Semua Gudang</option>
                                    <option value="Gudang Utama">Gudang Utama</option>
                                    <option value="Gudang Belakang">Gudang Belakang</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-weight-bold">Supplier PBF</label>
                                <select class="form-select" v-model="filterForm.supplier_id">
                                    <option value="">Semua Supplier</option>
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-weight-bold">User Penginput</label>
                                <select class="form-select" v-model="filterForm.user_id">
                                    <option value="">Semua User</option>
                                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role }})</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-weight-bold">Jenis Movement</label>
                                <select class="form-select" v-model="filterForm.movement_type">
                                    <option value="">Semua Jenis</option>
                                    <option value="PURCHASE">PURCHASE (Pembelian PO)</option>
                                    <option value="SALE">SALE (POS Penjualan Kasir)</option>
                                    <option value="STOCK_OPNAME">STOCK OPNAME</option>
                                    <option value="PURCHASE_RETURN">PURCHASE RETURN</option>
                                    <option value="SALE_RETURN">SALE RETURN</option>
                                    <option value="DAMAGED">DAMAGED (Barang Rusak)</option>
                                    <option value="EXPIRED">EXPIRED (Barang Kadaluarsa)</option>
                                    <option value="BONUS">BONUS (Free Goods Supplier)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-weight-bold">Status Expired Date</label>
                                <select class="form-select" v-model="filterForm.expired_status">
                                    <option value="">Semua Status Expired</option>
                                    <option value="expired_30">Expired dalam 30 Hari</option>
                                    <option value="expired_past">Sudah Expired</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="resetFilters" data-bs-dismiss="modal">Reset</button>
                        <button type="button" class="btn btn-primary fw-bold" @click="applyFilters" data-bs-dismiss="modal">Terapkan Filter</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Adjustment Modal -->
        <div class="modal fade" id="adjustmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title"><i class="bx bx-plus-circle me-1"></i> Penyesuaian Stok Manual / Barang Rusak</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitAdjustment">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label font-weight-bold">Pilih Obat</label>
                                <select class="form-select" v-model="adjustmentForm.medicine_id" required>
                                    <option v-for="med in medicines" :key="med.kode" :value="med.kode">{{ med.nama }} (Stok: {{ med.stok }})</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-weight-bold">Jenis Penyesuaian</label>
                                <select class="form-select" v-model="adjustmentForm.movement_type" required>
                                    <option value="ADJUSTMENT_IN">ADJUSTMENT IN (Tambah Stok)</option>
                                    <option value="ADJUSTMENT_OUT">ADJUSTMENT OUT (Kurangi Stok)</option>
                                    <option value="DAMAGED">DAMAGED (Barang Rusak)</option>
                                    <option value="EXPIRED">EXPIRED (Barang Kadaluarsa)</option>
                                    <option value="LOST">LOST (Barang Hilang)</option>
                                    <option value="BONUS">BONUS (Bonus Supplier)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-weight-bold">Jumlah Quantity</label>
                                <input type="number" class="form-control fw-bold" v-model.number="adjustmentForm.quantity" min="1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-weight-bold">Alasan / Catatan Penyesuaian</label>
                                <textarea class="form-control" v-model="adjustmentForm.notes" rows="2" placeholder="Tuliskan catatan lengkap..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-warning fw-bold text-dark" :disabled="adjustmentForm.processing">Simpan Penyesuaian</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reconcile Audit Modal -->
        <div class="modal fade" id="reconcileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bx bx-check-shield me-1"></i> Hasil Audit Rekonsiliasi Kartu Stok</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Obat</th>
                                    <th class="text-center">Stok Master DB</th>
                                    <th class="text-center">Hasil Hitung Mutasi</th>
                                    <th class="text-center">Selisih</th>
                                    <th class="text-center">Status Audit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="res in reconcileResults" :key="res.medicine_id">
                                    <td><strong>{{ res.medicine_name }}</strong> <small class="text-muted">({{ res.medicine_id }})</small></td>
                                    <td class="text-center fw-bold">{{ res.master_stock }}</td>
                                    <td class="text-center fw-bold">{{ res.calculated_stock }}</td>
                                    <td class="text-center fw-bold" :class="res.difference === 0 ? 'text-success' : 'text-danger'">{{ res.difference }}</td>
                                    <td class="text-center">
                                        <span class="badge" :class="res.status === 'MATCH' ? 'bg-success' : 'bg-danger'">
                                            {{ res.status === 'MATCH' ? '✓ MATCH' : '⚠ MISMATCH' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
