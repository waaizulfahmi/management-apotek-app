<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    revenue: Number,
    cogs: Number,
    gross_profit: Number,
    expenses: Array,
    total_expenses: Number,
    net_profit: Number,
    filters: Object,
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark mb-0"><i class="bx bx-chart text-success me-2"></i>Laporan Laba Rugi (Profit & Loss Statement)</h2>
                    <span class="text-muted small">Periode: {{ filters.start_date }} s/d {{ filters.end_date }}</span>
                </div>

                <div class="card border-0 shadow-sm p-4 bg-white rounded-3">
                    <!-- Revenue Section -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-primary text-uppercase mb-2">1. PENDAPATAN (REVENUE)</h6>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span>Pendapatan Omzet Penjualan Obat</span>
                            <span class="fw-bold">{{ formatCurrency(revenue) }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-2 fw-bold text-primary bg-light px-2 rounded">
                            <span>TOTAL PENDAPATAN</span>
                            <span>{{ formatCurrency(revenue) }}</span>
                        </div>
                    </div>

                    <!-- COGS Section -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-muted text-uppercase mb-2">2. HARGA POKOK PENJUALAN (HPP / COGS FEFO)</h6>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span>HPP Fisik Obat Terjual (Batch Cost)</span>
                            <span class="fw-bold text-muted">({{ formatCurrency(cogs) }})</span>
                        </div>
                        <div class="d-flex justify-content-between py-2 fw-bold text-muted bg-light px-2 rounded">
                            <span>TOTAL HPP</span>
                            <span>({{ formatCurrency(cogs) }})</span>
                        </div>
                    </div>

                    <!-- Gross Profit Summary -->
                    <div class="p-3 bg-light-primary border border-primary rounded-3 mb-4 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-primary">LABA KOTOR (GROSS PROFIT)</h5>
                        <h4 class="fw-bold mb-0 text-primary">{{ formatCurrency(gross_profit) }}</h4>
                    </div>

                    <!-- Operating Expenses Section -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-danger text-uppercase mb-2">3. BIAYA OPERASIONAL (OPERATIONAL EXPENSES)</h6>
                        <div v-for="exp in expenses" :key="exp.id" class="d-flex justify-content-between border-bottom py-2">
                            <span>{{ exp.category }} — {{ exp.description || 'Operasional' }}</span>
                            <span class="fw-bold text-danger">({{ formatCurrency(exp.amount) }})</span>
                        </div>
                        <div v-if="!expenses || expenses.length === 0" class="text-muted py-2 small">
                            Tidak ada pengeluaran operasional pada periode ini.
                        </div>
                        <div class="d-flex justify-content-between py-2 fw-bold text-danger bg-light px-2 rounded mt-2">
                            <span>TOTAL BIAYA OPERASIONAL</span>
                            <span>({{ formatCurrency(total_expenses) }})</span>
                        </div>
                    </div>

                    <!-- Net Profit Summary -->
                    <div class="p-4 rounded-3 d-flex justify-content-between align-items-center" :class="net_profit >= 0 ? 'bg-success text-white' : 'bg-danger text-white'">
                        <div>
                            <h4 class="fw-bold mb-0">LABA BERSIH (NET PROFIT)</h4>
                            <small>Pendapatan - HPP - Biaya Operasional</small>
                        </div>
                        <h2 class="fw-bold mb-0">{{ formatCurrency(net_profit) }}</h2>
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
