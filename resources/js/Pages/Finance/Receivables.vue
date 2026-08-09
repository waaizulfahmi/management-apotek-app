<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    receivables: Object,
    cashAccounts: Array,
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <h2 class="fw-bold text-dark mb-4"><i class="bx bx-money text-warning me-2"></i>Manajemen Piutang Customer (Accounts Receivable)</h2>

                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>No. Piutang / Inv</th>
                                <th>Nama Pelanggan</th>
                                <th>Jatuh Tempo</th>
                                <th class="text-end">Total Piutang</th>
                                <th class="text-end">Telah Dibayar</th>
                                <th class="text-end">Sisa Piutang</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in receivables.data" :key="r.id">
                                <td class="fw-bold text-primary">{{ r.receivable_number }}<br><small class="text-muted">{{ r.invoice_number }}</small></td>
                                <td class="fw-bold">{{ r.customer_name || 'Pelanggan Umum' }}</td>
                                <td><span class="badge bg-light text-warning border">{{ r.due_date }}</span></td>
                                <td class="text-end">{{ formatCurrency(r.total_amount) }}</td>
                                <td class="text-end text-success">{{ formatCurrency(r.paid_amount) }}</td>
                                <td class="text-end fw-bold text-warning">{{ formatCurrency(r.remaining_amount) }}</td>
                                <td>
                                    <span class="badge" :class="{
                                        'bg-danger': r.status === 'unpaid',
                                        'bg-warning text-dark': r.status === 'partial',
                                        'bg-success': r.status === 'paid'
                                    }">
                                        {{ r.status.toUpperCase() }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!receivables.data || receivables.data.length === 0">
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada piutang pelanggan recorded.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
