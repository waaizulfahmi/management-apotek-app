<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';

const props = defineProps({
    metrics: Object,
    topExpenses: Array,
    accounts: Array,
    filters: Object,
});

const selectedRange = ref(props.filters?.range || 'month');
const customStartDate = ref(props.filters?.start_date || '');
const customEndDate = ref(props.filters?.end_date || '');

const handleFilterChange = () => {
    router.get(route('finance.index'), {
        range: selectedRange.value,
        start_date: customStartDate.value,
        end_date: customEndDate.value,
    }, { preserveState: true, replace: true });
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <!-- Header & Quick Nav -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-dollar-circle text-primary me-2"></i>Dashboard Pengelolaan Keuangan</h2>
                        <p class="text-muted small mb-0">Pengawasan Real-Time Saldo, Laba Rugi, Hutang & Piutang Apotek</p>
                    </div>

                    <!-- Filter Control Bar -->
                    <div class="d-flex align-items-center gap-2 bg-white p-2 rounded-3 shadow-xs border">
                        <i class="bx bx-filter-alt text-primary ms-1"></i>
                        <select class="form-select form-select-sm border-0" v-model="selectedRange" @change="handleFilterChange" style="width: 140px; font-weight: 500;">
                            <option value="today">Hari Ini</option>
                            <option value="week">Minggu Ini</option>
                            <option value="month">Bulan Ini</option>
                            <option value="year">Tahun Ini</option>
                            <option value="custom">Custom Date</option>
                        </select>
                    </div>
                </div>

                <!-- Financial Quick Action Bar -->
                <div class="d-flex gap-2 mb-4 flex-wrap">
                    <Link :href="route('finance.accounts')" class="btn btn-outline-primary btn-sm fw-bold">
                        <i class="bx bx-wallet me-1"></i> Kas & Bank
                    </Link>
                    <Link :href="route('finance.payables')" class="btn btn-outline-danger btn-sm fw-bold">
                        <i class="bx bx-file me-1"></i> Hutang Supplier ({{ formatCurrency(metrics.total_payable) }})
                    </Link>
                    <Link :href="route('finance.receivables')" class="btn btn-outline-warning btn-sm text-dark fw-bold">
                        <i class="bx bx-money me-1"></i> Piutang Customer ({{ formatCurrency(metrics.total_receivable) }})
                    </Link>
                    <Link :href="route('finance.journals')" class="btn btn-outline-info btn-sm text-dark fw-bold">
                        <i class="bx bx-book me-1"></i> Jurnal Keuangan
                    </Link>
                    <Link :href="route('finance.profit_loss')" class="btn btn-outline-success btn-sm fw-bold">
                        <i class="bx bx-chart me-1"></i> Laba Rugi
                    </Link>
                </div>

                <!-- 4 Key Balance Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3">
                            <span class="text-muted small">Total Omzet Pendapatan</span>
                            <h4 class="fw-bold text-primary mb-0">{{ formatCurrency(metrics.total_revenue) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3">
                            <span class="text-muted small">HPP FEFO Obat Terjual</span>
                            <h4 class="fw-bold text-muted mb-0">{{ formatCurrency(metrics.total_cogs) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3">
                            <span class="text-muted small">Laba Kotor (Gross Profit)</span>
                            <h4 class="fw-bold text-success mb-0">{{ formatCurrency(metrics.gross_profit) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3">
                            <span class="text-muted small">Laba Bersih (Net Profit)</span>
                            <h3 class="fw-bold mb-0" :class="metrics.net_profit >= 0 ? 'text-success' : 'text-danger'">
                                {{ formatCurrency(metrics.net_profit) }}
                            </h3>
                        </div>
                    </div>
                </div>

                <!-- Kas/Bank Balances & Top Expenses -->
                <div class="row">
                    <!-- Cash & Bank Accounts Cards -->
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-xs p-4 bg-white rounded-3 h-100">
                            <h5 class="fw-bold mb-3 text-dark"><i class="bx bx-credit-card me-2 text-primary"></i>Saldo Kas & Bank Real-Time</h5>
                            <div class="list-group list-group-flush">
                                <div v-for="acc in accounts" :key="acc.id" class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark">{{ acc.name }}</h6>
                                        <small class="text-muted">{{ acc.account_code }} | {{ acc.account_number || 'Tunai Kasir' }}</small>
                                    </div>
                                    <h5 class="fw-bold text-primary mb-0">{{ formatCurrency(acc.current_balance) }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Operational Expense Breakdown -->
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-xs p-4 bg-white rounded-3 h-100">
                            <h5 class="fw-bold mb-3 text-dark"><i class="bx bx-pie-chart-alt me-2 text-danger"></i>Breakdown Biaya Operasional</h5>
                            <table class="table table-hover align-middle small">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Kategori Biaya</th>
                                        <th class="text-end">Jumlah Pengeluaran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="exp in topExpenses" :key="exp.category">
                                        <td class="fw-bold text-dark">{{ exp.category }}</td>
                                        <td class="text-end fw-bold text-danger">{{ formatCurrency(exp.total) }}</td>
                                    </tr>
                                    <tr v-if="!topExpenses || topExpenses.length === 0">
                                        <td colspan="2" class="text-center py-3 text-muted">Belum ada pengeluaran operasional recorded.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
