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
    outlets: Array,
    selectedMedicineDetail: Object,
    filters: Object,
});

const filterForm = ref({
    date_preset: props.filters?.date_preset || 'this_month',
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || '',
    outlet_id: props.filters?.outlet_id || '',
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
        outlet_id: '',
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
    if (filterForm.value.outlet_id) count++;
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

// ============================================================
// DETAIL KARTU STOK PER OBAT (MODAL & TRANSAKSI MUTASI)
// ============================================================
const showStockCardModal = ref(false);
const isStockCardLoading = ref(false);
const stockCardData = ref(null);
const stockCardModalOutlet = ref('');
const stockCardSearch = ref('');

const openMedicineStockCard = async (medicineId, medicineName, outletIdOverride = null) => {
    showStockCardModal.value = true;
    isStockCardLoading.value = true;
    stockCardData.value = null;
    stockCardSearch.value = '';

    const targetOutlet = outletIdOverride !== null ? outletIdOverride : (filterForm.value.outlet_id || '');
    stockCardModalOutlet.value = targetOutlet;

    try {
        const res = await axios.get(route('inventory.movements.medicine-ledger'), {
            params: {
                medicine_id: medicineId,
                outlet_id: targetOutlet,
                date_preset: filterForm.value.date_preset,
                start_date: filterForm.value.start_date,
                end_date: filterForm.value.end_date,
            }
        });
        if (res.data.success) {
            stockCardData.value = res.data;
        }
    } catch (err) {
        showError('Gagal!', 'Tidak dapat memuat histori mutasi kartu stok obat.');
        showStockCardModal.value = false;
    } finally {
        isStockCardLoading.value = false;
    }
};

const changeModalOutlet = (newOutletId) => {
    if (!stockCardData.value?.medicine?.kode) return;
    openMedicineStockCard(stockCardData.value.medicine.kode, stockCardData.value.medicine.nama, newOutletId);
};

const filteredModalLedger = computed(() => {
    if (!stockCardData.value?.ledger) return [];
    if (!stockCardSearch.value) return stockCardData.value.ledger;
    const q = stockCardSearch.value.toLowerCase();
    return stockCardData.value.ledger.filter(item => {
        return (item.reference_number && item.reference_number.toLowerCase().includes(q)) ||
               (item.movement_number && item.movement_number.toLowerCase().includes(q)) ||
               (item.batch_number && item.batch_number.toLowerCase().includes(q)) ||
               (item.movement_type && item.movement_type.toLowerCase().includes(q)) ||
               (item.notes && item.notes.toLowerCase().includes(q));
    });
});

const printMedicineLedger = () => {
    const printableElement = document.getElementById('printableMedicineLedger');
    if (!printableElement) return;

    const iframe = document.createElement('iframe');
    iframe.name = 'printMedicineLedgerFrame';
    iframe.style.position = 'fixed';
    iframe.style.top = '-9999px';
    iframe.style.left = '-9999px';
    iframe.style.width = '0px';
    iframe.style.height = '0px';
    iframe.style.border = 'none';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow || iframe.contentDocument;
    const iframeDoc = doc.document || doc;

    const medName = stockCardData.value?.medicine?.nama || 'Obat';
    const outletName = stockCardData.value?.outlet?.name || 'Semua_Outlet';

    iframeDoc.open();
    iframeDoc.write(`
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>Kartu_Stok_${medName.replace(/\\s+/g, '_')}_${outletName.replace(/\\s+/g, '_')}</title>
            <style>
                @page { size: A4 landscape; margin: 10mm 12mm; }
                * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    font-size: 11px;
                    line-height: 1.4;
                    color: #0f172a;
                    background: #ffffff;
                    margin: 0;
                    padding: 0;
                }
                .border-bottom-double { border-bottom: 3px double #0f172a !important; }
                .d-flex { display: flex !important; }
                .justify-content-between { justify-content: space-between !important; }
                .align-items-center { align-items: center !important; }
                .gap-2 { gap: 8px !important; }
                .gap-3 { gap: 16px !important; }
                .fw-bold { font-weight: 700 !important; }
                .text-uppercase { text-transform: uppercase !important; }
                .text-dark { color: #0f172a !important; }
                .text-primary { color: #1d4ed8 !important; }
                .text-success { color: #16a34a !important; }
                .text-danger { color: #dc2626 !important; }
                .text-muted { color: #64748b !important; }
                .text-center { text-align: center !important; }
                .text-end { text-align: right !important; }
                .small { font-size: 10px !important; }
                .fs-5 { font-size: 14px !important; }
                .font-monospace { font-family: "Courier New", Courier, monospace !important; }
                .mb-0 { margin-bottom: 0 !important; }
                .mb-1 { margin-bottom: 4px !important; }
                .mb-2 { margin-bottom: 8px !important; }
                .mb-3 { margin-bottom: 12px !important; }
                .p-2 { padding: 8px !important; }
                .p-3 { padding: 12px !important; }
                .border { border: 1px solid #cbd5e1 !important; }
                .rounded { border-radius: 6px !important; }
                .row { display: flex; flex-wrap: wrap; margin-left: -6px; margin-right: -6px; }
                .col-3 { width: 25%; padding: 0 6px; box-sizing: border-box; }
                .col-4 { width: 33.333%; padding: 0 6px; box-sizing: border-box; }
                .col-6 { width: 50%; padding: 0 6px; box-sizing: border-box; }
                .col-12 { width: 100%; padding: 0 6px; box-sizing: border-box; }
                table { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 8px; margin-bottom: 12px; }
                table th, table td { padding: 4px 6px; vertical-align: middle; }
                .table-bordered th, .table-bordered td { border: 1px solid #94a3b8 !important; }
                .table-light th { background-color: #f1f5f9 !important; color: #0f172a !important; font-weight: 700; }
                .badge { display: inline-block; padding: 2px 6px; font-size: 9px; font-weight: 700; border-radius: 4px; border: 1px solid #cbd5e1; }
            </style>
        </head>
        <body>
            ${printableElement.innerHTML}
        </body>
        </html>
    `);
    iframeDoc.close();

    setTimeout(() => {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (e) {
            console.error('Print error:', e);
        } finally {
            setTimeout(() => {
                if (document.body.contains(iframe)) {
                    document.body.removeChild(iframe);
                }
            }, 2500);
        }
    }, 300);
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
                            <!-- Outlet Filter Select -->
                            <div class="input-group input-group-sm" style="max-width: 200px;">
                                <span class="input-group-text bg-light text-primary" title="Filter Outlet"><i class="bx bx-store"></i></span>
                                <select class="form-select form-select-sm" v-model="filterForm.outlet_id" @change="applyFilters">
                                    <option value="">Semua Outlet</option>
                                    <option v-for="ot in outlets" :key="ot.id" :value="ot.id">{{ ot.name }}</option>
                                </select>
                            </div>

                            <div class="input-group input-group-sm" style="max-width: 200px;">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text" class="form-control" v-model="filterForm.search" @keyup.enter="applyFilters" placeholder="Cari obat, ref, batch...">
                            </div>

                            <select class="form-select form-select-sm" style="width: 130px;" v-model="filterForm.date_preset" @change="applyFilters">
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
                                    <th>Outlet</th>
                                    <th>Obat</th>
                                    <th>Batch / Expired</th>
                                    <th>Jenis Transaksi</th>
                                    <th>No. Referensi</th>
                                    <th>Transaksi (Qty & Satuan)</th>
                                    <th class="text-end text-success">Masuk (+)</th>
                                    <th class="text-end text-danger">Keluar (-)</th>
                                    <th class="text-end fw-bold">Saldo Stok</th>
                                    <th>User</th>
                                    <th class="text-center" style="min-width: 90px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in movements.data" :key="m.id">
                                    <td class="small">{{ m.created_at }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1 font-monospace">
                                            <i class="bx bx-store text-primary"></i>
                                            {{ m.outlet_name || 'Outlet Utama' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div 
                                            class="fw-bold text-dark text-hover-primary" 
                                            style="cursor: pointer;"
                                            @click="openMedicineStockCard(m.medicine_id, m.medicine_name)"
                                            title="Klik untuk melihat detail kartu stok"
                                        >
                                            {{ m.medicine_name }}
                                        </div>
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
                                    <td class="text-center">
                                        <button 
                                            type="button" 
                                            class="btn btn-xs btn-sm btn-outline-primary fw-bold d-inline-flex align-items-center gap-1 shadow-xs"
                                            @click="openMedicineStockCard(m.medicine_id, m.medicine_name)"
                                            title="Lihat Detail Transaksi & Kartu Stok Obat"
                                        >
                                            <i class="bx bx-show fs-6"></i>
                                            <span>Detail</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!movements.data || movements.data.length === 0">
                                    <td colspan="12" class="text-center py-4 text-muted">Belum ada data pergerakan stok (Stock Movement) sesuai filter ini.</td>
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
                                <label class="form-label font-weight-bold">Filter Outlet / Cabang</label>
                                <select class="form-select" v-model="filterForm.outlet_id">
                                    <option value="">Semua Outlet</option>
                                    <option v-for="ot in outlets" :key="ot.id" :value="ot.id">{{ ot.name }}</option>
                                </select>
                            </div>
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

                            <div class="col-md-6">
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
                            <div class="col-md-4">
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
                            <div class="col-md-12">
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

        <!-- Modal Detail Kartu Stok & Transaksi Mutasi Obat Per Outlet -->
        <div v-if="showStockCardModal" class="modal fade show d-block" @click.self="showStockCardModal = false" tabindex="-1" style="background-color: rgba(15, 23, 42, 0.7); z-index: 1060;" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
                    <div class="modal-header bg-primary text-white py-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-white bg-opacity-25 p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                <i class="bx bx-history fs-5"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">Kartu Stok: {{ stockCardData?.medicine?.nama || 'Memuat Data...' }}</h5>
                                <div class="small text-white-50" style="font-size: 0.78rem;">Rincian Lengkap Penambahan (+) dan Pengurangan (-) Stok Berdasarkan Filter Outlet</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button v-if="stockCardData" class="btn btn-sm btn-light fw-bold" @click="printMedicineLedger">
                                <i class="bx bx-printer me-1"></i> Cetak Kartu Stok
                            </button>
                            <button type="button" class="btn-close btn-close-white" @click="showStockCardModal = false" aria-label="Close"></button>
                        </div>
                    </div>

                    <div class="modal-body p-4 bg-light">
                        <!-- Loading State -->
                        <div v-if="isStockCardLoading" class="text-center py-5">
                            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                            <h5 class="fw-bold text-dark">Memuat Data Transaksi Stok Obat...</h5>
                            <p class="text-muted small">Menghitung akumulasi transaksi penambahan, pengurangan, dan saldo mutasi outlet.</p>
                        </div>

                        <!-- Loaded State -->
                        <div v-else-if="stockCardData">
                            <!-- Outlet Switcher & Medicine Meta Card -->
                            <div class="card border-0 shadow-xs p-3 mb-3 rounded-3 bg-white">
                                <div class="row align-items-center g-3">
                                    <div class="col-md-7">
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <span class="badge bg-secondary font-monospace">{{ stockCardData.medicine.kode }}</span>
                                            <span class="badge bg-info text-dark">{{ stockCardData.medicine.kategori }}</span>
                                            <span class="badge bg-light text-dark border">Satuan: {{ stockCardData.medicine.satuan }}</span>
                                            <span class="badge bg-warning text-dark border">Min Stok: {{ stockCardData.medicine.min_stok }}</span>
                                        </div>
                                        <div class="text-muted small">
                                            Stok Fisik Outlet: <strong class="text-dark">{{ formatNumber(stockCardData.medicine.stok_outlet) }} {{ stockCardData.medicine.satuan }}</strong>
                                            <span class="mx-2 text-black-50">|</span>
                                            Total Seluruh Apotek: <strong class="text-dark">{{ formatNumber(stockCardData.medicine.stok_master) }} {{ stockCardData.medicine.satuan }}</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center justify-content-md-end gap-2">
                                            <label class="form-label fw-bold mb-0 text-nowrap small text-muted"><i class="bx bx-store me-1 text-primary"></i>Pilih Outlet:</label>
                                            <select class="form-select form-select-sm shadow-xs border-primary border-opacity-50" style="max-width: 220px;" v-model="stockCardModalOutlet" @change="changeModalOutlet(stockCardModalOutlet)">
                                                <option value="">Semua Outlet (Konsolidasi)</option>
                                                <option v-for="ot in outlets" :key="ot.id" :value="ot.id">{{ ot.name }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary Metrics Card -->
                            <div class="row g-3 mb-4">
                                <div class="col-6 col-md-3">
                                    <div class="card border-0 shadow-xs p-3 rounded-3 text-center bg-white h-100">
                                        <span class="text-muted small">Stok Awal Periode</span>
                                        <h4 class="fw-bold text-secondary mb-0 mt-1">{{ formatNumber(stockCardData.summary.opening_stock) }}</h4>
                                        <small class="text-muted" style="font-size: 0.7rem;">Saldo awal sebelum periode</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="card border-0 shadow-xs p-3 rounded-3 text-center bg-white h-100 border-start border-4 border-success">
                                        <span class="text-success small fw-bold">Total Penambahan (+)</span>
                                        <h4 class="fw-bold text-success mb-0 mt-1">+{{ formatNumber(stockCardData.summary.total_in) }}</h4>
                                        <small class="text-muted" style="font-size: 0.7rem;">Pembelian, Retur Jual, Bonus</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="card border-0 shadow-xs p-3 rounded-3 text-center bg-white h-100 border-start border-4 border-danger">
                                        <span class="text-danger small fw-bold">Total Pengurangan (-)</span>
                                        <h4 class="fw-bold text-danger mb-0 mt-1">-{{ formatNumber(stockCardData.summary.total_out) }}</h4>
                                        <small class="text-muted" style="font-size: 0.7rem;">POS Penjualan, Retur Beli, Rusak</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="card border-0 shadow-xs p-3 rounded-3 text-center bg-white h-100 border-start border-4 border-primary">
                                        <span class="text-primary small fw-bold">Saldo Akhir Mutasi</span>
                                        <h4 class="fw-bold text-primary mb-0 mt-1">{{ formatNumber(stockCardData.summary.closing_stock) }}</h4>
                                        <small class="text-muted" style="font-size: 0.7rem;">
                                            {{ stockCardData.outlet ? stockCardData.outlet.name : 'Seluruh Outlet' }}
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Search Filter Inside Modal -->
                            <div class="card border-0 shadow-xs p-3 mb-3 bg-white rounded-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="fw-bold text-dark mb-0"><i class="bx bx-list-ul me-1 text-primary"></i>Rincian Transaksi Masuk & Keluar</h6>
                                        <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25">{{ filteredModalLedger.length }} Data</span>
                                    </div>
                                    <div class="input-group input-group-sm" style="max-width: 260px;">
                                        <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                        <input type="text" class="form-control" v-model="stockCardSearch" placeholder="Cari ref, batch, keterangan...">
                                    </div>
                                </div>
                            </div>

                            <!-- Transaction Ledger Table -->
                            <div class="card border-0 shadow-xs rounded-3 bg-white mb-4 overflow-hidden">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tanggal & Jam</th>
                                                <th>Jenis Transaksi</th>
                                                <th>No. Referensi</th>
                                                <th>Batch & Exp</th>
                                                <th>Outlet</th>
                                                <th class="text-end text-success">Masuk (+)</th>
                                                <th class="text-end text-danger">Keluar (-)</th>
                                                <th class="text-end fw-bold">Saldo Berjalan</th>
                                                <th>Petugas & Keterangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="item in filteredModalLedger" :key="item.id">
                                                <td class="small">{{ item.created_at }}</td>
                                                <td>
                                                    <span class="badge" :class="{
                                                        'bg-success': item.movement_type === 'PURCHASE' || item.movement_type === 'BONUS',
                                                        'bg-primary': item.movement_type === 'SALE',
                                                        'bg-warning text-dark': item.movement_type === 'STOCK_OPNAME' || item.movement_type === 'PURCHASE_RETURN' || item.movement_type === 'SALE_RETURN',
                                                        'bg-danger': item.movement_type === 'DAMAGED' || item.movement_type === 'EXPIRED' || item.movement_type === 'LOST',
                                                        'bg-info text-dark': item.movement_type === 'TRANSFER_IN' || item.movement_type === 'TRANSFER_OUT' || item.movement_type === 'INITIAL_STOCK'
                                                    }">
                                                        {{ getMovementLabel(item.movement_type) }}
                                                    </span>
                                                </td>
                                                <td class="small font-monospace fw-bold text-primary">{{ item.reference_number || item.movement_number }}</td>
                                                <td class="small">
                                                    <div><span class="badge bg-light text-dark border">{{ item.batch_number || '-' }}</span></div>
                                                    <small class="text-muted" v-if="item.expired_date">Exp: {{ item.expired_date }}</small>
                                                </td>
                                                <td class="small">
                                                    <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1 font-monospace">
                                                        <i class="bx bx-store text-primary"></i>{{ item.outlet_name || 'Outlet Utama' }}
                                                    </span>
                                                </td>
                                                <td class="text-end fw-bold">
                                                    <span v-if="item.type === 'in'" class="text-success font-monospace">
                                                        +{{ formatNumber(item.quantity) }}
                                                    </span>
                                                    <span v-else class="text-muted">—</span>
                                                </td>
                                                <td class="text-end fw-bold">
                                                    <span v-if="item.type === 'out'" class="text-danger font-monospace">
                                                        -{{ formatNumber(item.quantity) }}
                                                    </span>
                                                    <span v-else class="text-muted">—</span>
                                                </td>
                                                <td class="text-end fw-bold fs-6 font-monospace" :class="item.calculated_running_balance < 0 ? 'text-danger' : 'text-primary'">
                                                    {{ formatNumber(item.calculated_running_balance) }}
                                                </td>
                                                <td class="small">
                                                    <div class="fw-semibold text-dark">{{ item.user_name || 'System' }}</div>
                                                    <div class="text-muted" style="font-size: 0.75rem;" v-if="item.notes">{{ item.notes }}</div>
                                                    <div class="text-muted" style="font-size: 0.75rem;" v-if="item.supplier_name"><i class="bx bx-truck me-1"></i>{{ item.supplier_name }}</div>
                                                </td>
                                            </tr>
                                            <tr v-if="filteredModalLedger.length === 0">
                                                <td colspan="9" class="text-center py-4 text-muted">
                                                    Tidak ada catatan transaksi stok obat pada filter outlet dan periode ini.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Active Batches for Selected Outlet -->
                            <div v-if="stockCardData.batches && stockCardData.batches.length > 0" class="card border-0 shadow-xs p-3 bg-white mb-2 rounded-3">
                                <h6 class="fw-bold text-dark mb-2"><i class="bx bx-barcode me-1 text-success"></i>Batch Aktif Tersedia ({{ stockCardData.outlet ? stockCardData.outlet.name : 'Seluruh Cabang' }})</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Batch Number</th>
                                                <th>Tanggal Kadaluarsa</th>
                                                <th class="text-end">Sisa Stok Fisik</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="b in stockCardData.batches" :key="b.id">
                                                <td class="font-monospace fw-bold">{{ b.batch_number }}</td>
                                                <td>{{ b.expired_date }}</td>
                                                <td class="text-end fw-bold text-success">{{ formatNumber(b.stock) }} {{ stockCardData.medicine.satuan }}</td>
                                                <td>
                                                    <span class="badge bg-success">Tersedia</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-white border-top">
                        <button type="button" class="btn btn-secondary" @click="showStockCardModal = false">Tutup</button>
                        <button v-if="stockCardData" type="button" class="btn btn-primary fw-bold" @click="printMedicineLedger">
                            <i class="bx bx-printer me-1"></i> Cetak Kartu Stok
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden Element for Isolated Printing of Medicine Stock Card -->
        <div id="printableMedicineLedger" class="d-none" v-if="stockCardData">
            <div class="p-3">
                <!-- Kop Apotek -->
                <div class="d-flex justify-content-between align-items-center border-bottom-double pb-2 mb-3">
                    <div style="flex: 1;">
                        <h2 class="fw-bold text-uppercase text-dark mb-0 fs-5">
                            {{ stockCardData.settings?.pharmacy_name || 'APOTEK MANAGEMENT SYSTEM' }}
                        </h2>
                        <div class="small text-muted mb-0">
                            {{ stockCardData.settings?.pharmacy_address || 'Jl. Raya Farmasi No. 123' }}
                        </div>
                        <div class="small text-muted">
                            Telp: {{ stockCardData.settings?.pharmacy_phone || '-' }} | 
                            Apoteker: {{ stockCardData.settings?.pharmacist_name || '-' }} (SIPA: {{ stockCardData.settings?.pharmacist_license || '-' }})
                        </div>
                    </div>
                    <div class="text-end" style="min-width: 200px;">
                        <div class="fw-bold text-primary fs-5 text-uppercase">KARTU STOK OBAT</div>
                        <div class="small font-monospace">Dicetak: {{ new Date().toLocaleString('id-ID') }}</div>
                        <div class="small text-muted">Outlet: {{ stockCardData.outlet?.name || 'Semua Outlet' }}</div>
                    </div>
                </div>

                <!-- Info Obat -->
                <div class="border rounded p-2 mb-3 bg-light">
                    <div class="row">
                        <div class="col-6">
                            <div><strong>Kode Obat:</strong> {{ stockCardData.medicine.kode }}</div>
                            <div><strong>Nama Obat:</strong> {{ stockCardData.medicine.nama }}</div>
                            <div><strong>Kategori:</strong> {{ stockCardData.medicine.kategori }}</div>
                        </div>
                        <div class="col-6 text-end">
                            <div><strong>Stok Awal:</strong> {{ formatNumber(stockCardData.summary.opening_stock) }} {{ stockCardData.medicine.satuan }}</div>
                            <div><strong>Total Masuk (+):</strong> +{{ formatNumber(stockCardData.summary.total_in) }} {{ stockCardData.medicine.satuan }}</div>
                            <div><strong>Total Keluar (-):</strong> -{{ formatNumber(stockCardData.summary.total_out) }} {{ stockCardData.medicine.satuan }}</div>
                            <div><strong>Saldo Akhir:</strong> <strong>{{ formatNumber(stockCardData.summary.closing_stock) }} {{ stockCardData.medicine.satuan }}</strong></div>
                        </div>
                    </div>
                </div>

                <!-- Tabel Mutasi Printable -->
                <table class="table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 30px;">No</th>
                            <th style="width: 120px;">Tanggal & Jam</th>
                            <th style="width: 90px;">Jenis</th>
                            <th style="width: 130px;">No. Referensi</th>
                            <th style="width: 110px;">Batch / Exp</th>
                            <th style="width: 90px;">Outlet</th>
                            <th class="text-end" style="width: 75px;">Masuk (+)</th>
                            <th class="text-end" style="width: 75px;">Keluar (-)</th>
                            <th class="text-end" style="width: 75px;">Saldo</th>
                            <th>Keterangan / User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in stockCardData.ledger" :key="item.id">
                            <td class="text-center">{{ idx + 1 }}</td>
                            <td>{{ item.created_at }}</td>
                            <td>{{ getMovementLabel(item.movement_type) }}</td>
                            <td class="font-monospace fw-bold">{{ item.reference_number || item.movement_number }}</td>
                            <td>{{ item.batch_number || '-' }} <span v-if="item.expired_date">({{ item.expired_date }})</span></td>
                            <td>{{ item.outlet_name || 'Utama' }}</td>
                            <td class="text-end fw-bold text-success">{{ item.type === 'in' ? '+' + formatNumber(item.quantity) : '-' }}</td>
                            <td class="text-end fw-bold text-danger">{{ item.type === 'out' ? '-' + formatNumber(item.quantity) : '-' }}</td>
                            <td class="text-end fw-bold">{{ formatNumber(item.calculated_running_balance) }}</td>
                            <td>{{ item.notes || '-' }} ({{ item.user_name || 'System' }})</td>
                        </tr>
                    </tbody>
                </table>

                <div class="row mt-4 pt-3">
                    <div class="col-4 text-center">
                        <div>Petugas Farmasi,</div>
                        <div style="height: 50px;"></div>
                        <div>( ......................................... )</div>
                    </div>
                    <div class="col-4"></div>
                    <div class="col-4 text-center">
                        <div>Apoteker Penanggung Jawab,</div>
                        <div style="height: 50px;"></div>
                        <div>( {{ stockCardData.settings?.pharmacist_name || '.........................................' }} )</div>
                    </div>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
