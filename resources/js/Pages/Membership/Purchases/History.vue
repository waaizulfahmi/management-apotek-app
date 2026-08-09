<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    purchases: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');

const handleSearch = () => {
    router.get(route('membership.purchases.index'), { search: search.value }, { preserveState: true });
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-receipt text-primary me-2"></i>Riwayat Transaksi Pembelian Member</h2>
                        <p class="text-muted small mb-0">Daftar seluruh invoice transaksi penjualan POS yang terhubung dengan akun member</p>
                    </div>
                </div>

                <!-- Filter -->
                <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
                    <div class="row g-2">
                        <div class="col-md-10">
                            <input type="text" class="form-control bg-light" placeholder="Cari nomor invoice, nama member, no HP..." v-model="search" @keyup.enter="handleSearch">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" @click="handleSearch">Cari</button>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">No. Invoice</th>
                                        <th>Nama Member</th>
                                        <th>Tanggal Sale</th>
                                        <th>Metode Pembayaran</th>
                                        <th>Total Pembelian</th>
                                        <th class="pe-4 text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in purchases.data" :key="p.id">
                                        <td class="ps-4 font-monospace fw-bold text-primary">{{ p.invoice_number }}</td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ p.customer_name }}</div>
                                            <small class="text-muted">{{ p.customer_code }} | {{ p.customer_phone }}</small>
                                        </td>
                                        <td>{{ formatDate(p.sale_date) }}</td>
                                        <td><span class="badge bg-info text-dark text-uppercase">{{ p.payment_method }}</span></td>
                                        <td class="fw-bold text-success fs-6">{{ formatRupiah(p.grand_total) }}</td>
                                        <td class="pe-4 text-end"><span class="badge bg-success">{{ p.status }}</span></td>
                                    </tr>
                                    <tr v-if="!purchases.data || purchases.data.length === 0">
                                        <td colspan="6" class="text-center py-5 text-muted">Belum ada data transaksi pembelian member.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 py-3">
                        <Pagination :links="purchases.links" />
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
