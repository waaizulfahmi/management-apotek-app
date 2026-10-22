<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    invoices: Object,
    metrics: Object,
    suppliers: Array,
    outlets: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const paymentStatusFilter = ref(props.filters?.payment_status || '');
const supplierFilter = ref(props.filters?.supplier_id || '');
const outletFilter = ref(props.filters?.outlet_id || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

const handleFilter = () => {
    router.get(route('purchases.invoices.index'), {
        search: search.value,
        status: statusFilter.value,
        payment_status: paymentStatusFilter.value,
        supplier_id: supplierFilter.value,
        outlet_id: outletFilter.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
    }, { preserveState: true, replace: true });
};

const resetFilter = () => {
    search.value = '';
    statusFilter.value = '';
    paymentStatusFilter.value = '';
    supplierFilter.value = '';
    outletFilter.value = '';
    dateFrom.value = '';
    dateTo.value = '';
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

// Konfirmasi Finalisasi dari tabel
const confirmFinalize = (invoice) => {
    Swal.fire({
        title: 'Finalisasi Faktur Pembelian?',
        html: `
            <div class="text-start small">
                <p class="mb-1"><strong>No. Faktur:</strong> ${invoice.invoice_number || invoice.receipt_number}</p>
                <p class="mb-1"><strong>Supplier:</strong> ${invoice.supplier?.name || '-'}</p>
                <p class="mb-1"><strong>Total Nilai:</strong> ${formatCurrency(invoice.grand_total)}</p>
                <p class="mb-1"><strong>Outlet Tujuan:</strong> ${invoice.outlet?.name || '-'}</p>
                <hr class="my-2"/>
                <div class="alert alert-info py-2 px-3 mb-0 small">
                    <i class="bx bx-info-circle me-1"></i> Finalisasi akan <strong>langsung menambah stok persediaan</strong> di outlet tujuan, mencatat mutasi kartu stok, dan memperbarui hutang usaha.
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bx bx-check-double me-1"></i> Ya, Finalisasi Sekarang',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('purchases.invoices.finalize', invoice.id), {}, {
                preserveScroll: true,
            });
        }
    });
};

// Konfirmasi Hapus Draft
const confirmDeleteDraft = (invoice) => {
    Swal.fire({
        title: 'Hapus Draft Faktur?',
        text: `Draft faktur ${invoice.receipt_number} akan dihapus secara permanen.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus Draft',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('purchases.invoices.destroy', invoice.id), {
                preserveScroll: true,
            });
        }
    });
};

