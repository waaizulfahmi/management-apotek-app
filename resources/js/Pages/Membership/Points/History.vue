<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    transactions: Object,
    filters: Object,
});

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-history text-primary me-2"></i>Riwayat Transaksi Poin Member</h2>
                        <p class="text-muted small mb-0">Audit trail perolehan (earn), penukaran (redeem), bonus, dan penyesuaian poin</p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Tanggal & Waktu</th>
                                        <th>Nama Member</th>
                                        <th>Tipe Transaksi</th>
                                        <th>Deskripsi / Ref</th>
                                        <th>Poin Masuk</th>
                                        <th>Poin Keluar</th>
                                        <th class="pe-4 text-end">Saldo Akhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="t in transactions.data" :key="t.id">
                                        <td class="ps-4 text-muted small">{{ formatDate(t.created_at) }}</td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ t.customer?.name || 'Member' }}</div>
                                            <small class="text-muted">{{ t.customer?.code }} | {{ t.customer?.phone }}</small>
                                        </td>
                                        <td>
                                            <span class="badge" :class="{
                                                'bg-success': t.points_in > 0,
                                                'bg-danger': t.points_out > 0,
                                                'bg-warning text-dark': t.transaction_type === 'EXPIRED'
                                            }">
                                                {{ t.transaction_type }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="text-dark">{{ t.description }}</div>
                                            <small v-if="t.reference_id" class="text-primary font-monospace">Ref: {{ t.reference_id }}</small>
                                        </td>
                                        <td class="fw-bold text-success">{{ t.points_in > 0 ? '+' + t.points_in : '-' }}</td>
                                        <td class="fw-bold text-danger">{{ t.points_out > 0 ? '-' + t.points_out : '-' }}</td>
                                        <td class="pe-4 text-end fw-bold text-dark fs-6">{{ t.balance_after.toLocaleString() }} Pts</td>
                                    </tr>
                                    <tr v-if="!transactions.data || transactions.data.length === 0">
                                        <td colspan="7" class="text-center py-5 text-muted">Belum ada riwayat transaksi poin.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 py-3">
                        <Pagination :links="transactions.links" />
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
