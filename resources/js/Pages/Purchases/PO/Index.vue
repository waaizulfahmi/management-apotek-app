<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    orders: Object,
    suppliers: Array,
    metrics: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const supplierFilter = ref(props.filters?.supplier_id || '');

const handleFilter = () => {
    router.get(route('purchases.po.index'), {
        search: search.value,
        status: statusFilter.value,
        supplier_id: supplierFilter.value,
    }, { preserveState: true, replace: true });
};

const resetFilter = () => {
    search.value = '';
    statusFilter.value = '';
    supplierFilter.value = '';
    handleFilter();
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const getStatusBadge = (status) => {
    const s = (status || '').toUpperCase();
    switch (s) {
        case 'DRAFT':
            return { class: 'status-draft', label: 'DRAFT', icon: 'bx-edit' };
        case 'WAITING_APPROVAL':
            return { class: 'status-waiting', label: 'WAITING APPROVAL', icon: 'bx-time-five' };
        case 'APPROVED':
            return { class: 'status-approved', label: 'APPROVED', icon: 'bx-check' };
        case 'SENT':
            return { class: 'status-sent', label: 'SENT (DIKIRIM)', icon: 'bx-send' };
        case 'PARTIAL_RECEIVED':
            return { class: 'status-partial', label: 'SEBAGIAN DITERIMA', icon: 'bx-package' };
        case 'RECEIVED':
            return { class: 'status-received', label: 'RECEIVED (SELESAI)', icon: 'bx-check-double' };
        case 'CANCELLED':
            return { class: 'status-cancelled', label: 'CANCELLED', icon: 'bx-x' };
        default:
            return { class: 'status-draft', label: s || 'UNKNOWN', icon: 'bx-question-mark' };
    }
};

const cancelPO = (po) => {
    Swal.fire({
        title: 'Batalkan Purchase Order ini?',
        text: `PO ${po.po_number} akan dibatalkan.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Batalkan PO',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b'
    }).then((res) => {
        if (res.isConfirmed) {
            router.post(route('purchases.po.cancel', po.id));
        }
    });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4 purchase-orders-module">
            <div class="content mt-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge-module-tag">
                                <i class="bx bx-cart-download me-1"></i> Surat Pesanan (SP)
                            </span>
                        </div>
                        <h2 class="fw-bold page-title mb-1">
                            <i class="bx bx-cart-download text-primary me-2"></i>Purchase Order (PO) ke PBF
                        </h2>
                        <p class="page-subtitle mb-0">
                            Pencatatan Surat Pesanan & Pengadaan Obat ke PBF/Supplier.
                        </p>
                    </div>

                    <div class="d-flex gap-2">
                        <Link :href="route('purchases.invoices.index')" class="btn btn-nav-secondary fw-semibold">
                            <i class="bx bx-receipt me-1"></i> Faktur Pembelian (Stok)
                        </Link>
                        <Link :href="route('purchases.po.create')" class="btn btn-primary shadow-sm fw-bold px-3">
                            <i class="bx bx-plus me-1"></i> Buat PO Baru
                        </Link>
                    </div>
                </div>

                <!-- ALERT NOTICE: PO TIDAK MEMPENGARUHI STOK -->
                <div class="alert-po-notice mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="notice-icon">
                            <i class="bx bx-shield-quarter"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                <h6 class="fw-bold text-white mb-0">Aturan Pengadaan Apotek: PO HANYA UNTUK PENDATAAN SURAT PESANAN</h6>
                                <span class="badge bg-warning text-dark fw-bold">TIDAK MEMPENGARUHI STOK</span>
                            </div>
                            <p class="text-slate-200 small mb-2">
                                Modul Purchase Order (PO) ini <strong>murni untuk mencatat & mencetak Surat Pesanan (SP) ke PBF</strong> dan <strong>TIDAK menambah stok fisik persediaan obat</strong> ketika disetujui (Approved) maupun selesai.
                                Penambahan stok fisik obat, pencatatan nomor batch, tanggal kadaluarsa (ED), dan mutasi kartu stok <strong>HANYA diproses saat pengisian & finalisasi di menu Faktur Pembelian</strong>.
                            </p>
                            <div class="d-flex gap-2 flex-wrap">
                                <Link :href="route('purchases.invoices.index')" class="btn btn-xs btn-outline-info text-white">
                                    <i class="bx bx-receipt me-1"></i> Buka Modul Faktur Pembelian
                                </Link>
                                <Link :href="route('purchases.invoices.create')" class="btn btn-xs btn-success text-white fw-bold">
                                    <i class="bx bx-plus-circle me-1"></i> Catat Faktur Masuk Stok
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Summary Metrics -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stat-po-card card-total h-100">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="stat-card-label">PO Bulan Ini</span>
                                    <h3 class="stat-card-value text-white mb-0 mt-1">{{ metrics.total_month || 0 }}</h3>
                                </div>
                                <div class="stat-icon-circle icon-blue">
                                    <i class="bx bx-calendar"></i>
                                </div>
                            </div>
                            <div class="stat-card-sub text-slate-300 mt-2">Periode Berjalan</div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stat-po-card card-waiting h-100">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="stat-card-label">Menunggu Approval</span>
                                    <h3 class="stat-card-value text-warning mb-0 mt-1">{{ metrics.waiting_approval || 0 }}</h3>
                                </div>
                                <div class="stat-icon-circle icon-amber">
                                    <i class="bx bx-time-five"></i>
                                </div>
                            </div>
                            <div class="stat-card-sub text-slate-300 mt-2">Butuh Persetujuan</div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stat-po-card card-approved h-100">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="stat-card-label">Disetujui (Approved)</span>
                                    <h3 class="stat-card-value text-info mb-0 mt-1">{{ metrics.approved || 0 }}</h3>
                                </div>
                                <div class="stat-icon-circle icon-cyan">
                                    <i class="bx bx-check"></i>
                                </div>
                            </div>
                            <div class="stat-card-sub text-slate-300 mt-2">Siap Kirim ke PBF</div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stat-po-card card-sent h-100">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="stat-card-label">Dikirim (Sent)</span>
                                    <h3 class="stat-card-value text-sky mb-0 mt-1">{{ metrics.sent || 0 }}</h3>
                                </div>
                                <div class="stat-icon-circle icon-indigo">
                                    <i class="bx bx-send"></i>
                                </div>
                            </div>
                            <div class="stat-card-sub text-slate-300 mt-2">Menunggu Pengiriman</div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stat-po-card card-partial h-100">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="stat-card-label">Sebagian Diterima</span>
                                    <h3 class="stat-card-value text-amber mb-0 mt-1">{{ metrics.partial_received || 0 }}</h3>
                                </div>
                                <div class="stat-icon-circle icon-orange">
                                    <i class="bx bx-package"></i>
                                </div>
                            </div>
                            <div class="stat-card-sub text-slate-300 mt-2">Parsial di Faktur</div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stat-po-card card-received h-100">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="stat-card-label">Selesai (Received)</span>
                                    <h3 class="stat-card-value text-emerald mb-0 mt-1">{{ metrics.received || 0 }}</h3>
                                </div>
                                <div class="stat-icon-circle icon-emerald">
                                    <i class="bx bx-check-double"></i>
                                </div>
                            </div>
                            <div class="stat-card-sub text-slate-300 mt-2">Lengkap di Faktur</div>
                        </div>
                    </div>
                </div>

                <!-- Table & Filter Bar Container -->
                <div class="po-table-container mb-4">
                    <!-- Filter Toolbar -->
                    <div class="po-toolbar p-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-md-4 col-lg-3">
                                <div class="input-group input-group-sm po-search-box">
                                    <span class="input-group-text"><i class="bx bx-search"></i></span>
                                    <input type="text" class="form-control" v-model="search" @keyup.enter="handleFilter" placeholder="Cari No. PO...">
                                </div>
                            </div>

                            <div class="col-6 col-md-4 col-lg-3">
                                <select class="form-select form-select-sm po-filter-select" v-model="supplierFilter" @change="handleFilter">
                                    <option value="">Semua PBF / Supplier</option>
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>

                            <div class="col-6 col-md-4 col-lg-3">
                                <select class="form-select form-select-sm po-filter-select" v-model="statusFilter" @change="handleFilter">
                                    <option value="">Semua Status PO</option>
                                    <option value="DRAFT">DRAFT</option>
                                    <option value="WAITING_APPROVAL">WAITING APPROVAL</option>
                                    <option value="APPROVED">APPROVED</option>
                                    <option value="SENT">SENT</option>
                                    <option value="PARTIAL_RECEIVED">PARTIAL RECEIVED</option>
                                    <option value="RECEIVED">RECEIVED</option>
                                </select>
                            </div>

                            <div class="col-12 col-lg-3 d-flex gap-2 justify-content-lg-end">
                                <button type="button" class="btn btn-sm btn-primary px-3 fw-bold" @click="handleFilter">
                                    <i class="bx bx-filter-alt me-1"></i> Filter
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary text-slate-300" @click="resetFilter" title="Reset Filter">
                                    <i class="bx bx-reset"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- PO Table -->
                    <div class="table-responsive">
                        <table class="table po-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 50px;">#</th>
                                    <th>No. PO</th>
                                    <th>Tanggal</th>
                                    <th>PBF / Supplier</th>
                                    <th class="text-center">Total Item</th>
                                    <th class="text-end">Total PO / Nilai</th>
                                    <th class="text-center">Status PO</th>
                                    <th>Dibuat Oleh</th>
                                    <th class="text-center" style="width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(po, idx) in orders.data" :key="po.id" class="po-row">
                                    <td class="text-center row-number">
                                        {{ (orders.current_page - 1) * orders.per_page + idx + 1 }}
                                    </td>
                                    <td>
                                        <Link :href="route('purchases.po.show', po.id)" class="po-link font-monospace">
                                            {{ po.po_number }}
                                        </Link>
                                    </td>
                                    <td>
                                        <div class="text-cell-primary fw-medium">{{ formatDate(po.order_date) }}</div>
                                    </td>
                                    <td>
                                        <div class="text-cell-white fw-bold">{{ po.supplier_name }}</div>
                                        <small class="text-cell-muted font-monospace">{{ po.supplier_code || '-' }}</small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-items-pill">{{ po.total_items }} Item</span>
                                    </td>
                                    <td class="text-end">
                                        <div v-if="Number(po.grand_total) > 0" class="fw-bold font-monospace text-cell-total">
                                            {{ formatCurrency(po.grand_total) }}
                                        </div>
                                        <span v-else class="badge bg-secondary-subtle text-slate-300 border border-secondary border-opacity-50 small">
                                            <i class="bx bx-receipt me-1"></i>Harga di Faktur
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span :class="['status-badge', getStatusBadge(po.status).class]">
                                            <i :class="['bx me-1', getStatusBadge(po.status).icon]"></i>
                                            {{ getStatusBadge(po.status).label }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-cell-primary small">{{ po.created_by_name || '-' }}</div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center align-items-center gap-1">
                                            <!-- Detail PO -->
                                            <Link :href="route('purchases.po.show', po.id)"
                                                class="btn-action-icon btn-action-view"
                                                title="Lihat Detail PO">
                                                <i class="bx bx-show"></i>
                                            </Link>

                                            <!-- Proses ke Faktur Pembelian (Masuk Stok) -->
                                            <Link v-if="po.status === 'APPROVED' || po.status === 'SENT' || po.status === 'PARTIAL_RECEIVED' || po.status === 'RECEIVED'"
                                                :href="route('purchases.invoices.create', { po_id: po.id })"
                                                class="btn-action-icon btn-action-invoice"
                                                title="Buat Faktur Pembelian (Input Batch & Masuk Stok)">
                                                <i class="bx bx-receipt"></i>
                                            </Link>

                                            <!-- Batalkan PO -->
                                            <button v-if="po.status === 'DRAFT' || po.status === 'WAITING_APPROVAL'"
                                                @click="cancelPO(po)"
                                                class="btn-action-icon btn-action-delete"
                                                title="Batalkan PO">
                                                <i class="bx bx-x"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!orders.data || orders.data.length === 0">
                                    <td colspan="9" class="text-center py-5">
                                        <div class="text-cell-muted">
                                            <i class="bx bx-file-blank fs-1 d-block mb-2 opacity-50"></i>
                                            Belum ada data Purchase Order (PO).
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="p-3 border-top border-secondary border-opacity-25 po-pagination">
                        <Pagination :links="orders.links" />
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>

<style scoped>
/* ============================================================ */
/* ULTRA HIGH CONTRAST DARK MODE THEME FOR PURCHASE ORDERS      */
/* ============================================================ */

.purchase-orders-module {
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

/* ============================================================ */
/* NOTICE BANNER: PO TIDAK MEMPENGARUHI STOK                    */
/* ============================================================ */
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

.btn-xs {
    padding: 4px 10px;
    font-size: 0.78rem;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

/* ============================================================ */
/* STAT CARDS                                                   */
/* ============================================================ */
.stat-po-card {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-po-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
}

:global(body:not(.dark-mode)) .stat-po-card {
    background: #ffffff;
    border-color: #e2e8f0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

:global(body:not(.dark-mode)) .stat-po-card:hover {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
}

.stat-card-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #cbd5e1;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: block;
}

:global(body:not(.dark-mode)) .stat-card-label {
    color: #64748b;
}

.stat-card-value {
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1.1;
}

:global(body:not(.dark-mode)) .stat-card-value.text-white {
    color: #0f172a !important;
}

.stat-card-sub {
    font-size: 0.72rem;
    color: #94a3b8;
}

.stat-icon-circle {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.icon-blue {
    background: rgba(59, 130, 246, 0.18);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.35);
}

.icon-amber {
    background: rgba(245, 158, 11, 0.18);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.35);
}

.icon-cyan {
    background: rgba(6, 182, 212, 0.18);
    color: #22d3ee;
    border: 1px solid rgba(6, 182, 212, 0.35);
}

.icon-indigo {
    background: rgba(99, 102, 241, 0.18);
    color: #818cf8;
    border: 1px solid rgba(99, 102, 241, 0.35);
}

.icon-orange {
    background: rgba(249, 115, 22, 0.18);
    color: #fb923c;
    border: 1px solid rgba(249, 115, 22, 0.35);
}

.icon-emerald {
    background: rgba(16, 185, 129, 0.18);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.35);
}

/* ============================================================ */
/* TABLE & TOOLBAR                                              */
/* ============================================================ */
.po-table-container {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
    overflow: hidden;
}

:global(body:not(.dark-mode)) .po-table-container {
    background: #ffffff;
    border-color: #e2e8f0;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.po-toolbar {
    background: #0f172a;
    border-bottom: 1px solid #334155;
}

:global(body:not(.dark-mode)) .po-toolbar {
    background: #f8fafc;
    border-bottom-color: #e2e8f0;
}

.po-search-box .input-group-text {
    background: #1e293b !important;
    border-color: #475569 !important;
    color: #94a3b8 !important;
}

.po-search-box .form-control {
    background: #1e293b !important;
    border-color: #475569 !important;
    color: #ffffff !important;
}

.po-search-box .form-control::placeholder {
    color: #94a3b8 !important;
}

.po-filter-select {
    background-color: #1e293b !important;
    border-color: #475569 !important;
    color: #ffffff !important;
}

:global(body:not(.dark-mode)) .po-search-box .input-group-text,
:global(body:not(.dark-mode)) .po-search-box .form-control,
:global(body:not(.dark-mode)) .po-filter-select {
    background-color: #ffffff !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}

.po-table thead th {
    background-color: #0f172a !important;
    color: #f8fafc !important;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px solid #475569 !important;
    padding: 12px 10px !important;
    white-space: nowrap;
}

:global(body:not(.dark-mode)) .po-table thead th {
    background-color: #f1f5f9 !important;
    color: #334155 !important;
    border-bottom-color: #cbd5e1 !important;
}

.po-row td {
    background-color: #1e293b !important;
    border-bottom: 1px solid #334155 !important;
    padding: 12px 10px !important;
}

.po-row:hover td {
    background-color: #273549 !important;
}

:global(body:not(.dark-mode)) .po-row td {
    background-color: #ffffff !important;
    border-bottom-color: #f1f5f9 !important;
}

:global(body:not(.dark-mode)) .po-row:hover td {
    background-color: #f8fafc !important;
}

.row-number {
    color: #94a3b8;
    font-size: 0.82rem;
    font-weight: 600;
}

.po-link {
    color: #38bdf8 !important;
    font-weight: 700;
    font-size: 0.92rem;
    text-decoration: none;
    transition: color 0.15s ease;
}

.po-link:hover {
    color: #7dd3fc !important;
    text-decoration: underline;
}

.text-cell-primary {
    color: #f8fafc !important;
    font-size: 0.85rem;
}

:global(body:not(.dark-mode)) .text-cell-primary {
    color: #0f172a !important;
}

.text-cell-white {
    color: #ffffff !important;
    font-size: 0.9rem;
}

:global(body:not(.dark-mode)) .text-cell-white {
    color: #0f172a !important;
}

.text-cell-muted {
    color: #cbd5e1 !important;
}

:global(body:not(.dark-mode)) .text-cell-muted {
    color: #64748b !important;
}

.text-cell-total {
    color: #38bdf8 !important;
    font-size: 0.92rem;
}

:global(body:not(.dark-mode)) .text-cell-total {
    color: #0284c7 !important;
}

.badge-items-pill {
    background: rgba(148, 163, 184, 0.2);
    color: #f1f5f9 !important;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* ============================================================ */
/* STATUS BADGES                                                */
/* ============================================================ */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.status-draft {
    background: rgba(148, 163, 184, 0.2);
    color: #cbd5e1;
    border: 1px solid rgba(148, 163, 184, 0.4);
}

.status-waiting {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.45);
}

.status-approved {
    background: rgba(6, 182, 212, 0.2);
    color: #22d3ee;
    border: 1px solid rgba(6, 182, 212, 0.45);
}

.status-sent {
    background: rgba(99, 102, 241, 0.2);
    color: #818cf8;
    border: 1px solid rgba(99, 102, 241, 0.45);
}

.status-partial {
    background: rgba(249, 115, 22, 0.2);
    color: #fb923c;
    border: 1px solid rgba(249, 115, 22, 0.45);
}

.status-received {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.45);
}

.status-cancelled {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.45);
}

/* ============================================================ */
/* ACTION BUTTONS                                               */
/* ============================================================ */
.btn-action-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    border: 1px solid transparent;
    transition: all 0.18s ease;
    cursor: pointer;
    text-decoration: none;
}

.btn-action-icon:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
}

.btn-action-view {
    background: rgba(56, 189, 248, 0.18);
    color: #38bdf8 !important;
    border-color: rgba(56, 189, 248, 0.4);
}
.btn-action-view:hover {
    background: #0284c7;
    color: #ffffff !important;
    border-color: #0284c7;
}

.btn-action-invoice {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399 !important;
    border-color: rgba(16, 185, 129, 0.45);
}
.btn-action-invoice:hover {
    background: #10b981;
    color: #ffffff !important;
    border-color: #10b981;
}

.btn-action-delete {
    background: rgba(239, 68, 68, 0.18);
    color: #f87171 !important;
    border-color: rgba(239, 68, 68, 0.4);
}
.btn-action-delete:hover {
    background: #dc2626;
    color: #ffffff !important;
    border-color: #dc2626;
}

.po-pagination {
    background: #0f172a;
}

:global(body:not(.dark-mode)) .po-pagination {
    background: #f8fafc;
}
</style>