// Cetak via popup window
const openPrintPopup = (invoiceId, type = 'invoice') => {
    const url = route('purchases.invoices.print', invoiceId) + `?type=${type}`;
    const width = 1000;
    const height = 850;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;
    window.open(url, 'PrintInvoicePopup', `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes,status=no,toolbar=no,menubar=no`);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4 purchase-invoices-module">
            <div class="content mt-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge-module-tag">
                                <i class="bx bx-receipt me-1"></i> Farmasi & Logistik
                            </span>
                        </div>
                        <h2 class="fw-bold page-title mb-1">
                            <i class="bx bx-receipt text-primary me-2"></i>Faktur Pembelian Farmasi
                        </h2>
                        <p class="page-subtitle mb-0">
                            Pencatatan faktur supplier/PBF, penerimaan fisik obat, konversi satuan, dan pembaruan stok otomatis.
                        </p>
                    </div>

                    <div class="d-flex gap-2">
                        <Link :href="route('purchases.index')" class="btn btn-nav-secondary fw-semibold">
                            <i class="bx bx-cart-download me-1"></i> Riwayat PO
                        </Link>
                        <Link :href="route('purchases.invoices.create')" class="btn btn-primary shadow-sm fw-bold px-3">
                            <i class="bx bx-plus me-1"></i> Catat Faktur Baru
                        </Link>
                    </div>
                </div>

                <!-- Dashboard Summary Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stat-invoice-card card-total h-100">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="stat-card-label">Total Faktur</span>
                                    <h3 class="stat-card-value text-white mb-0 mt-1">{{ metrics.total_invoices }}</h3>
                                </div>
                                <div class="stat-icon-circle icon-blue">
                                    <i class="bx bx-file"></i>
                                </div>
                            </div>
                            <div class="stat-card-sub text-slate-300 mt-2">Semua Transaksi</div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stat-invoice-card card-draft h-100">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="stat-card-label">Draft (Belum Final)</span>
                                    <h3 class="stat-card-value text-amber mb-0 mt-1">{{ metrics.total_draft }}</h3>
                                </div>
                                <div class="stat-icon-circle icon-amber">
                                    <i class="bx bx-time-five"></i>
                                </div>
                            </div>
                            <span class="badge-status-draft mt-2 d-inline-block">
                                <i class="bx bx-pause me-0.5"></i> Stok Belum Masuk
                            </span>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stat-invoice-card card-final h-100">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="stat-card-label">Final (Stok Masuk)</span>
                                    <h3 class="stat-card-value text-emerald mb-0 mt-1">{{ metrics.total_final }}</h3>
                                </div>
                                <div class="stat-icon-circle icon-emerald">
                                    <i class="bx bx-check-shield"></i>
                                </div>
                            </div>
                            <span class="badge-status-final mt-2 d-inline-block">
                                <i class="bx bx-check-double me-0.5"></i> Stok Sudah Terupdate
                            </span>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="stat-invoice-card card-cancelled h-100">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="stat-card-label">Dibatalkan</span>
                                    <h3 class="stat-card-value text-rose mb-0 mt-1">{{ metrics.total_cancelled }}</h3>
                                </div>
                                <div class="stat-icon-circle icon-rose">
                                    <i class="bx bx-x-circle"></i>
                                </div>
                            </div>
                            <span class="badge-status-cancelled mt-2 d-inline-block">
                                <i class="bx bx-redo me-0.5"></i> Reversal Mutasi
                            </span>
                        </div>
                    </div>

                    <div class="col-12 col-md-8 col-xl-4">
                        <div class="stat-invoice-card card-financial h-100 p-3">
                            <div class="row g-2 h-100 align-items-center">
                                <div class="col-6 border-end border-slate-700 pe-3">
                                    <span class="stat-card-label">Total Nilai Final</span>
                                    <h5 class="fw-bold text-cyan mb-0 mt-1">{{ formatCurrency(metrics.total_final_amount) }}</h5>
                                    <small class="text-slate-300 d-block mt-0.5 font-11">Akumulasi pembelian sah</small>
                                </div>
                                <div class="col-6 ps-3">
                                    <span class="stat-card-label">Sisa Hutang PBF</span>
                                    <h5 class="fw-bold text-rose mb-0 mt-1">{{ formatCurrency(metrics.total_unpaid_amount) }}</h5>
                                    <small class="text-slate-300 d-block mt-0.5 font-11">Belum lunas / jatuh tempo</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Invoices Container Card -->
                <div class="invoice-main-card rounded-3 overflow-hidden shadow-sm">
                    <!-- Filter Toolbar -->
                    <div class="invoice-toolbar p-3 border-bottom">
                        <div class="row g-2 align-items-center">
                            <!-- Search Input -->
                            <div class="col-12 col-md-3">
                                <div class="input-group input-group-sm custom-input-group">
                                    <span class="input-group-text"><i class="bx bx-search text-cyan"></i></span>
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        v-model="search" 
                                        @keyup.enter="handleFilter" 
                                        placeholder="No Faktur, Penerimaan, DO, Supplier..."
                                    >
                                </div>
                            </div>

                            <!-- Supplier Filter -->
                            <div class="col-6 col-md-2">
                                <select class="form-select form-select-sm" v-model="supplierFilter" @change="handleFilter">
                                    <option value="">Semua Supplier (PBF)</option>
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>

                            <!-- Outlet Filter -->
                            <div class="col-6 col-md-2" v-if="outlets && outlets.length > 1">
                                <select class="form-select form-select-sm" v-model="outletFilter" @change="handleFilter">
                                    <option value="">Semua Outlet</option>
                                    <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
                                </select>
                            </div>

                            <!-- Status Faktur Filter -->
                            <div class="col-6 col-md-2">
                                <select class="form-select form-select-sm" v-model="statusFilter" @change="handleFilter">
                                    <option value="">Status: Semua</option>
                                    <option value="DRAFT">DRAFT (Belum Stok)</option>
                                    <option value="FINAL">FINAL (Stok Masuk)</option>
                                    <option value="CANCELLED">DIBATALKAN</option>
                                </select>
                            </div>

                            <!-- Payment Status Filter -->
                            <div class="col-6 col-md-2">
                                <select class="form-select form-select-sm" v-model="paymentStatusFilter" @change="handleFilter">
                                    <option value="">Bayar: Semua</option>
                                    <option value="unpaid">Belum Dibayar</option>
                                    <option value="partial">Dibayar Sebagian</option>
                                    <option value="paid">Lunas</option>
                                </select>
                            </div>

                            <!-- Actions -->
                            <div class="col-12 col-md-auto ms-auto d-flex gap-2">
                                <button class="btn btn-sm btn-primary px-3 fw-bold" @click="handleFilter" title="Terapkan Filter">
                                    <i class="bx bx-filter-alt me-1"></i> Filter
                                </button>
                                <button class="btn btn-sm btn-nav-secondary" @click="resetFilter" title="Reset Filter">
                                    <i class="bx bx-reset"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Date Range Row -->
                        <div class="row g-2 align-items-center mt-2 pt-2 border-top border-slate-700">
                            <div class="col-auto">
                                <span class="filter-label"><i class="bx bx-calendar me-1 text-cyan"></i>Tgl Terima:</span>
                            </div>
                            <div class="col-auto">
                                <input type="date" class="form-control form-control-sm date-input" v-model="dateFrom" @change="handleFilter" title="Dari Tanggal">
                            </div>
                            <div class="col-auto">
                                <span class="text-slate-300 small">s/d</span>
                            </div>
                            <div class="col-auto">
                                <input type="date" class="form-control form-control-sm date-input" v-model="dateTo" @change="handleFilter" title="Sampai Tanggal">
                            </div>
                            <div class="col-auto ms-auto">
                                <span class="filter-count">Total Ditemukan: <strong class="text-white">{{ invoices.total || 0 }}</strong> Faktur</span>
                            </div>
                        </div>
                    </div>

                    <!-- Invoices Table -->
                    <div class="table-responsive">
                        <table class="table invoice-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3" style="width: 45px;">#</th>
                                    <th style="min-width: 180px;">FAKTUR & PENERIMAAN</th>
                                    <th style="min-width: 130px;">TANGGAL</th>
                                    <th style="min-width: 200px;">SUPPLIER (PBF)</th>
                                    <th style="min-width: 140px;">OUTLET TUJUAN</th>
                                    <th class="text-center" style="width: 80px;">ITEM</th>
                                    <th class="text-end" style="min-width: 140px;">TOTAL FAKTUR</th>
                                    <th class="text-center" style="min-width: 140px;">STATUS STOK</th>
                                    <th class="text-center" style="min-width: 120px;">PEMBAYARAN</th>
                                    <th class="text-end pe-3" style="min-width: 160px;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(inv, idx) in invoices.data" :key="inv.id" class="invoice-row">
                                    <td class="ps-3 row-number">
                                        {{ (invoices.from || 1) + idx }}
                                    </td>
                                    <td>
                                        <div class="fw-bold invoice-number-cell">
                                            <Link :href="route('purchases.invoices.show', inv.id)" class="invoice-link">
                                                {{ inv.invoice_number || inv.receipt_number }}
                                            </Link>
                                        </div>
                                        <div class="receipt-sub-badge font-monospace">
                                            {{ inv.receipt_number }}
                                            <span v-if="inv.delivery_order_number" class="badge-do ms-1">DO: {{ inv.delivery_order_number }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-cell-primary">{{ formatDate(inv.received_date || inv.purchase_date) }}</div>
                                        <div class="text-cell-muted font-11">Faktur: {{ formatDate(inv.purchase_date) }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-cell-primary">{{ inv.supplier?.name || '-' }}</div>
                                        <div class="text-cell-muted font-11">Kode: {{ inv.supplier?.code || '-' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge-outlet-pill">
                                            <i class="bx bx-store-alt me-1"></i>{{ inv.outlet?.name || 'Gudang Pusat' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-items-pill">
                                            {{ inv.items_count || 0 }} Obat
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-bold text-cell-white font-15">{{ formatCurrency(inv.grand_total) }}</div>
                                        <div v-if="inv.remaining_amount > 0" class="text-rose fw-semibold font-12">
                                            Sisa: {{ formatCurrency(inv.remaining_amount) }}
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span v-if="inv.status?.toUpperCase() === 'FINAL' || inv.status?.toUpperCase() === 'RECEIVED'" class="badge-status-final">
                                            <i class="bx bx-check-double me-1"></i>STOK MASUK
                                        </span>
                                        <span v-else-if="inv.status?.toUpperCase() === 'CANCELLED'" class="badge-status-cancelled">
                                            <i class="bx bx-x me-1"></i>DIBATALKAN
                                        </span>
                                        <span v-else class="badge-status-draft">
                                            <i class="bx bx-time me-1"></i>DRAFT
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span v-if="inv.payment_status === 'paid'" class="badge-pay-paid">
                                            Lunas
                                        </span>
                                        <span v-else-if="inv.payment_status === 'partial'" class="badge-pay-partial">
                                            Sebagian
                                        </span>
                                        <span v-else class="badge-pay-unpaid">
                                            Belum Dibayar
                                        </span>
                                        <div class="payment-type-sub">
                                            {{ inv.payment_type ? inv.payment_type.toUpperCase() : 'KREDIT' }}
                                        </div>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1.5 align-items-center">
                                            <!-- Detail Button -->
                                            <Link :href="route('purchases.invoices.show', inv.id)" class="btn-action-icon btn-action-view" title="Lihat Detail & Preview">
                                                <i class="bx bx-show"></i>
                                            </Link>

                                            <!-- Edit Draft -->
                                            <Link 
                                                v-if="inv.status?.toUpperCase() === 'DRAFT'" 
                                                :href="route('purchases.invoices.edit', inv.id)" 
                                                class="btn-action-icon btn-action-edit" 
                                                title="Edit Draft"
                                            >
                                                <i class="bx bx-edit"></i>
                                            </Link>

                                            <!-- Finalize Button for Draft -->
                                            <button 
                                                v-if="inv.status?.toUpperCase() === 'DRAFT'" 
                                                type="button" 
                                                class="btn-action-icon btn-action-finalize" 
                                                @click="confirmFinalize(inv)" 
                                                title="Finalisasi Faktur (Tambah Stok)"
                                            >
                                                <i class="bx bx-check-double"></i>
                                            </button>

                                            <!-- Print Options for Final/Draft (Popup) -->
                                            <button 
                                                type="button" 
                                                class="btn-action-icon btn-action-print" 
                                                @click="openPrintPopup(inv.id)"
                                                title="Cetak Faktur (Popup)"
                                            >
                                                <i class="bx bx-printer"></i>
                                            </button>

                                            <!-- Delete Draft -->
                                            <button 
                                                v-if="inv.status?.toUpperCase() === 'DRAFT'" 
                                                type="button" 
                                                class="btn-action-icon btn-action-delete" 
                                                @click="confirmDeleteDraft(inv)" 
                                                title="Hapus Draft"
                                            >
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="!invoices.data || invoices.data.length === 0">
                                    <td colspan="10" class="text-center py-5">
                                        <div class="empty-state-box text-slate-300">
                                            <i class="bx bx-receipt fs-1 d-block mb-2 text-cyan opacity-75"></i>
                                            <h6 class="fw-semibold text-white">Belum Ada Faktur Pembelian</h6>
                                            <p class="small text-slate-300 mb-3">Tidak ada data faktur pembelian yang sesuai dengan filter pencarian.</p>
                                            <Link :href="route('purchases.invoices.create')" class="btn btn-sm btn-primary">
                                                <i class="bx bx-plus me-1"></i> Catat Faktur Baru
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="p-3 border-top border-slate-700 invoice-pagination" v-if="invoices.links">
                        <Pagination 
                            :links="invoices.links" 
                            :from="invoices.from" 
                            :to="invoices.to" 
                            :total="invoices.total" 
                        />
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>

<style scoped>
/* ============================================================ */
/* ULTRA HIGH-CONTRAST & ACCESSIBLE STYLING (DARK & LIGHT)      */
/* ============================================================ */

/* Typography & Titles */
.badge-module-tag {
    display: inline-flex;
    align-items: center;
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.4);
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 9999px;
    letter-spacing: 0.02em;
}

.page-title {
    color: #f8fafc !important;
    font-size: 1.6rem;
    letter-spacing: -0.02em;
}

.page-subtitle {
    color: #94a3b8 !important;
    font-size: 0.88rem;
}

:global(body:not(.dark-mode)) .page-title {
    color: #0f172a !important;
}

:global(body:not(.dark-mode)) .page-subtitle {
    color: #475569 !important;
}

/* Nav Buttons */
.btn-nav-secondary {
    background: #1e293b;
    color: #e2e8f0 !important;
    border: 1px solid #475569;
    border-radius: 8px;
    padding: 7px 14px;
    transition: all 0.2s ease;
}

.btn-nav-secondary:hover {
    background: #334155;
    color: #ffffff !important;
    border-color: #64748b;
}

:global(body:not(.dark-mode)) .btn-nav-secondary {
    background: #ffffff;
    color: #334155 !important;
    border-color: #cbd5e1;
}

/* ============================================================ */
/* DASHBOARD SUMMARY CARDS                                      */
/* ============================================================ */
.stat-invoice-card {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    transition: transform 0.2s ease, border-color 0.2s ease;
}

.stat-invoice-card:hover {
    transform: translateY(-2px);
    border-color: #475569;
}

:global(body:not(.dark-mode)) .stat-invoice-card {
    background: #ffffff;
    border-color: #e2e8f0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.card-total { border-left: 4px solid #3b82f6 !important; }
.card-draft { border-left: 4px solid #f59e0b !important; }
.card-final { border-left: 4px solid #10b981 !important; }
.card-cancelled { border-left: 4px solid #ef4444 !important; }
.card-financial { border-left: 4px solid #06b6d4 !important; }

.stat-card-label {
    font-size: 0.74rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #cbd5e1;
}

:global(body:not(.dark-mode)) .stat-card-label {
    color: #64748b;
}

.stat-card-value {
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1.2;
}

.stat-card-sub {
    font-size: 0.72rem;
}

.stat-icon-circle {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.icon-blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
.icon-amber { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
.icon-emerald { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.icon-rose { background: rgba(239, 68, 68, 0.2); color: #f87171; }

.text-white { color: #ffffff !important; }
.text-amber { color: #fbbf24 !important; }
.text-emerald { color: #34d399 !important; }
.text-rose { color: #f87171 !important; }
.text-cyan { color: #38bdf8 !important; }
.text-slate-300 { color: #cbd5e1 !important; }
.font-11 { font-size: 0.72rem; }
.font-12 { font-size: 0.78rem; }
.font-15 { font-size: 0.95rem; }

/* Status Badges */
.badge-status-draft {
    background: rgba(245, 158, 11, 0.22);
    color: #fde68a !important;
    border: 1px solid rgba(245, 158, 11, 0.45);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    letter-spacing: 0.02em;
}

.badge-status-final {
    background: rgba(16, 185, 129, 0.22);
    color: #6ee7b7 !important;
    border: 1px solid rgba(16, 185, 129, 0.45);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    letter-spacing: 0.02em;
}

.badge-status-cancelled {
    background: rgba(239, 68, 68, 0.22);
    color: #fca5a5 !important;
    border: 1px solid rgba(239, 68, 68, 0.45);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    letter-spacing: 0.02em;
}

/* Payment Status Badges */
.badge-pay-paid {
    background: rgba(16, 185, 129, 0.22);
    color: #6ee7b7 !important;
    border: 1px solid rgba(16, 185, 129, 0.4);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-block;
}

.badge-pay-partial {
    background: rgba(245, 158, 11, 0.22);
    color: #fde68a !important;
    border: 1px solid rgba(245, 158, 11, 0.4);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-block;
}

.badge-pay-unpaid {
    background: rgba(239, 68, 68, 0.22);
    color: #fca5a5 !important;
    border: 1px solid rgba(239, 68, 68, 0.4);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-block;
}

.payment-type-sub {
    font-size: 0.68rem;
    color: #cbd5e1;
    margin-top: 2px;
}

/* ============================================================ */
/* INVOICE TABLE & CONTAINER                                    */
/* ============================================================ */
.invoice-main-card {
    background: #1e293b;
    border: 1px solid #334155;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

:global(body:not(.dark-mode)) .invoice-main-card {
    background: #ffffff;
    border-color: #e2e8f0;
}

.invoice-toolbar {
    background: #0f172a;
    border-color: #334155 !important;
}

:global(body:not(.dark-mode)) .invoice-toolbar {
    background: #f8fafc;
    border-color: #e2e8f0 !important;
}

.filter-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #cbd5e1;
}

:global(body:not(.dark-mode)) .filter-label {
    color: #475569;
}

.filter-count {
    font-size: 0.82rem;
    color: #cbd5e1;
}

/* Table Design */
.invoice-table {
    border-color: #334155 !important;
}

.invoice-table thead th {
    background-color: #0f172a !important;
    color: #f8fafc !important;
    font-weight: 700 !important;
    font-size: 0.75rem !important;
    letter-spacing: 0.05em !important;
    padding: 12px 10px !important;
    border-bottom: 2px solid #334155 !important;
    border-top: none !important;
}

:global(body:not(.dark-mode)) .invoice-table thead th {
    background-color: #f1f5f9 !important;
    color: #1e293b !important;
    border-bottom-color: #cbd5e1 !important;
}

.invoice-row td {
    background-color: #1e293b !important;
    border-bottom: 1px solid #334155 !important;
    padding: 12px 10px !important;
}

.invoice-row:hover td {
    background-color: #273549 !important;
}

:global(body:not(.dark-mode)) .invoice-row td {
    background-color: #ffffff !important;
    border-bottom-color: #f1f5f9 !important;
}

:global(body:not(.dark-mode)) .invoice-row:hover td {
    background-color: #f8fafc !important;
}

.row-number {
    color: #94a3b8;
    font-size: 0.82rem;
    font-weight: 600;
}

.invoice-link {
    color: #38bdf8 !important;
    font-weight: 700;
    font-size: 0.92rem;
    text-decoration: none;
    transition: color 0.15s ease;
}

.invoice-link:hover {
    color: #7dd3fc !important;
    text-decoration: underline;
}

.receipt-sub-badge {
    color: #cbd5e1;
    font-size: 0.76rem;
    margin-top: 2px;
}

.badge-do {
    background: rgba(148, 163, 184, 0.2);
    color: #cbd5e1;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 4px;
    padding: 1px 4px;
    font-size: 0.7rem;
}

.text-cell-primary {
    color: #f8fafc !important;
    font-size: 0.85rem;
}

:global(body:not(.dark-mode)) .text-cell-primary {
    color: #0f172a !important;
}

.text-cell-muted {
    color: #cbd5e1 !important;
}

:global(body:not(.dark-mode)) .text-cell-muted {
    color: #64748b !important;
}

.text-cell-white {
    color: #ffffff !important;
}

:global(body:not(.dark-mode)) .text-cell-white {
    color: #0f172a !important;
}

.badge-outlet-pill {
    background: rgba(59, 130, 246, 0.18);
    color: #93c5fd !important;
    border: 1px solid rgba(59, 130, 246, 0.35);
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
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
/* ACTION BUTTONS (CRISP, VISIBLE & VIBRANT)                    */
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

.btn-action-edit {
    background: rgba(245, 158, 11, 0.18);
    color: #fbbf24 !important;
    border-color: rgba(245, 158, 11, 0.4);
}
.btn-action-edit:hover {
    background: #d97706;
    color: #ffffff !important;
    border-color: #d97706;
}

.btn-action-finalize {
    background: #10b981;
    color: #ffffff !important;
    border-color: #10b981;
}
.btn-action-finalize:hover {
    background: #059669;
    border-color: #059669;
}

.btn-action-print {
    background: rgba(148, 163, 184, 0.18);
    color: #e2e8f0 !important;
    border-color: rgba(148, 163, 184, 0.35);
}
.btn-action-print:hover {
    background: #475569;
    color: #ffffff !important;
    border-color: #475569;
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

.invoice-pagination {
    background: #0f172a;
}

:global(body:not(.dark-mode)) .invoice-pagination {
    background: #f8fafc;
}
</style>
