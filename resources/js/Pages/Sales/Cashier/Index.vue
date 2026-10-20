<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    reports: Object,
    cashiers: Array,
    outlets: Array,
    filters: Object,
});

const cashierFilter = ref(props.filters?.cashier_id || '');
const shiftNameFilter = ref(props.filters?.shift_name || '');
const outletFilter = ref(props.filters?.outlet_id || '');
const paymentFilter = ref(props.filters?.payment_method || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');

let debounceTimer = null;
const debouncedSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(
            route('sales.cashier.index'),
            {
                cashier_id: cashierFilter.value,
                shift_name: shiftNameFilter.value,
                outlet_id: outletFilter.value,
                payment_method: paymentFilter.value,
                start_date: startDate.value,
                end_date: endDate.value,
            },
            { preserveState: true, replace: true }
        );
    }, 300);
};

watch([cashierFilter, shiftNameFilter, outletFilter, paymentFilter, startDate, endDate], () => {
    debouncedSearch();
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// ================= MODAL 1: TRANSAKSI PER KASIR & SHIFT =================
const showTransactionsModal = ref(false);
const activeCashierRow = ref(null);
const isLoadingTransactions = ref(false);
const transactionsList = ref([]);
const transactionsPagination = ref({
    current_page: 1,
    last_page: 1,
    from: 0,
    to: 0,
    total: 0,
    links: [],
    prev_page_url: null,
    next_page_url: null,
});

const modalSearch = ref('');
const modalPaymentMethod = ref('');
const modalPerPage = ref(10);

let modalSearchTimer = null;
const debouncedModalSearch = () => {
    clearTimeout(modalSearchTimer);
    modalSearchTimer = setTimeout(() => {
        fetchTransactions(1);
    }, 300);
};

const openTransactionsModal = (row) => {
    activeCashierRow.value = row;
    modalSearch.value = '';
    modalPaymentMethod.value = paymentFilter.value || '';
    modalPerPage.value = 10;
    showTransactionsModal.value = true;
    fetchTransactions(1);
};

const closeTransactionsModal = () => {
    showTransactionsModal.value = false;
    activeCashierRow.value = null;
};

const fetchTransactions = async (page = 1) => {
    if (!activeCashierRow.value) return;
    isLoadingTransactions.value = true;

    try {
        const params = {
            page: page,
            per_page: modalPerPage.value,
            cashier_id: activeCashierRow.value.cashier_id,
            shift_id: activeCashierRow.value.shift_id ?? 'null',
            outlet_id: activeCashierRow.value.outlet_id || '',
            payment_method: modalPaymentMethod.value,
            start_date: startDate.value,
            end_date: endDate.value,
            search: modalSearch.value,
        };

        const response = await axios.get(route('sales.cashier.transactions'), { params });
        if (response.data?.success) {
            const data = response.data.data;
            transactionsList.value = data.data || [];
            transactionsPagination.value = {
                current_page: data.current_page,
                last_page: data.last_page,
                from: data.from || 0,
                to: data.to || 0,
                total: data.total || 0,
                links: data.links || [],
                prev_page_url: data.prev_page_url,
                next_page_url: data.next_page_url,
            };
        }
    } catch (err) {
        console.error('Gagal mengambil daftar transaksi:', err);
    } finally {
        isLoadingTransactions.value = false;
    }
};

// ================= MODAL 2: DETAIL TRANSAKSI LENGKAP =================
const showDetailModal = ref(false);
const selectedSale = ref(null);
const isLoadingSaleDetail = ref(false);

const openDetailModal = async (sale) => {
    selectedSale.value = sale;
    showDetailModal.value = true;
    isLoadingSaleDetail.value = true;

    try {
        const res = await axios.get(route('sales.history.show', sale.id), {
            params: { json: 1 },
            headers: { 'Accept': 'application/json' }
        });
        if (res.data?.success && res.data.sale) {
            selectedSale.value = res.data.sale;
        }
    } catch (e) {
        console.warn('Gagal memuat detail transaksi terbaru, menggunakan data item lokal:', e);
    } finally {
        isLoadingSaleDetail.value = false;
    }
};

const closeDetailModal = () => {
    showDetailModal.value = false;
};

// ================= MODAL 3: CETAK STRUK / INVOICE =================
const showPrintModal = ref(false);
const printFormat = ref('A4');
const printTime = ref('');
const saleToPrint = ref(null);

const openPrintModal = (sale) => {
    saleToPrint.value = sale;
    const now = new Date();
    printTime.value = now.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
                     now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    showPrintModal.value = true;
};

const executePrint = () => {
    const printArea = document.getElementById('printableCashierInvoiceArea');
    if (!printArea) return;

    const printContents = printArea.innerHTML;
    const printWindow = window.open('', '_blank', 'width=900,height=800');

    let bodyWidth = '210mm';
    let fontSize = '12px';
    if (printFormat.value === '80mm') {
        bodyWidth = '76mm';
        fontSize = '11px';
    } else if (printFormat.value === '58mm') {
        bodyWidth = '54mm';
        fontSize = '10px';
    }

    printWindow.document.write('<!DOCTYPE html><html><head><title>Struk / Invoice - ' + (saleToPrint.value?.invoice_number || '') + '</title>');
    printWindow.document.write('<style>');
    printWindow.document.write(`
        @page { size: ${printFormat.value === 'A4' ? 'A4 portrait' : bodyWidth + ' auto'}; margin: ${printFormat.value === 'A4' ? '15mm' : '3mm'}; }
        body {
            font-family: ${printFormat.value === 'A4' ? '\'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif' : '\'Courier New\', Courier, monospace'};
            width: ${printFormat.value === 'A4' ? '100%' : bodyWidth};
            margin: 0 auto;
            padding: 5px;
            font-size: ${fontSize};
            color: #000;
            background: #fff;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .text-muted { color: #555; }
        .small { font-size: 0.85em; }
        .font-monospace { font-family: monospace; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 8px; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        th { background-color: #f2f2f2; }
        .thermal-table th, .thermal-table td { border: none; border-bottom: 1px dashed #777; padding: 4px 0; }
        .my-2 { margin-top: 8px; margin-bottom: 8px; }
        .py-2 { padding-top: 8px; padding-bottom: 8px; }
        .border-top { border-top: 1px solid #ccc; }
        .border-bottom { border-bottom: 1px solid #ccc; }
        .d-flex { display: flex; }
        .justify-content-between { justify-content: space-between; }
        .signature-box { margin-top: 30px; display: flex; justify-content: space-between; text-align: center; }
        .signature-col { width: 45%; }
        .signature-line { margin-top: 45px; font-weight: bold; }
    `);
    printWindow.document.write('</style></head><body>');
    printWindow.document.write(printContents);
    printWindow.document.write('</body></html>');

    printWindow.document.close();
    printWindow.focus();

    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 300);
};

const getPaymentBadgeClass = (method) => {
    switch ((method || '').toLowerCase()) {
        case 'cash':
            return 'bg-success text-white';
        case 'qris':
            return 'bg-info text-dark';
        case 'debit':
            return 'bg-primary text-white';
        case 'transfer':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary text-white';
    }
};

const getItemCount = (sale) => {
    if (!sale?.items || sale.items.length === 0) return 0;
    return sale.items.reduce((acc, curr) => acc + (Number(curr.quantity) || 1), 0);
};

// Keyboard listener for Escape & Body scroll lock
const handleKeydown = (e) => {
    if (e.key === 'Escape') {
        if (showPrintModal.value) {
            showPrintModal.value = false;
        } else if (showDetailModal.value) {
            closeDetailModal();
        } else if (showTransactionsModal.value) {
            closeTransactionsModal();
        }
    }
};

watch([showTransactionsModal, showDetailModal, showPrintModal], ([m1, m2, m3]) => {
    if (m1 || m2 || m3) {
        document.body.classList.add('overflow-hidden');
    } else {
        document.body.classList.remove('overflow-hidden');
    }
});

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    document.body.classList.remove('overflow-hidden');
});
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">
                            <i class="bx bx-user-check text-primary me-2"></i>Laporan Penjualan Per Kasir & Shift
                        </h2>
                        <p class="text-muted small mb-0">Analisa rincian pendapatan, breakdown metode pembayaran, dan refund per kasir & shift.</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm p-3 mb-4" style="border-radius: 12px;">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <select v-model="cashierFilter" class="form-select">
                                <option value="">-- Semua Kasir --</option>
                                <option v-for="c in cashiers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select v-model="shiftNameFilter" class="form-select">
                                <option value="">-- Semua Shift --</option>
                                <option value="Pagi">Shift Pagi</option>
                                <option value="Siang">Shift Siang</option>
                                <option value="Malam">Shift Malam</option>
                                <option value="Full Day">Shift Full Day</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select v-model="paymentFilter" class="form-select">
                                <option value="">-- Metode Bayar --</option>
                                <option value="cash">Cash</option>
                                <option value="qris">QRIS</option>
                                <option value="debit">Debit</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2 align-items-center">
                            <input type="date" v-model="startDate" class="form-control" title="Tanggal Awal">
                            <span class="text-muted">s/d</span>
                            <input type="date" v-model="endDate" class="form-control" title="Tanggal Akhir">
                        </div>
                    </div>
                </div>

                <!-- Report Table -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>Kasir</th>
                                    <th>Shift / Outlet</th>
                                    <th class="text-center">Jml Transaksi</th>
                                    <th class="text-end">Penjualan Gross</th>
                                    <th class="text-end">Cash</th>
                                    <th class="text-end">QRIS</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Transfer</th>
                                    <th class="text-end text-warning">Refund</th>
                                    <th class="text-end">Net Sales</th>
                                    <th class="text-center" style="min-width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(r, idx) in reports.data" :key="idx" class="table-row-item">
                                    <td class="fw-bold text-dark">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-sm bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                <i class="bx bx-user fs-5"></i>
                                            </div>
                                            <span>{{ r.cashier_name }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-primary">{{ r.shift_name || 'Standar' }}</div>
                                        <small class="text-muted"><i class="bx bx-store-alt me-1"></i>{{ r.outlet_name || 'Utama' }}</small>
                                    </td>
                                    <td class="text-center">
                                        <button 
                                            @click="openTransactionsModal(r)" 
                                            class="btn btn-sm btn-light border text-primary fw-bold px-3 py-1 rounded-pill shadow-xs d-inline-flex align-items-center gap-1 btn-badge-trigger"
                                            title="Klik untuk melihat daftar transaksi lengkap"
                                        >
                                            <i class="bx bx-list-ul"></i>
                                            <span>{{ r.total_transactions }}</span>
                                        </button>
                                    </td>
                                    <td class="text-end fw-bold text-dark">{{ formatCurrency(r.total_sales) }}</td>
                                    <td class="text-end text-success font-monospace">{{ formatCurrency(r.cash_sales) }}</td>
                                    <td class="text-end text-info font-monospace">{{ formatCurrency(r.qris_sales) }}</td>
                                    <td class="text-end text-muted font-monospace">{{ formatCurrency(r.debit_sales) }}</td>
                                    <td class="text-end text-muted font-monospace">{{ formatCurrency(r.transfer_sales) }}</td>
                                    <td class="text-end text-danger font-monospace">-{{ formatCurrency(r.refund_amount) }}</td>
                                    <td class="text-end fw-bold text-primary fs-6">{{ formatCurrency(r.net_sales) }}</td>
                                    <td class="text-center">
                                        <button 
                                            @click="openTransactionsModal(r)" 
                                            class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm px-2 py-1 btn-action-detail"
                                            title="Buka rincian transaksi lengkap"
                                        >
                                            <i class="bx bx-receipt fs-6"></i>
                                            <span>Detail Transaksi</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!reports.data || reports.data.length === 0">
                                    <td colspan="11" class="text-center py-4 text-muted">Belum ada data penjualan kasir yang ditemukan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top" v-if="reports.links && reports.links.length > 3">
                        <small class="text-muted">
                            Menampilkan {{ reports.from }} - {{ reports.to }} dari {{ reports.total }} hasil
                        </small>
                        <div class="btn-group btn-group-sm">
                            <template v-for="(link, k) in reports.links" :key="k">
                                <div v-if="link.url === null" class="btn btn-outline-secondary disabled" v-html="link.label"></div>
                                <Link v-else :href="link.url" :class="['btn', link.active ? 'btn-primary' : 'btn-outline-secondary']" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- MODAL 1: DAFTAR TRANSAKSI KASIR & SHIFT (WITH PAGINATION)     -->
        <!-- ============================================================== -->
        <Transition name="modal-smooth">
            <div 
                v-if="showTransactionsModal" 
                class="modal-backdrop-wrapper" 
                @click.self="closeTransactionsModal"
            >
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-animated w-100 my-auto">
                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                        <!-- Modal Header -->
                        <div class="modal-header bg-primary text-white px-4 py-3 border-0">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center p-2 shadow-sm header-icon-badge" style="width: 44px; height: 44px;">
                                    <i class="bx bx-receipt fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="modal-title fw-bold mb-0">Rincian Transaksi: {{ activeCashierRow?.cashier_name }}</h5>
                                    <div class="small text-white-50 mt-1 d-flex gap-2 flex-wrap align-items-center">
                                        <span class="badge bg-light text-primary px-2 py-1"><i class="bx bx-time-five me-1"></i>Shift: {{ activeCashierRow?.shift_name || 'Standar' }}</span>
                                        <span class="badge bg-light text-primary px-2 py-1"><i class="bx bx-store-alt me-1"></i>Outlet: {{ activeCashierRow?.outlet_name || 'Utama' }}</span>
                                        <span v-if="startDate || endDate" class="badge bg-info text-dark px-2 py-1">
                                            <i class="bx bx-calendar me-1"></i>{{ startDate || 'Awal' }} s/d {{ endDate || 'Sekarang' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" @click="closeTransactionsModal"></button>
                        </div>

                        <!-- Modal Body -->
                        <div class="modal-body bg-light p-4">
                            <!-- Summary Cards -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3 col-6">
                                    <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100 stat-card">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Total Transaksi</div>
                                        <div class="d-flex align-items-baseline gap-2">
                                            <h3 class="fw-bold text-dark mb-0">{{ activeCashierRow?.total_transactions }}</h3>
                                            <span class="text-muted small">penjualan</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100 stat-card">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Gross Sales</div>
                                        <h4 class="fw-bold text-primary mb-0">{{ formatCurrency(activeCashierRow?.total_sales) }}</h4>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100 stat-card">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Cash / QRIS</div>
                                        <div class="small fw-semibold text-success">Cash: {{ formatCurrency(activeCashierRow?.cash_sales) }}</div>
                                        <div class="small fw-semibold text-info">QRIS: {{ formatCurrency(activeCashierRow?.qris_sales) }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100 stat-card">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Net Sales (Setelah Retur)</div>
                                        <h4 class="fw-bold text-success mb-0">{{ formatCurrency(activeCashierRow?.net_sales) }}</h4>
                                        <small v-if="activeCashierRow?.refund_amount > 0" class="text-danger small d-block">
                                            Refund: -{{ formatCurrency(activeCashierRow?.refund_amount) }}
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Toolbar: Search & Payment Method Filter -->
                            <div class="card border-0 shadow-sm p-3 mb-3 rounded-3 bg-white">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-5">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bx bx-search"></i></span>
                                            <input 
                                                type="text" 
                                                v-model="modalSearch" 
                                                @input="debouncedModalSearch"
                                                placeholder="Cari No Invoice / Nama Pelanggan..." 
                                                class="form-control border-start-0"
                                            >
                                            <button v-if="modalSearch" class="btn btn-outline-secondary border-start-0" type="button" @click="modalSearch = ''; fetchTransactions(1)">
                                                <i class="bx bx-x"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <select v-model="modalPaymentMethod" @change="fetchTransactions(1)" class="form-select">
                                            <option value="">-- Semua Metode Bayar --</option>
                                            <option value="cash">Tunai / Cash</option>
                                            <option value="qris">QRIS</option>
                                            <option value="debit">Kartu Debit</option>
                                            <option value="transfer">Transfer Bank</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select v-model="modalPerPage" @change="fetchTransactions(1)" class="form-select" title="Baris per halaman">
                                            <option :value="5">5 per hal</option>
                                            <option :value="10">10 per hal</option>
                                            <option :value="25">25 per hal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <button @click="fetchTransactions(transactionsPagination.current_page)" class="btn btn-outline-primary w-100 d-inline-flex align-items-center justify-content-center gap-1 btn-action-detail">
                                            <i class="bx bx-refresh" :class="{ 'bx-spin': isLoadingTransactions }"></i>
                                            <span>Refresh</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Transactions Table -->
                            <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-muted small text-uppercase">
                                            <tr>
                                                <th class="ps-3" style="width: 50px;">#</th>
                                                <th>No. Invoice</th>
                                                <th>Waktu Transaksi</th>
                                                <th>Pelanggan</th>
                                                <th class="text-center">Metode Bayar</th>
                                                <th class="text-center">Jml Item</th>
                                                <th class="text-end">Total Belanja</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-center pe-3" style="width: 160px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Loading Spinner State -->
                                            <tr v-if="isLoadingTransactions">
                                                <td colspan="9" class="text-center py-5 text-muted">
                                                    <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                                                    <span>Memuat data transaksi kasir...</span>
                                                </td>
                                            </tr>

                                            <!-- Transactions Rows -->
                                            <template v-else-if="transactionsList && transactionsList.length > 0">
                                                <tr v-for="(sale, sIdx) in transactionsList" :key="sale.id" class="table-transaction-row">
                                                    <td class="ps-3 text-muted small">
                                                        {{ (transactionsPagination.current_page - 1) * modalPerPage + sIdx + 1 }}
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold text-primary font-monospace">{{ sale.invoice_number }}</div>
                                                        <small class="text-muted">{{ sale.outlet?.name || 'Utama' }}</small>
                                                    </td>
                                                    <td>
                                                        <div class="text-dark small">{{ formatDate(sale.sale_date) }}</div>
                                                    </td>
                                                    <td>
                                                        <div class="fw-medium text-dark">{{ sale.customer?.name || 'Umum / Non-Member' }}</div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge text-uppercase px-2 py-1" :class="getPaymentBadgeClass(sale.payment_method)">
                                                            {{ sale.payment_method }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-light text-dark border px-2 py-1">
                                                            <i class="bx bx-capsule me-1 text-primary"></i>{{ getItemCount(sale) }} obat
                                                        </span>
                                                    </td>
                                                    <td class="text-end fw-bold text-dark font-monospace fs-6">
                                                        {{ formatCurrency(sale.grand_total) }}
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 text-uppercase">
                                                            {{ sale.status || 'COMPLETED' }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center pe-3">
                                                        <div class="btn-group btn-group-sm">
                                                            <button 
                                                                @click="openDetailModal(sale)" 
                                                                class="btn btn-primary d-inline-flex align-items-center gap-1 px-2 btn-action-detail"
                                                                title="Lihat Detail Transaksi & Rincian Obat"
                                                            >
                                                                <i class="bx bx-show"></i>
                                                                <span>Detail</span>
                                                            </button>
                                                            <button 
                                                                @click="openPrintModal(sale)" 
                                                                class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 px-2 btn-action-detail"
                                                                title="Cetak Struk / Faktur"
                                                            >
                                                                <i class="bx bx-printer"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>

                                            <!-- Empty State -->
                                            <tr v-else>
                                                <td colspan="9" class="text-center py-5 text-muted">
                                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                                        <i class="bx bx-folder-open fs-1 text-muted opacity-50 mb-2"></i>
                                                        <div class="fw-bold">Tidak ada transaksi yang cocok</div>
                                                        <small class="text-muted">Coba ubah filter pencarian atau metode pembayaran.</small>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Modal Pagination Controls -->
                                <div class="d-flex justify-content-between align-items-center p-3 border-top bg-light" v-if="transactionsPagination.total > 0">
                                    <small class="text-muted">
                                        Menampilkan <strong>{{ transactionsPagination.from }} - {{ transactionsPagination.to }}</strong> dari <strong>{{ transactionsPagination.total }}</strong> transaksi
                                    </small>

                                    <div class="d-flex align-items-center gap-1">
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-secondary btn-action-detail"
                                            :disabled="transactionsPagination.current_page <= 1 || isLoadingTransactions"
                                            @click="fetchTransactions(transactionsPagination.current_page - 1)"
                                        >
                                            <i class="bx bx-chevron-left me-1"></i> Sebelumnya
                                        </button>

                                        <!-- Numeric Page Buttons -->
                                        <div class="btn-group btn-group-sm">
                                            <template v-for="(link, lIdx) in transactionsPagination.links" :key="lIdx">
                                                <button 
                                                    v-if="!isNaN(Number(link.label))"
                                                    type="button"
                                                    class="btn"
                                                    :class="link.active ? 'btn-primary' : 'btn-outline-secondary'"
                                                    :disabled="isLoadingTransactions"
                                                    @click="fetchTransactions(Number(link.label))"
                                                >
                                                    {{ link.label }}
                                                </button>
                                            </template>
                                        </div>

                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-secondary btn-action-detail"
                                            :disabled="transactionsPagination.current_page >= transactionsPagination.last_page || isLoadingTransactions"
                                            @click="fetchTransactions(transactionsPagination.current_page + 1)"
                                        >
                                            Selanjutnya <i class="bx bx-chevron-right ms-1"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="modal-footer bg-white border-top px-4 py-3">
                            <button type="button" class="btn btn-secondary px-4 btn-action-detail" @click="closeTransactionsModal">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ============================================================== -->
        <!-- MODAL 2: DETAIL TRANSAKSI LENGKAP (ITEMS, OBAT, PEMBAYARAN)   -->
        <!-- ============================================================== -->
        <Transition name="modal-smooth">
            <div 
                v-if="showDetailModal" 
                class="modal-backdrop-wrapper modal-backdrop-detail" 
                @click.self="closeDetailModal"
            >
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-animated w-100 my-auto">
                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden fade-in-content">
                        <!-- Detail Header -->
                        <div class="modal-header bg-dark text-white px-4 py-3 border-0">
                            <div class="d-flex align-items-center gap-3">
                                <button @click="closeDetailModal" class="btn btn-outline-light btn-sm rounded-circle p-2 btn-action-detail" title="Kembali ke Daftar Transaksi">
                                    <i class="bx bx-arrow-back fs-5"></i>
                                </button>
                                <div>
                                    <h5 class="modal-title fw-bold mb-0">
                                        <i class="bx bx-file text-primary me-2"></i>Detail Transaksi: {{ selectedSale?.invoice_number }}
                                    </h5>
                                    <div class="small text-white-50 mt-1">
                                        Tanggal: {{ formatDate(selectedSale?.sale_date) }} | Status: <span class="badge bg-success text-uppercase">{{ selectedSale?.status || 'COMPLETED' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button @click="openPrintModal(selectedSale)" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm btn-action-detail">
                                    <i class="bx bx-printer"></i> Cetak Struk
                                </button>
                                <button type="button" class="btn-close btn-close-white" @click="closeDetailModal"></button>
                            </div>
                        </div>

                        <!-- Detail Body -->
                        <div class="modal-body bg-light p-4">
                            <!-- Loading State -->
                            <div v-if="isLoadingSaleDetail" class="text-center py-4 text-muted">
                                <div class="spinner-border text-primary spinner-border-sm me-2"></div>
                                <span>Memuat rincian obat transaksi...</span>
                            </div>

                            <div v-else class="fade-in-content">
                                <!-- Info Cards -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
                                            <h6 class="fw-bold text-muted text-uppercase small mb-2 border-bottom pb-2">
                                                <i class="bx bx-info-circle me-1 text-primary"></i>Informasi Transaksi
                                            </h6>
                                            <div class="row g-2 small">
                                                <div class="col-5 text-muted">No. Invoice:</div>
                                                <div class="col-7 fw-bold text-dark font-monospace">{{ selectedSale?.invoice_number }}</div>

                                                <div class="col-5 text-muted">Kasir / Petugas:</div>
                                                <div class="col-7 fw-bold text-dark">{{ selectedSale?.user?.name || activeCashierRow?.cashier_name || '-' }}</div>

                                                <div class="col-5 text-muted">Shift:</div>
                                                <div class="col-7 fw-medium text-primary">{{ selectedSale?.shift?.shift_name || activeCashierRow?.shift_name || 'Standar' }}</div>

                                                <div class="col-5 text-muted">Outlet:</div>
                                                <div class="col-7 text-dark">{{ selectedSale?.outlet?.name || activeCashierRow?.outlet_name || 'Utama' }}</div>

                                                <div class="col-5 text-muted">Waktu:</div>
                                                <div class="col-7 text-dark">{{ formatDate(selectedSale?.sale_date) }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
                                            <h6 class="fw-bold text-muted text-uppercase small mb-2 border-bottom pb-2">
                                                <i class="bx bx-credit-card me-1 text-primary"></i>Pelanggan & Pembayaran
                                            </h6>
                                            <div class="row g-2 small">
                                                <div class="col-5 text-muted">Pelanggan:</div>
                                                <div class="col-7 fw-bold text-primary">{{ selectedSale?.customer?.name || 'Umum / Non-Member' }}</div>

                                                <div class="col-5 text-muted">Metode Bayar:</div>
                                                <div class="col-7">
                                                    <span class="badge text-uppercase px-2 py-1" :class="getPaymentBadgeClass(selectedSale?.payment_method)">
                                                        {{ selectedSale?.payment_method }}
                                                    </span>
                                                </div>

                                                <div class="col-5 text-muted">Uang Dibayar:</div>
                                                <div class="col-7 fw-bold text-dark font-monospace">{{ formatCurrency(selectedSale?.paid_amount) }}</div>

                                                <div class="col-5 text-muted">Kembalian:</div>
                                                <div class="col-7 fw-bold text-success font-monospace">{{ formatCurrency(selectedSale?.change_amount) }}</div>

                                                <div v-if="selectedSale?.notes" class="col-12 mt-1">
                                                    <div class="text-muted">Catatan:</div>
                                                    <div class="bg-light p-2 rounded small text-dark">{{ selectedSale.notes }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Purchased Medicines List -->
                                <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden mb-4">
                                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                            <i class="bx bx-capsule text-primary fs-5"></i>
                                            <span>Rincian Obat yang Dibeli</span>
                                        </h6>
                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-medium px-2 py-1">
                                            {{ selectedSale?.items?.length || 0 }} macam obat
                                        </span>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light text-muted small text-uppercase">
                                                <tr>
                                                    <th class="ps-3" style="width: 40px;">No</th>
                                                    <th>Nama & Kode Obat</th>
                                                    <th class="text-center" style="width: 100px;">Jumlah</th>
                                                    <th class="text-end" style="width: 130px;">Harga Satuan</th>
                                                    <th class="text-end" style="width: 110px;" v-if="selectedSale?.items?.some(i => i.discount > 0)">Diskon Item</th>
                                                    <th class="text-end pe-3" style="width: 140px;">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(item, idx) in selectedSale?.items" :key="item.id || idx">
                                                    <td class="ps-3 text-muted small">{{ idx + 1 }}</td>
                                                    <td>
                                                        <div class="fw-bold text-dark">{{ item.medicine?.nama || item.medicine_id }}</div>
                                                        <small class="text-muted font-monospace">Kode: {{ item.medicine_id }}</small>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-secondary bg-opacity-10 text-dark border px-2 py-1 fw-bold fs-6">
                                                            {{ item.quantity }}
                                                        </span>
                                                    </td>
                                                    <td class="text-end font-monospace text-muted">
                                                        {{ formatCurrency(item.unit_price) }}
                                                    </td>
                                                    <td class="text-end text-danger font-monospace" v-if="selectedSale?.items?.some(i => i.discount > 0)">
                                                        {{ item.discount > 0 ? '-' + formatCurrency(item.discount) : '-' }}
                                                    </td>
                                                    <td class="text-end pe-3 fw-bold text-dark font-monospace">
                                                        {{ formatCurrency(item.subtotal || (item.unit_price * item.quantity)) }}
                                                    </td>
                                                </tr>
                                                <tr v-if="!selectedSale?.items || selectedSale.items.length === 0">
                                                    <td colspan="6" class="text-center py-4 text-muted">Tidak ada rincian item dalam transaksi ini.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                <!-- Financial Calculation Summary -->
                                <div class="p-3 bg-light border-top">
                                    <div class="row justify-content-end">
                                        <div class="col-md-6 col-12">
                                            <div class="d-flex justify-content-between py-1 small">
                                                <span class="text-muted">Subtotal:</span>
                                                <span class="fw-bold text-dark font-monospace">{{ formatCurrency(selectedSale?.subtotal) }}</span>
                                            </div>
                                            <div v-if="selectedSale?.discount > 0" class="d-flex justify-content-between py-1 small text-danger">
                                                <span>Diskon Transaksi:</span>
                                                <span class="fw-bold font-monospace">-{{ formatCurrency(selectedSale?.discount) }}</span>
                                            </div>
                                            <div v-if="selectedSale?.tax > 0" class="d-flex justify-content-between py-1 small text-muted">
                                                <span>PPN (Pajak):</span>
                                                <span class="fw-bold font-monospace">+{{ formatCurrency(selectedSale?.tax) }}</span>
                                            </div>
                                            <hr class="my-2">
                                            <div class="d-flex justify-content-between align-items-center py-1">
                                                <span class="fw-bold text-dark fs-6">Grand Total:</span>
                                                <span class="fw-bold text-primary fs-5 font-monospace">{{ formatCurrency(selectedSale?.grand_total) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Product Return Info (If Any) -->
                            <div v-if="selectedSale?.product_returns && selectedSale.product_returns.length > 0" class="alert alert-warning border-0 shadow-sm d-flex align-items-start gap-2 mb-0">
                                <i class="bx bx-repost fs-4 text-warning"></i>
                                <div>
                                    <div class="fw-bold">Riwayat Retur Terkait Transaksi Ini:</div>
                                    <ul class="mb-0 small ps-3 mt-1">
                                        <li v-for="ret in selectedSale.product_returns" :key="ret.id">
                                            Retur No: <strong>{{ ret.return_number }}</strong> (Status: {{ ret.status }}) — Total Refund: {{ formatCurrency(ret.refund_amount) }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Footer -->
                    <div class="modal-footer bg-white border-top px-4 py-3 d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 btn-action-detail" @click="closeDetailModal">
                            <i class="bx bx-chevron-left"></i> Kembali ke Daftar Transaksi
                        </button>

                        <div class="d-flex gap-2">
                            <Link 
                                v-if="selectedSale" 
                                :href="route('sales.history.show', selectedSale.id)" 
                                target="_blank"
                                class="btn btn-outline-primary d-inline-flex align-items-center gap-1 btn-action-detail"
                            >
                                <i class="bx bx-link-external"></i> Halaman Penuh
                            </Link>
                            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1 btn-action-detail" @click="openPrintModal(selectedSale)">
                                <i class="bx bx-printer"></i> Cetak Struk
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </Transition>

        <!-- ============================================================== -->
        <!-- MODAL 3: CETAK STRUK / INVOICE PENJUALAN (A4 / 80MM / 58MM)   -->
        <!-- ============================================================== -->
        <Transition name="modal-smooth">
            <div 
                v-if="showPrintModal" 
                class="modal-backdrop-wrapper modal-backdrop-print" 
                @click.self="showPrintModal = false"
            >
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-animated w-100 my-auto">
                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="modal-header bg-primary text-white px-4 py-3 border-0">
                            <h5 class="modal-title fw-bold">
                                <i class="bx bx-printer me-2"></i>Preview Cetak Struk / Invoice Penjualan
                            </h5>
                            <button type="button" class="btn-close btn-close-white" @click="showPrintModal = false"></button>
                        </div>

                        <div class="modal-body bg-light p-4">
                            <!-- Format Selection Controls -->
                            <div class="card border-0 shadow-sm p-3 mb-4 rounded-3 bg-white">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="fw-bold me-2 text-dark mb-0">Pilih Format Printer:</label>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm" :class="printFormat === 'A4' ? 'btn-primary' : 'btn-outline-primary'" @click="printFormat = 'A4'">
                                                <i class="bx bx-file me-1"></i> Dokumen Invoice A4
                                            </button>
                                            <button type="button" class="btn btn-sm" :class="printFormat === '80mm' ? 'btn-primary' : 'btn-outline-primary'" @click="printFormat = '80mm'">
                                                <i class="bx bx-receipt me-1"></i> Thermal 80mm
                                            </button>
                                            <button type="button" class="btn btn-sm" :class="printFormat === '58mm' ? 'btn-primary' : 'btn-outline-primary'" @click="printFormat = '58mm'">
                                                <i class="bx bx-receipt me-1"></i> Thermal 58mm (Mini)
                                            </button>
                                        </div>
                                    </div>
                                    <span class="badge bg-info text-dark px-3 py-2 fs-6">
                                        Format: {{ printFormat }}
                                    </span>
                                </div>
                            </div>

                            <!-- LIVE PRINT PREVIEW CONTAINER -->
                            <div class="d-flex justify-content-center">
                                <div id="printableCashierInvoiceArea" class="bg-white p-4 text-dark shadow-sm border" :style="{
                                    width: printFormat === 'A4' ? '100%' : (printFormat === '80mm' ? '360px' : '280px'),
                                    fontSize: printFormat === 'A4' ? '13px' : '11px',
                                    fontFamily: printFormat === 'A4' ? 'Arial, sans-serif' : '\'Courier New\', Courier, monospace'
                                }">
                                    <!-- HEADER APOTEK -->
                                    <div class="text-center mb-3 pb-2 border-bottom">
                                        <img v-if="$page.props.app_settings?.pharmacy_logo" :src="$page.props.app_settings.pharmacy_logo" alt="Logo" style="max-height: 48px; object-fit: contain;" class="mb-1 d-block mx-auto">
                                        <img v-else src="/Assets/img/LOGO.svg" alt="Logo" style="max-height: 40px;" class="mb-1" @error="(e) => e.target.style.display='none'">
                                        <h4 class="fw-bold mb-0" style="font-size: 1.2em;">{{ $page.props.app_settings?.pharmacy_name || 'APOTEK SAYA' }}</h4>
                                        <div class="small">{{ $page.props.app_settings?.pharmacy_address || 'Jl. Raya Farmasi No. 10' }} | Telp: {{ $page.props.app_settings?.pharmacy_phone || '021-5551234' }}</div>
                                        <div v-if="$page.props.app_settings?.pharmacist_name" class="small text-muted" style="font-size: 0.85em;">
                                            Apoteker: {{ $page.props.app_settings.pharmacist_name }} | SIPA: {{ $page.props.app_settings.pharmacist_license }}
                                        </div>
                                        <hr class="my-2" :style="{ borderStyle: printFormat === 'A4' ? 'solid' : 'dashed' }">
                                        <h5 class="fw-bold text-uppercase mb-0" style="font-size: 1.1em; letter-spacing: 0.5px;">
                                            {{ printFormat === 'A4' ? 'FAKTUR INVOICE PENJUALAN' : 'NOTA STRUK PENJUALAN' }}
                                        </h5>
                                    </div>

                                    <!-- TRANSACTION INFO -->
                                    <div class="mb-3 small">
                                        <div class="d-flex justify-content-between">
                                            <span><strong>No Invoice:</strong> {{ saleToPrint?.invoice_number }}</span>
                                            <span><strong>Tgl:</strong> {{ formatDate(saleToPrint?.sale_date) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1">
                                            <span><strong>Kasir:</strong> {{ saleToPrint?.user?.name || activeCashierRow?.cashier_name || '-' }}</span>
                                            <span><strong>Metode:</strong> {{ saleToPrint?.payment_method?.toUpperCase() }}</span>
                                        </div>
                                        <div class="mt-1">
                                            <span><strong>Pelanggan:</strong> {{ saleToPrint?.customer?.name || 'Umum / Non-Member' }}</span>
                                        </div>
                                    </div>

                                    <!-- ITEMS TABLE -->
                                    <table :class="printFormat === 'A4' ? 'table table-bordered align-middle' : 'thermal-table'" style="margin-bottom: 10px;">
                                        <thead>
                                            <tr>
                                                <th v-if="printFormat === 'A4'" style="width: 30px;">No</th>
                                                <th>Nama Obat</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-end">Harga</th>
                                                <th class="text-end">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, idx) in saleToPrint?.items" :key="item.id || idx">
                                                <td v-if="printFormat === 'A4'" class="text-center">{{ idx + 1 }}</td>
                                                <td>
                                                    <div class="fw-bold">{{ item.medicine?.nama || item.medicine_id }}</div>
                                                    <small v-if="printFormat !== 'A4'" class="text-muted">Kode: {{ item.medicine_id }}</small>
                                                </td>
                                                <td class="text-center fw-bold">{{ item.quantity }}</td>
                                                <td class="text-end">{{ formatCurrency(item.unit_price) }}</td>
                                                <td class="text-end fw-bold">{{ formatCurrency(item.subtotal || (item.unit_price * item.quantity)) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <!-- SUMMARY & CALCULATIONS -->
                                    <div class="py-2 border-top border-bottom mb-3 small">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Subtotal Belanja:</span>
                                            <strong>{{ formatCurrency(saleToPrint?.subtotal) }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1 text-danger" v-if="saleToPrint?.discount > 0">
                                            <span>Diskon:</span>
                                            <strong>-{{ formatCurrency(saleToPrint?.discount) }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1 text-dark" v-if="saleToPrint?.tax > 0">
                                            <span>PPN:</span>
                                            <strong>+{{ formatCurrency(saleToPrint?.tax) }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between fs-6 fw-bold mt-2 pt-2 border-top">
                                            <span>TOTAL BELANJA:</span>
                                            <span class="text-primary">{{ formatCurrency(saleToPrint?.grand_total) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1">
                                            <span>Jumlah Uang Dibayar:</span>
                                            <span>{{ formatCurrency(saleToPrint?.paid_amount) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1 fw-bold">
                                            <span>Uang Kembalian:</span>
                                            <span class="text-success">{{ formatCurrency(saleToPrint?.change_amount) }}</span>
                                        </div>
                                    </div>

                                    <!-- SIGNATURE SECTION FOR A4 INVOICE -->
                                    <div class="signature-box my-3 pt-2" v-if="printFormat === 'A4'">
                                        <div class="signature-col">
                                            <div>Penerima / Pembeli</div>
                                            <div class="signature-line">( {{ saleToPrint?.customer?.name || '________________' }} )</div>
                                        </div>
                                        <div class="signature-col">
                                            <div>Kasir / Petugas Apotek</div>
                                            <div class="signature-line">( {{ saleToPrint?.user?.name || activeCashierRow?.cashier_name || '________________' }} )</div>
                                        </div>
                                    </div>

                                    <!-- FOOTER & BARCODE -->
                                    <div class="text-center mt-3 pt-2 border-top small text-muted">
                                        <div class="font-monospace fw-bold tracking-widest text-dark" style="letter-spacing: 2px;">
                                            ||| |||| | ||||| || ||| ||||
                                        </div>
                                        <div class="font-monospace small text-dark mb-1">{{ saleToPrint?.invoice_number }}</div>
                                        <div>Dicetak pada: {{ printTime }}</div>
                                        <small class="d-block mt-1">Terima Kasih Atas Kunjungan Anda — Semoga Lekas Sembuh</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer bg-white border-top px-4 py-3">
                            <button type="button" class="btn btn-secondary px-4 btn-action-detail" @click="showPrintModal = false">Batal</button>
                            <button type="button" class="btn btn-primary fw-bold px-4 d-inline-flex align-items-center gap-2 btn-action-detail" @click="executePrint">
                                <i class="bx bx-printer fs-5"></i> Cetak Sekarang ({{ printFormat }})
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

    </LegacyLayout>
</template>

<style scoped>
/* ============================================================== */
/* SMOOTH MODAL ANIMATIONS & GLASSMORPHISM BACKDROP               */
/* ============================================================== */
.modal-backdrop-wrapper {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1055;
    overflow-y: auto;
    padding: 1.25rem;
}

.modal-backdrop-detail {
    z-index: 1065;
    background: rgba(15, 23, 42, 0.82);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.modal-backdrop-print {
    z-index: 1075;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

/* Modal Smooth Transitions */
.modal-smooth-enter-active {
    transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-smooth-leave-active {
    transition: opacity 0.2s cubic-bezier(0.7, 0, 0.84, 0);
}
.modal-smooth-enter-from,
.modal-smooth-leave-to {
    opacity: 0;
}

.modal-smooth-enter-active .modal-animated {
    animation: modal-smooth-in 0.34s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.modal-smooth-leave-active .modal-animated {
    animation: modal-smooth-out 0.2s cubic-bezier(0.7, 0, 0.84, 0) forwards;
}

@keyframes modal-smooth-in {
    0% {
        opacity: 0;
        transform: scale(0.93) translateY(24px);
    }
    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes modal-smooth-out {
    0% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
    100% {
        opacity: 0;
        transform: scale(0.95) translateY(14px);
    }
}

/* Content Fade In */
.fade-in-content {
    animation: fadeInContent 0.28s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeInContent {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Micro-interactions & Buttons */
.btn-action-detail {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.btn-action-detail:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
}
.btn-action-detail:active {
    transform: scale(0.95);
}

.btn-badge-trigger {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.btn-badge-trigger:hover {
    transform: translateY(-2px) scale(1.04);
    background-color: #0d6efd !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.35);
}
.btn-badge-trigger:active {
    transform: scale(0.95);
}

.table-row-item {
    transition: background-color 0.15s ease;
}
.table-transaction-row {
    transition: background-color 0.15s ease;
}
.table-transaction-row:hover {
    background-color: rgba(13, 110, 253, 0.04);
}

.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08) !important;
}

.header-icon-badge {
    animation: pulseIcon 2s infinite ease-in-out;
}
@keyframes pulseIcon {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.06);
    }
}

.shadow-xs {
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
</style>
