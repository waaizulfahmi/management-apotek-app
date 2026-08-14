<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
                            <input type="date" v-model="startDate" class="form-control">
                            <span class="text-muted">s/d</span>
                            <input type="date" v-model="endDate" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Report Table -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
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
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(r, idx) in reports.data" :key="idx">
                                    <td class="fw-bold text-dark">{{ r.cashier_name }}</td>
                                    <td>
                                        <div class="fw-bold text-primary">{{ r.shift_name || 'Standar' }}</div>
                                        <small class="text-muted">{{ r.outlet_name || 'Utama' }}</small>
                                    </td>
                                    <td class="text-center"><span class="badge bg-secondary fs-6">{{ r.total_transactions }}</span></td>
                                    <td class="text-end fw-bold text-dark">{{ formatCurrency(r.total_sales) }}</td>
                                    <td class="text-end text-success font-monospace">{{ formatCurrency(r.cash_sales) }}</td>
                                    <td class="text-end text-info font-monospace">{{ formatCurrency(r.qris_sales) }}</td>
                                    <td class="text-end text-muted font-monospace">{{ formatCurrency(r.debit_sales) }}</td>
                                    <td class="text-end text-muted font-monospace">{{ formatCurrency(r.transfer_sales) }}</td>
                                    <td class="text-end text-danger font-monospace">-{{ formatCurrency(r.refund_amount) }}</td>
                                    <td class="text-end fw-bold text-primary fs-6">{{ formatCurrency(r.net_sales) }}</td>
                                </tr>
                                <tr v-if="!reports.data || reports.data.length === 0">
                                    <td colspan="10" class="text-center py-4 text-muted">Belum ada data penjualan kasir yang ditemukan.</td>
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
    </LegacyLayout>
</template>
