<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, watch } from 'vue';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const props = defineProps({
    total_kasir: Number,
    total_obat: Number,
    total_transaksi: Number,
    total_pendapatan: Number,
    salesChart: Object,
    topSellingChart: Object,
    categoryChart: Object,
    recentSales: Array,
    filters: Object,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const selectedRange = ref(props.filters?.range || 'month');
const customStartDate = ref(props.filters?.start_date || '');
const customEndDate = ref(props.filters?.end_date || '');

const handleFilterChange = () => {
    router.get(route('admin.dashboard'), {
        range: selectedRange.value,
        start_date: customStartDate.value,
        end_date: customEndDate.value,
    }, { preserveState: true, replace: true });
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

// Chart Canvas References
const salesCanvas = ref(null);
const topSellingCanvas = ref(null);
const categoryCanvas = ref(null);

let salesChartInstance = null;
let topSellingInstance = null;
let categoryInstance = null;

const renderCharts = () => {
    // 1. Sales Trend Line Chart
    if (salesCanvas.value) {
        if (salesChartInstance) salesChartInstance.destroy();
        salesChartInstance = new Chart(salesCanvas.value, {
            type: 'line',
            data: {
                labels: props.salesChart.labels.length ? props.salesChart.labels : ['Hari Ini'],
                datasets: [{
                    label: 'Pendapatan Penjualan (Rp)',
                    data: props.salesChart.data.length ? props.salesChart.data : [props.total_pendapatan],
                    borderColor: '#3b6bff',
                    backgroundColor: 'rgba(59, 107, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // 2. Top Selling Medicines Bar Chart
    if (topSellingCanvas.value) {
        if (topSellingInstance) topSellingInstance.destroy();
        topSellingInstance = new Chart(topSellingCanvas.value, {
            type: 'bar',
            data: {
                labels: props.topSellingChart.labels.length ? props.topSellingChart.labels : ['Belum Ada Transaksi'],
                datasets: [{
                    label: 'Jumlah Terjual (Qty)',
                    data: props.topSellingChart.data.length ? props.topSellingChart.data : [0],
                    backgroundColor: ['#47b9f8', '#ff7043', '#4caf50', '#ffb74d', '#ab47bc'],
                    borderRadius: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // 3. Category Doughnut Chart
    if (categoryCanvas.value) {
        if (categoryInstance) categoryInstance.destroy();
        categoryInstance = new Chart(categoryCanvas.value, {
            type: 'doughnut',
            data: {
                labels: props.categoryChart.labels,
                datasets: [{
                    data: props.categoryChart.data,
                    backgroundColor: ['#3b6bff', '#ff7043', '#4caf50', '#ab47bc', '#ffa726'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
            }
        });
    }
};

onMounted(() => {
    renderCharts();
});

watch(() => props.salesChart, () => {
    renderCharts();
}, { deep: true });

const confirmLogout = () => {
    if (confirm("Apakah Anda yakin ingin keluar?")) {
        router.post(route('logout'));
    }
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <!-- Header & Filter Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold text-dark mb-0">Dashboard Analytics Admin</h2>
                        <p class="text-muted small mb-0">Statistik Penjualan & Performa Real-Time</p>
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

                        <template v-if="selectedRange === 'custom'">
                            <input type="date" class="form-control form-control-sm border-0" v-model="customStartDate" @change="handleFilterChange">
                            <span>s/d</span>
                            <input type="date" class="form-control form-control-sm border-0" v-model="customEndDate" @change="handleFilterChange">
                        </template>

                        <button class="btn btn-outline-danger btn-sm rounded-circle p-2 ms-2" @click="confirmLogout">
                            <i class="bx bx-exit" style="font-size: 1.2rem;"></i>
                        </button>
                    </div>
                </div>

                <!-- 4 Key Metric Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card shadow-xs p-3 bg-white">
                            <div class="d-flex align-items-center">
                                <i class="bx bxs-user-circle" style="font-size: 34px; color: #3b6bff;"></i>
                                <div class="ms-3">
                                    <h6 class="text-muted mb-0 small">Total Kasir</h6>
                                    <h4 class="fw-bold mb-0 text-dark">{{ total_kasir }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card shadow-xs p-3 bg-white">
                            <div class="d-flex align-items-center">
                                <i class="bx bxs-capsule" style="font-size: 34px; color: #ff7043;"></i>
                                <div class="ms-3">
                                    <h6 class="text-muted mb-0 small">Total Obat</h6>
                                    <h4 class="fw-bold mb-0 text-dark">{{ total_obat }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card shadow-xs p-3 bg-white">
                            <div class="d-flex align-items-center">
                                <i class="bx bxs-cart" style="font-size: 34px; color: #4caf50;"></i>
                                <div class="ms-3">
                                    <h6 class="text-muted mb-0 small">Transaksi (Filter)</h6>
                                    <h4 class="fw-bold mb-0 text-dark">{{ total_transaksi }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card shadow-xs p-3 bg-white">
                            <div class="d-flex align-items-center">
                                <i class="bx bxs-wallet" style="font-size: 34px; color: #3b6bff;"></i>
                                <div class="ms-3">
                                    <h6 class="text-muted mb-0 small">Pendapatan (Filter)</h6>
                                    <h5 class="fw-bold mb-0 text-primary">{{ formatCurrency(total_pendapatan) }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row mb-4">
                    <!-- Line Chart: Sales Trend -->
                    <div class="col-md-8 mb-4">
                        <div class="card border-0 shadow-xs p-3 bg-white h-100">
                            <h6 class="fw-bold mb-3 text-dark"><i class="bx bx-line-chart me-2 text-primary"></i>Grafik Tren Pendapatan Penjualan</h6>
                            <div style="height: 280px;">
                                <canvas ref="salesCanvas"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Doughnut Chart: Categories -->
                    <div class="col-md-4 mb-4">
                        <div class="card border-0 shadow-xs p-3 bg-white h-100">
                            <h6 class="fw-bold mb-3 text-dark"><i class="bx bx-pie-chart-alt-2 me-2 text-success"></i>Distribusi Kategori Obat</h6>
                            <div style="height: 280px;">
                                <canvas ref="categoryCanvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Bar Chart: Top Selling Medicines -->
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-xs p-3 bg-white h-100">
                            <h6 class="fw-bold mb-3 text-dark"><i class="bx bx-trending-up me-2 text-warning"></i>5 Produk Obat Paling Laku</h6>
                            <div style="height: 250px;">
                                <canvas ref="topSellingCanvas"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Transactions Table -->
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-xs p-3 bg-white h-100">
                            <h6 class="fw-bold mb-3 text-dark"><i class="bx bx-history me-2 text-info"></i>5 Transaksi Terbaru</h6>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm align-middle small">
                                    <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Kasir</th>
                                            <th>Status</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="sale in recentSales" :key="sale.id">
                                            <td class="fw-bold text-primary">{{ sale.invoice_number }}</td>
                                            <td>{{ sale.cashier_name }}</td>
                                            <td><span class="badge bg-success">Lunas</span></td>
                                            <td class="text-end fw-bold">{{ formatCurrency(sale.grand_total) }}</td>
                                        </tr>
                                        <tr v-if="!recentSales || recentSales.length === 0">
                                            <td colspan="4" class="text-center py-3 text-muted">Belum ada transaksi.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
