<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    vouchers: Array,
});

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'Tanpa Batas';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-purchase-tag-alt text-primary me-2"></i>Voucher Diskon Member POS</h2>
                        <p class="text-muted small mb-0">Kelola kode voucher diskon yang dapat digunakan member saat belanja di Kasir POS</p>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6" v-for="v in vouchers" :key="v.id">
                        <div class="card border-0 shadow-sm rounded-4 p-4 position-relative overflow-hidden">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-primary fs-6 font-monospace px-3 py-2 rounded-3">{{ v.code }}</span>
                                <span class="badge" :class="v.is_active ? 'bg-success' : 'bg-secondary'">{{ v.is_active ? 'AKTIF' : 'NON-AKTIF' }}</span>
                            </div>

                            <h5 class="fw-bold text-dark mb-1">{{ v.name }}</h5>
                            <div class="fs-4 fw-bold text-success mb-3">{{ formatRupiah(v.value) }}</div>

                            <div class="bg-light p-3 rounded-3 mb-3">
                                <div class="row g-2 small">
                                    <div class="col-6">
                                        <span class="text-muted">Min Belanja:</span>
                                        <div class="fw-bold text-dark">{{ formatRupiah(v.min_purchase) }}</div>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">Maksimal Diskon:</span>
                                        <div class="fw-bold text-dark">{{ v.max_discount ? formatRupiah(v.max_discount) : 'Sesuai Nominal' }}</div>
                                    </div>
                                    <div class="col-6 mt-2">
                                        <span class="text-muted">Tier Eligible:</span>
                                        <div class="fw-bold text-primary">{{ v.applicable_tier }}</div>
                                    </div>
                                    <div class="col-6 mt-2">
                                        <span class="text-muted">Periode Aktif:</span>
                                        <div class="fw-bold text-dark">{{ formatDate(v.start_date) }} - {{ formatDate(v.end_date) }}</div>
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
