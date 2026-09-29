<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';

const props = defineProps({
    period: String,
    start_date: String,
    end_date: String,
    prev_start_date: String,
    prev_end_date: String,
    metrics: Object,
    prev_metrics: Object,
    comparisons: Object,
    payment_methods: Array,
    product_breakdown: Array,
    top_products_sales: Array,
    top_products_profit: Array,
    bottom_products_margin: Array,
    category_breakdown: Array,
    expense_breakdown: Array,
    trend_data: Object,
    cashier_breakdown: Array,
    outlet_breakdown: Array,
    financial_positions: Object,
    last_updated_at: String,
    outlets: Array,
    cashiers: Array,
    categories: Array,
    filters: Object,
});

const selectedPeriod = ref(props.filters?.period || 'this_month');
const startDateInput = ref(props.start_date || '');
const endDateInput = ref(props.end_date || '');
const selectedOutlet = ref(props.filters?.outlet_id || '');
const selectedCashier = ref(props.filters?.cashier_id || '');

const activeTab = ref('sales_payment'); // 'sales_payment' | 'products' | 'top_bottom' | 'expenses' | 'cashier' | 'outlets' | 'cash_bank'
const productSearch = ref('');
const productCategoryFilter = ref('');
const productSort = ref('sales_desc'); // 'sales_desc' | 'profit_desc' | 'margin_desc' | 'margin_asc'

const showDetailedStatement = ref(true);

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const applyFilter = () => {
    router.get(route('finance.profit_loss'), {
        period: selectedPeriod.value,
        start_date: startDateInput.value,
        end_date: endDateInput.value,
        outlet_id: selectedOutlet.value,
        cashier_id: selectedCashier.value,
    }, { preserveState: true, preserveScroll: true });
};

watch(selectedPeriod, (newPeriod) => {
    if (newPeriod !== 'custom') {
        applyFilter();
    }
});

const filteredProducts = computed(() => {
    let list = [...(props.product_breakdown || [])];

    if (productSearch.value) {
        const q = productSearch.value.toLowerCase();
        list = list.filter(p => p.nama.toLowerCase().includes(q) || p.kode.toLowerCase().includes(q));
    }

    if (productCategoryFilter.value) {
        list = list.filter(p => p.kategori === productCategoryFilter.value);
    }

    if (productSort.value === 'sales_desc') {
        list.sort((a, b) => b.total_sales - a.total_sales);
    } else if (productSort.value === 'profit_desc') {
        list.sort((a, b) => b.gross_profit - a.gross_profit);
    } else if (productSort.value === 'margin_desc') {
        list.sort((a, b) => b.margin_percent - a.margin_percent);
    } else if (productSort.value === 'margin_asc') {
        list.sort((a, b) => a.margin_percent - b.margin_percent);
    }

    return list;
});

const printReport = () => {
    window.print();
};

