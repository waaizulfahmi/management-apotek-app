<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';

const props = defineProps({
    sales: Array,
    expiredAlerts: Array,
    lowStockAlerts: Array,
    filters: Object,
});

const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');

const applyFilter = () => {
    router.get(route('reports.index'), { start_date: startDate.value, end_date: endDate.value }, { preserveState: true });
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const printReport = () => {
    window.print();
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark mb-0"><i class="bx bx-bar-chart-alt-2 text-primary me-2"></i>Laporan & Pusat Peringatan Alert System</h2>
                    <button class="btn btn-outline-primary shadow-sm" @click="printReport">
                        <i class="bx bx-printer"></i> Print / Cetak Laporan
                    </button>
                </div>

                <!-- Warning Alert Cards: Low Stock & Expired -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-warning shadow-sm p-3 rounded-3 bg-white">
                            <h6 class="fw-bold text-warning mb-2"><i class="bx bx-error me-2"></i>Peringatan Obat Hampir Expired (< 60 Hari)</h6>
                            <ul class="list-group list-group-flush small" style="max-height: 150px; overflow-y: auto;">
                                <li class="list-group-item d-flex justify-content-between align-items-center" v-for="ex in expiredAlerts" :key="ex.id">
                                    <span>{{ ex.medicine_name }} (Batch: {{ ex.batch_number }})</span>
                                    <span class="badge bg-danger">Expired: {{ ex.expired_date }} (Stok: {{ ex.stock }})</span>
                                </li>
                                <li v-if="expiredAlerts.length === 0" class="list-group-item text-muted text-center py-2">
                                    Tidak ada obat hampir expired.
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card border-danger shadow-sm p-3 rounded-3 bg-white">
                            <h6 class="fw-bold text-danger mb-2"><i class="bx bx-package me-2"></i>Peringatan Stok Menipis (Stok <= 10)</h6>
                            <ul class="list-group list-group-flush small" style="max-height: 150px; overflow-y: auto;">
                                <li class="list-group-item d-flex justify-content-between align-items-center" v-for="ls in lowStockAlerts" :key="ls.kode">
                                    <span>{{ ls.nama }} ({{ ls.kode }})</span>
                                    <span class="badge bg-warning text-dark">Sisa Stok: {{ ls.stok }}</span>
                                </li>
                                <li v-if="lowStockAlerts.length === 0" class="list-group-item text-muted text-center py-2">
                                    Stok obat aman.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Sales Report Filter & Table -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">Laporan Penjualan Pembayaran</h5>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="date" class="form-control form-control-sm" v-model="startDate">
                            <span>s/d</span>
                            <input type="date" class="form-control form-control-sm" v-model="endDate">
                            <button class="btn btn-primary btn-sm" @click="applyFilter">Filter</button>
                        </div>
                    </div>

                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>No. Invoice</th>
                                <th>Kasir</th>
                                <th>Pelanggan</th>
                                <th>Tanggal Penjualan</th>
                                <th>Metode Bayar</th>
                                <th class="text-end">Total Penjualan</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in sales" :key="s.id">
                                <td class="fw-bold text-primary">{{ s.invoice_number }}</td>
                                <td>{{ s.cashier_name }}</td>
                                <td>{{ s.customer_name || 'Umum' }}</td>
                                <td>{{ s.sale_date }}</td>
                                <td><span class="badge bg-info text-dark">{{ s.payment_method.toUpperCase() }}</span></td>
                                <td class="text-end fw-bold">{{ formatCurrency(s.grand_total) }}</td>
                                <td class="text-center">
                                    <Link :href="route('returns.create', { type: 'sale', reference_id: s.id })" class="btn btn-sm btn-outline-danger">
                                        RETUR
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!sales || sales.length === 0">
                                <td colspan="7" class="text-center py-4 text-muted">Tidak ada data penjualan pada rentang tanggal ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