const exportExcel = () => {
    // CSV / Excel Data Generation
    const rows = [
        ['LAPORAN LABA RUGI (PROFIT & LOSS STATEMENT)'],
        ['Periode', `${props.start_date} s/d ${props.end_date}`],
        ['Terakhir Diperbarui', props.last_updated_at],
        [],
        ['1. PENDAPATAN'],
        ['Total Penjualan Kotor', props.metrics.gross_sales],
        ['(-) Diskon Penjualan', props.metrics.total_discount],
        ['(-) Retur Penjualan', props.metrics.total_returns],
        ['PENJUALAN BERSIH', props.metrics.net_sales],
        [],
        ['2. HARGA POKOK PENJUALAN (HPP)'],
        ['(-) HPP Barang Terjual', props.metrics.cogs],
        ['LABA KOTOR', props.metrics.gross_profit],
        [],
        ['3. BIAYA OPERASIONAL'],
        ['(-) Total Biaya Operasional', props.metrics.total_expenses],
        ['LABA BERSIH', props.metrics.net_profit],
    ];

    let csvContent = "data:text/csv;charset=utf-8," + rows.map(e => e.join(",")).join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `Laporan_Laba_Rugi_${props.start_date}_s_d_${props.end_date}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4 print-container">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                
                <!-- Page Title & Quick Actions (Hidden in Print) -->
                <div class="d-flex justify-content-between align-items-center mb-4 no-print flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">
                            <i class="bx bx-chart text-success me-2"></i>Laporan Laba / Rugi (Profit & Loss)
                        </h2>
                        <span class="text-muted small">
                            <i class="bx bx-info-circle me-1 text-primary"></i>
                            Diperhitungkan dari transaksi <strong>COMPLETED</strong> | Status Data: <strong>{{ last_updated_at }}</strong>
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm fw-bold rounded-pill shadow-xs" @click="exportExcel">
                            <i class="bx bx-file me-1"></i> Export Excel
                        </button>
                        <button type="button" class="btn btn-primary btn-sm fw-bold rounded-pill shadow-xs" @click="printReport">
                            <i class="bx bx-printer me-1"></i> Cetak / Export PDF
                        </button>
                    </div>
                </div>

                <!-- Print Header (Visible only in Print) -->
                <div class="print-only mb-4 text-center">
                    <h3 class="fw-bold mb-1">{{ $page.props.app_settings?.pharmacy_name || 'APOTEK MEDIKA SORA' }}</h3>
                    <p class="mb-1 small">{{ $page.props.app_settings?.pharmacy_address || 'Jl. Raya Farmasi No. 10' }} | Telp: {{ $page.props.app_settings?.pharmacy_phone || '021-5551234' }}</p>
                    <h4 class="fw-bold text-uppercase mt-3 border-top border-bottom py-2">LAPORAN LABA RUGI (PROFIT & LOSS STATEMENT)</h4>
                    <p class="small text-muted mb-0">Periode Laporan: <strong>{{ start_date }}</strong> s/d <strong>{{ end_date }}</strong></p>
                </div>

                <!-- FILTER BAR SECTION (Hidden in Print) -->
                <div class="card border-0 shadow-sm p-3 mb-4 rounded-3 bg-white no-print">
                    <div class="row g-2 align-items-center">
                        <!-- Quick Period Filter -->
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-dark mb-1"><i class="bx bx-calendar me-1"></i>Periode Laporan</label>
                            <select class="form-select form-select-sm rounded-3 shadow-xs fw-semibold" v-model="selectedPeriod">
                                <option value="today">Hari Ini</option>
                                <option value="yesterday">Kemarin</option>
                                <option value="this_week">Minggu Ini</option>
                                <option value="this_month">Bulan Ini</option>
                                <option value="last_month">Bulan Lalu</option>
                                <option value="this_year">Tahun Ini</option>
                                <option value="last_year">Tahun Lalu</option>
                                <option value="custom">Custom Date Range</option>
                            </select>
                        </div>

                        <!-- Date Range Inputs -->
                        <div class="col-md-4" v-if="selectedPeriod === 'custom'">
                            <div class="row g-1">
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                                    <input type="date" class="form-control form-control-sm" v-model="startDateInput" @change="applyFilter">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                                    <input type="date" class="form-control form-control-sm" v-model="endDateInput" @change="applyFilter">
                                </div>
                            </div>
                        </div>

                        <!-- Outlet Filter -->
                        <div class="col-md-2" v-if="outlets && outlets.length > 0">
                            <label class="form-label small fw-bold text-dark mb-1"><i class="bx bx-store me-1"></i>Outlet</label>
                            <select class="form-select form-select-sm rounded-3 shadow-xs" v-model="selectedOutlet" @change="applyFilter">
                                <option value="">Semua Outlet</option>
                                <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
                            </select>
                        </div>

                        <!-- Cashier Filter -->
                        <div class="col-md-2" v-if="cashiers && cashiers.length > 0">
                            <label class="form-label small fw-bold text-dark mb-1"><i class="bx bx-user me-1"></i>Kasir</label>
                            <select class="form-select form-select-sm rounded-3 shadow-xs" v-model="selectedCashier" @change="applyFilter">
                                <option value="">Semua Kasir</option>
                                <option v-for="c in cashiers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- SECTION 1: KEY EXECUTIVE METRICS (CARD RINGKASAN UTAMA) -->
                <div class="row g-3 mb-4">
                    <!-- Net Sales -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm p-3 rounded-3 h-100 bg-white border-start border-4 border-primary">
                            <small class="text-muted fw-semibold d-block mb-1">Penjualan Bersih (Net Sales)</small>
                            <h4 class="fw-bold text-dark mb-1">{{ formatCurrency(metrics.net_sales) }}</h4>
                            <div class="d-flex align-items-center justify-content-between small">
                                <span class="text-muted" style="font-size: 0.75rem;">Kotor: {{ formatCurrency(metrics.gross_sales) }}</span>
                                <span v-if="comparisons.net_sales" :class="comparisons.net_sales.direction === 'up' ? 'text-success fw-bold' : 'text-danger fw-bold'" style="font-size: 0.75rem;">
                                    <i :class="comparisons.net_sales.direction === 'up' ? 'bx bx-trending-up me-0.5' : 'bx bx-trending-down me-0.5'"></i>
                                    {{ comparisons.net_sales.direction === 'up' ? '↑' : '↓' }} {{ Math.abs(comparisons.net_sales.percentage) }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- HPP / COGS -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm p-3 rounded-3 h-100 bg-white border-start border-4 border-secondary">
                            <small class="text-muted fw-semibold d-block mb-1">HPP Barang Terjual (COGS)</small>
                            <h4 class="fw-bold text-dark mb-1">{{ formatCurrency(metrics.cogs) }}</h4>
                            <div class="d-flex align-items-center justify-content-between small">
                                <span class="text-muted" style="font-size: 0.75rem;">Total Item: {{ metrics.total_items_sold }} unit</span>
                                <span v-if="comparisons.cogs" :class="comparisons.cogs.direction === 'up' ? 'text-danger fw-bold' : 'text-success fw-bold'" style="font-size: 0.75rem;">
                                    <i :class="comparisons.cogs.direction === 'up' ? 'bx bx-trending-up me-0.5' : 'bx bx-trending-down me-0.5'"></i>
                                    {{ comparisons.cogs.direction === 'up' ? '↑' : '↓' }} {{ Math.abs(comparisons.cogs.percentage) }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Gross Profit -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm p-3 rounded-3 h-100 bg-white border-start border-4 border-info">
                            <small class="text-muted fw-semibold d-block mb-1">Laba Kotor (Gross Profit)</small>
                            <h4 class="fw-bold text-primary mb-1">{{ formatCurrency(metrics.gross_profit) }}</h4>
                            <div class="d-flex align-items-center justify-content-between small">
                                <span class="badge bg-primary bg-opacity-10 text-primary font-monospace">Margin: {{ metrics.gross_margin_percent }}%</span>
                                <span v-if="comparisons.gross_profit" :class="comparisons.gross_profit.direction === 'up' ? 'text-success fw-bold' : 'text-danger fw-bold'" style="font-size: 0.75rem;">
                                    <i :class="comparisons.gross_profit.direction === 'up' ? 'bx bx-trending-up me-0.5' : 'bx bx-trending-down me-0.5'"></i>
                                    {{ comparisons.gross_profit.direction === 'up' ? '↑' : '↓' }} {{ Math.abs(comparisons.gross_profit.percentage) }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Net Profit Highlight Card -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm p-3 rounded-3 h-100 text-white" :class="metrics.net_profit >= 0 ? 'bg-success' : 'bg-danger'">
                            <small class="text-white text-opacity-75 fw-semibold d-block mb-1">Laba Bersih (Net Profit)</small>
                            <h3 class="fw-extrabold text-white mb-1">{{ formatCurrency(metrics.net_profit) }}</h3>
                            <div class="d-flex align-items-center justify-content-between small">
                                <span class="badge bg-black bg-opacity-20 text-white font-monospace">Net Margin: {{ metrics.net_margin_percent }}%</span>
                                <span v-if="comparisons.net_profit" class="text-white fw-bold" style="font-size: 0.75rem;">
                                    {{ comparisons.net_profit.direction === 'up' ? '↑' : '↓' }} {{ Math.abs(comparisons.net_profit.percentage) }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: GRAFIK TREN LABA RUGI -->
                <div class="card border-0 shadow-sm p-4 mb-4 rounded-3 bg-white no-print">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0"><i class="bx bx-line-chart me-2 text-primary"></i>Grafik Tren Keuangan (Harian)</h5>
                        <div class="small text-muted font-monospace">
                            <span class="badge bg-primary me-2">Penjualan</span>
                            <span class="badge bg-secondary me-2">HPP</span>
                            <span class="badge bg-danger me-2">Biaya</span>
                            <span class="badge bg-success">Laba Bersih</span>
                        </div>
                    </div>

                    <!-- SVG Bar / Line Chart Visualization -->
                    <div v-if="trend_data && trend_data.labels && trend_data.labels.length > 0" class="trend-chart-container py-3">
                        <div class="d-flex align-items-end justify-content-between gap-1" style="height: 180px;">
                            <div 
                                v-for="(lbl, idx) in trend_data.labels" 
                                :key="idx" 
                                class="flex-fill d-flex flex-column align-items-center justify-content-end h-100" 
                                style="min-width: 12px;"
                            >
                                <div class="w-100 d-flex align-items-end justify-content-center gap-0.5" style="height: 140px;">
                                    <!-- Net Sales Bar -->
                                    <div 
                                        class="bg-primary rounded-top" 
                                        :style="{ height: Math.min(100, Math.max(8, (trend_data.sales[idx] / (Math.max(...trend_data.sales) || 1)) * 100)) + '%' }" 
                                        style="width: 35%; transition: height 0.3s ease;"
                                        :title="`Penjualan ${lbl}: ${formatCurrency(trend_data.sales[idx])}`"
                                    ></div>
                                    <!-- Net Profit Bar -->
                                    <div 
                                        class="bg-success rounded-top" 
                                        :style="{ height: Math.min(100, Math.max(4, (trend_data.net_profit[idx] / (Math.max(...trend_data.sales) || 1)) * 100)) + '%' }" 
                                        style="width: 35%; transition: height 0.3s ease;"
                                        :title="`Laba Bersih ${lbl}: ${formatCurrency(trend_data.net_profit[idx])}`"
                                    ></div>
                                </div>
                                <span class="small text-muted font-monospace mt-2 text-truncate" style="font-size: 0.68rem; max-width: 45px;">{{ lbl }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: FORMAL INCOME STATEMENT (RINCIAN DETAIL LABA RUGI) -->
                <div class="card border-0 shadow-sm p-4 mb-4 rounded-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <h5 class="fw-bold text-dark mb-0"><i class="bx bx-receipt me-2 text-primary"></i>Laporan Laba Rugi Komprehensif (Income Statement)</h5>
                        <button type="button" class="btn btn-sm btn-outline-secondary no-print" @click="showDetailedStatement = !showDetailedStatement">
                            <i :class="showDetailedStatement ? 'bx bx-chevron-up me-1' : 'bx bx-chevron-down me-1'"></i>
                            {{ showDetailedStatement ? 'Sembunyikan Rincian' : 'Tampilkan Rincian' }}
                        </button>
                    </div>

                    <div v-if="showDetailedStatement" class="income-statement-table font-monospace">
                        <!-- 1. PENDAPATAN OPERASIONAL -->
                        <div class="mb-3">
                            <h6 class="fw-bold text-primary text-uppercase mb-2">1. PENDAPATAN OPERASIONAL (REVENUE)</h6>
                            <div class="d-flex justify-content-between border-bottom py-1.5 px-2">
                                <span>Penjualan Kotor (Gross Sales)</span>
                                <span class="fw-semibold">{{ formatCurrency(metrics.gross_sales) }}</span>
                            </div>
                            <div class="d-flex justify-content-between border-bottom py-1.5 px-2 text-danger">
                                <span>(-) Diskon Penjualan</span>
                                <span>({{ formatCurrency(metrics.total_discount) }})</span>
                            </div>
                            <div class="d-flex justify-content-between border-bottom py-1.5 px-2 text-danger">
                                <span>(-) Retur Penjualan (Approved)</span>
                                <span>({{ formatCurrency(metrics.total_returns) }})</span>
                            </div>
                            <div class="d-flex justify-content-between py-2 fw-bold text-primary bg-primary bg-opacity-10 px-3 rounded-2 mt-1">
                                <span>= PENJUALAN BERSIH (NET SALES)</span>
                                <span>{{ formatCurrency(metrics.net_sales) }}</span>
                            </div>
                        </div>

                        <!-- 2. HARGA POKOK PENJUALAN -->
                        <div class="mb-3">
                            <h6 class="fw-bold text-secondary text-uppercase mb-2">2. HARGA POKOK PENJUALAN (HPP / COGS)</h6>
                            <div class="d-flex justify-content-between border-bottom py-1.5 px-2 text-muted">
                                <span>(-) HPP Fisik Obat Terjual (Historical Batch Cost)</span>
                                <span class="fw-semibold">({{ formatCurrency(metrics.cogs) }})</span>
                            </div>
                            <div class="d-flex justify-content-between py-2 fw-bold text-dark bg-light px-3 rounded-2 mt-1 border">
                                <span>= LABA KOTOR (GROSS PROFIT)</span>
                                <span class="text-primary">{{ formatCurrency(metrics.gross_profit) }} (Margin: {{ metrics.gross_margin_percent }}%)</span>
                            </div>
                        </div>

                        <!-- 3. BIAYA OPERASIONAL -->
                        <div class="mb-3">
                            <h6 class="fw-bold text-danger text-uppercase mb-2">3. BIAYA OPERASIONAL (OPERATIONAL EXPENSES)</h6>
                            <div v-for="exp in expense_breakdown" :key="exp.category" class="d-flex justify-content-between border-bottom py-1.5 px-2 text-muted">
                                <span>(-) {{ exp.category }} ({{ exp.count }} Transaksi)</span>
                                <span class="text-danger">({{ formatCurrency(exp.total_amount) }}) [{{ exp.percentage }}%]</span>
                            </div>
                            <div v-if="!expense_breakdown || expense_breakdown.length === 0" class="p-3 rounded-3 bg-light text-center text-muted small border">
                                <i class="bx bx-info-circle me-1 text-primary"></i> Belum ada catatan biaya operasional pada periode ini.
                            </div>
                            <div class="d-flex justify-content-between py-2 fw-bold text-danger bg-danger bg-opacity-10 px-3 rounded-2 mt-2">
                                <span>= TOTAL BIAYA OPERASIONAL</span>
                                <span>({{ formatCurrency(metrics.total_expenses) }})</span>
                            </div>
                        </div>

                        <!-- 4. LABA BERSIH OPERASIONAL -->
                        <div class="p-3 rounded-3 d-flex justify-content-between align-items-center text-white" :class="metrics.net_profit >= 0 ? 'bg-success' : 'bg-danger'">
                            <div>
                                <h5 class="fw-bold mb-0">= LABA BERSIH OPERASIONAL (NET PROFIT)</h5>
                                <small class="text-white-50">Penjualan Bersih - HPP - Biaya Operasional</small>
                            </div>
                            <h3 class="fw-extrabold mb-0">{{ formatCurrency(metrics.net_profit) }}</h3>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: TABBED ANALYTICS & BREAKDOWN SECTION (Hidden in Print) -->
                <div class="card border-0 shadow-sm p-4 mb-4 rounded-3 bg-white no-print">
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs border-bottom mb-3">
                        <li class="nav-item">
                            <button class="nav-link fw-bold" :class="{ active: activeTab === 'sales_payment' }" @click="activeTab = 'sales_payment'">
                                <i class="bx bx-credit-card me-1"></i> Penjualan & Pembayaran
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-bold" :class="{ active: activeTab === 'products' }" @click="activeTab = 'products'">
                                <i class="bx bx-capsule me-1"></i> Performa HPP Produk
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-bold" :class="{ active: activeTab === 'top_bottom' }" @click="activeTab = 'top_bottom'">
                                <i class="bx bx-trophy me-1"></i> Top & Bottom Produk
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-bold" :class="{ active: activeTab === 'expenses' }" @click="activeTab = 'expenses'">
                                <i class="bx bx-receipt me-1"></i> Biaya Operasional
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-bold" :class="{ active: activeTab === 'cashier' }" @click="activeTab = 'cashier'">
                                <i class="bx bx-user me-1"></i> Kasir & Shift
                            </button>
                        </li>
                        <li class="nav-item" v-if="outlet_breakdown && outlet_breakdown.length > 0">
                            <button class="nav-link fw-bold" :class="{ active: activeTab === 'outlets' }" @click="activeTab = 'outlets'">
                                <i class="bx bx-store me-1"></i> Per Outlet
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-bold" :class="{ active: activeTab === 'cash_bank' }" @click="activeTab = 'cash_bank'">
                                <i class="bx bx-wallet me-1"></i> Saldo Kas vs Hutang/Piutang
                            </button>
                        </li>
                    </ul>

                    <!-- TAB 1: BREAKDOWN PENJUALAN & PEMBAYARAN -->
                    <div v-if="activeTab === 'sales_payment'">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border">
                                    <small class="text-muted d-block mb-1">Total Transaksi</small>
                                    <h4 class="fw-bold text-dark mb-0">{{ metrics.total_transactions }} Transaksi</h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border">
                                    <small class="text-muted d-block mb-1">Jumlah Item Obat Terjual</small>
                                    <h4 class="fw-bold text-dark mb-0">{{ metrics.total_items_sold }} Unit</h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border">
                                    <small class="text-muted d-block mb-1">Rata-rata Nilai Transaksi (Basket Size)</small>
                                    <h4 class="fw-bold text-primary mb-0">{{ formatCurrency(metrics.avg_transaction_value) }}</h4>
                                </div>
                            </div>
                        </div>

                        <h6 class="fw-bold text-dark mb-2"><i class="bx bx-pie-chart-alt me-1 text-primary"></i>Breakdown Metode Pembayaran</h6>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="bg-light text-dark small">
                                    <tr>
                                        <th>Metode Pembayaran</th>
                                        <th class="text-center">Jumlah Transaksi</th>
                                        <th class="text-end">Total Omzet</th>
                                        <th class="text-end">Persentase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="pm in payment_methods" :key="pm.method">
                                        <td class="fw-bold text-dark">
                                            <i :class="pm.method_raw === 'cash' ? 'bx bx-money me-1 text-success' : 'bx bx-credit-card me-1 text-primary'"></i>
                                            {{ pm.method }}
                                        </td>
                                        <td class="text-center font-monospace">{{ pm.count }}</td>
                                        <td class="text-end fw-bold text-dark">{{ formatCurrency(pm.total_amount) }}</td>
                                        <td class="text-end font-monospace fw-bold text-primary">{{ pm.percentage }}%</td>
                                    </tr>
                                    <tr v-if="!payment_methods || payment_methods.length === 0">
                                        <td colspan="4" class="text-center py-3 text-muted">Belum ada data pembayaran.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 2: BREAKDOWN HPP & LABA PER PRODUK -->
                    <div v-if="activeTab === 'products'">
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control form-control-sm" v-model="productSearch" placeholder="Cari Kode atau Nama Obat...">
                            </div>
                            <div class="col-md-3" v-if="categories && categories.length > 0">
                                <select class="form-select form-select-sm" v-model="productCategoryFilter">
                                    <option value="">Semua Kategori</option>
                                    <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" v-model="productSort">
                                    <option value="sales_desc">Penjualan Tertinggi</option>
                                    <option value="profit_desc">Laba Kotor Tertinggi</option>
                                    <option value="margin_desc">Margin % Tertinggi</option>
                                    <option value="margin_asc">Margin % Terendah</option>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="bg-light text-dark small">
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama Obat</th>
                                        <th>Kategori</th>
                                        <th class="text-center">Qty Terjual</th>
                                        <th class="text-end">Total Penjualan</th>
                                        <th class="text-end">Total HPP</th>
                                        <th class="text-end">Laba Kotor</th>
                                        <th class="text-end">Margin %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in filteredProducts" :key="p.kode">
                                        <td class="font-monospace text-muted small">{{ p.kode }}</td>
                                        <td class="fw-bold text-dark">{{ p.nama }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ p.kategori }}</span></td>
                                        <td class="text-center font-monospace fw-bold">{{ p.qty_sold }}</td>
                                        <td class="text-end fw-bold text-dark">{{ formatCurrency(p.total_sales) }}</td>
                                        <td class="text-end text-muted">{{ formatCurrency(p.cogs) }}</td>
                                        <td class="text-end fw-bold text-success">{{ formatCurrency(p.gross_profit) }}</td>
                                        <td class="text-end font-monospace fw-bold" :class="p.margin_percent < 15 ? 'text-danger' : 'text-primary'">
                                            {{ p.margin_percent }}%
                                        </td>
                                    </tr>
                                    <tr v-if="!filteredProducts || filteredProducts.length === 0">
                                        <td colspan="8" class="text-center py-4 text-muted">Obat tidak ditemukan.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 3: TOP & BOTTOM PRODUK -->
                    <div v-if="activeTab === 'top_bottom'">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3 border">
                                    <h6 class="fw-bold text-success mb-3"><i class="bx bx-trophy me-1"></i>Top 10 Produk Paling Menguntungkan</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Obat</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-end">Laba Kotor</th>
                                                    <th class="text-end">Margin</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="tp in top_products_profit" :key="tp.kode">
                                                    <td class="fw-bold text-dark small">{{ tp.nama }}</td>
                                                    <td class="text-center small font-monospace">{{ tp.qty_sold }}</td>
                                                    <td class="text-end small fw-bold text-success">{{ formatCurrency(tp.gross_profit) }}</td>
                                                    <td class="text-end small font-monospace text-primary fw-bold">{{ tp.margin_percent }}%</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3 border">
                                    <h6 class="fw-bold text-danger mb-3"><i class="bx bx-error-circle me-1"></i>Top Produk Margin Terendah</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Obat</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-end">Laba Kotor</th>
                                                    <th class="text-end">Margin</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="bp in bottom_products_margin" :key="bp.kode">
                                                    <td class="fw-bold text-dark small">{{ bp.nama }}</td>
                                                    <td class="text-center small font-monospace">{{ bp.qty_sold }}</td>
                                                    <td class="text-end small fw-bold text-dark">{{ formatCurrency(bp.gross_profit) }}</td>
                                                    <td class="text-end small font-monospace text-danger fw-bold">{{ bp.margin_percent }}%</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: BIAYA OPERASIONAL -->
                    <div v-if="activeTab === 'expenses'">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-dark mb-0"><i class="bx bx-receipt me-1 text-danger"></i>Rincian Pengeluaran Operasional per Kategori</h6>
                            <Link :href="route('finance.journals')" class="btn btn-sm btn-outline-danger">
                                <i class="bx bx-plus me-1"></i> Tambah Pengeluaran
                            </Link>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="bg-light text-dark small">
                                    <tr>
                                        <th>Kategori Biaya</th>
                                        <th class="text-center">Jumlah Transaksi</th>
                                        <th class="text-end">Total Pengeluaran</th>
                                        <th class="text-end">Persentase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="exp in expense_breakdown" :key="exp.category">
                                        <td class="fw-bold text-dark">{{ exp.category }}</td>
                                        <td class="text-center font-monospace">{{ exp.count }}</td>
                                        <td class="text-end fw-bold text-danger">{{ formatCurrency(exp.total_amount) }}</td>
                                        <td class="text-end font-monospace fw-bold text-muted">{{ exp.percentage }}%</td>
                                    </tr>
                                    <tr v-if="!expense_breakdown || expense_breakdown.length === 0">
                                        <td colspan="4" class="text-center py-4 text-muted">Belum ada pengeluaran operasional pada periode ini.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 5: KASIR & SHIFT -->
                    <div v-if="activeTab === 'cashier'">
                        <h6 class="fw-bold text-dark mb-2"><i class="bx bx-user me-1 text-primary"></i>Penjualan Berdasarkan Kasir</h6>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="bg-light text-dark small">
                                    <tr>
                                        <th>Nama Kasir</th>
                                        <th class="text-center">Total Transaksi</th>
                                        <th class="text-end">Penjualan Kotor</th>
                                        <th class="text-end">Diskon</th>
                                        <th class="text-end">Penjualan Bersih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="cb in cashier_breakdown" :key="cb.cashier_name">
                                        <td class="fw-bold text-dark">{{ cb.cashier_name }}</td>
                                        <td class="text-center font-monospace">{{ cb.total_transactions }}</td>
                                        <td class="text-end text-muted">{{ formatCurrency(cb.gross_sales) }}</td>
                                        <td class="text-end text-danger">({{ formatCurrency(cb.total_discount) }})</td>
                                        <td class="text-end fw-bold text-primary">{{ formatCurrency(cb.net_sales) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 6: PER OUTLET -->
                    <div v-if="activeTab === 'outlets'">
                        <h6 class="fw-bold text-dark mb-2"><i class="bx bx-store me-1 text-primary"></i>Performa Laba Rugi per Outlet</h6>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="bg-light text-dark small">
                                    <tr>
                                        <th>Nama Outlet</th>
                                        <th class="text-end">Penjualan Bersih</th>
                                        <th class="text-end">HPP</th>
                                        <th class="text-end">Laba Kotor</th>
                                        <th class="text-end">Biaya</th>
                                        <th class="text-end">Laba Bersih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="ob in outlet_breakdown" :key="ob.outlet_id">
                                        <td class="fw-bold text-dark">{{ ob.outlet_name }}</td>
                                        <td class="text-end fw-bold text-dark">{{ formatCurrency(ob.net_sales) }}</td>
                                        <td class="text-end text-muted">{{ formatCurrency(ob.cogs) }}</td>
                                        <td class="text-end text-primary fw-semibold">{{ formatCurrency(ob.gross_profit) }}</td>
                                        <td class="text-end text-danger">({{ formatCurrency(ob.total_expenses) }})</td>
                                        <td class="text-end fw-bold" :class="ob.net_profit >= 0 ? 'text-success' : 'text-danger'">{{ formatCurrency(ob.net_profit) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 7: POSITION SUMMARY (KAS/BANK VS HUTANG/PIUTANG) -->
                    <div v-if="activeTab === 'cash_bank'">
                        <div class="alert alert-info border-0 d-flex align-items-center mb-3">
                            <i class="bx bx-info-circle fs-4 me-2"></i>
                            <div>
                                <strong>Catatan Penting Akuntansi:</strong> Saldo Kas & Bank hanya menunjukkan jumlah likuiditas uang tunai yang tersedia, sedangkan Laba/Rugi dihitung dari Pendapatan dikurangi HPP dan Biaya.
                            </div>
                        </div>

                        <div class="row g-3">
                            <!-- Kas & Bank Cards -->
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3 border">
                                    <h6 class="fw-bold text-success mb-3"><i class="bx bx-wallet me-1"></i>Saldo Likuiditas Kas & Bank</h6>
                                    <div class="d-flex justify-content-between py-1.5 border-bottom">
                                        <span>Kas Tunai Laci / Brankas</span>
                                        <strong class="text-dark">{{ formatCurrency(financial_positions?.total_cash) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1.5 border-bottom">
                                        <span>Rekening Bank / Transfer</span>
                                        <strong class="text-dark">{{ formatCurrency(financial_positions?.total_bank) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1.5 border-bottom">
                                        <span>QRIS & E-Wallet Settlement</span>
                                        <strong class="text-dark">{{ formatCurrency((financial_positions?.total_qris || 0) + (financial_positions?.total_ewallet || 0)) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 bg-white px-2 rounded border mt-2 fw-bold text-success fs-6">
                                        <span>TOTAL KAS & BANK AVAILABLE</span>
                                        <span>{{ formatCurrency(financial_positions?.total_cash_bank) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Hutang & Piutang Cards -->
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3 border">
                                    <h6 class="fw-bold text-dark mb-3"><i class="bx bx-transfer-alt me-1 text-primary"></i>Ringkasan Hutang & Piutang</h6>
                                    <div class="d-flex justify-content-between py-1.5 border-bottom">
                                        <span>Hutang Supplier (Accounts Payable)</span>
                                        <strong class="text-danger">{{ formatCurrency(financial_positions?.total_payables) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1.5 border-bottom text-muted small">
                                        <span class="ps-2">↳ Hutang Terlambat Jatuh Tempo</span>
                                        <span class="text-danger fw-bold">{{ formatCurrency(financial_positions?.overdue_payables) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between py-1.5 border-bottom">
                                        <span>Piutang Customer (Accounts Receivable)</span>
                                        <strong class="text-primary">{{ formatCurrency(financial_positions?.total_receivables) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1.5 border-bottom text-muted small">
                                        <span class="ps-2">↳ Piutang Terlambat Jatuh Tempo</span>
                                        <span class="text-warning fw-bold">{{ formatCurrency(financial_positions?.overdue_receivables) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </LegacyLayout>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
    .print-container {
        margin: 0 !important;
        padding: 0 !important;
    }
    .content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    body {
        background: #fff !important;
        color: #000 !important;
    }
}

.print-only {
    display: none;
}
</style>
