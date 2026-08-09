<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    tiers: Array,
});

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-crown text-warning me-2"></i>Tier Membership & Benefit Privilese</h2>
                        <p class="text-muted small mb-0">Tingkatan member (Bronze, Silver, Gold, Platinum), threshold minimal belanja, multiplier poin, dan diskon</p>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-3" v-for="t in tiers" :key="t.id">
                        <div class="card border-0 shadow-sm rounded-4 text-center p-4 h-100">
                            <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center text-white" :style="{ backgroundColor: t.badge_color, width: '64px', height: '64px' }">
                                <i class="bx bx-crown fs-2"></i>
                            </div>

                            <h4 class="fw-bold text-dark mb-1">{{ t.name }}</h4>
                            <p class="text-muted small mb-3">{{ t.description }}</p>

                            <div class="bg-light p-3 rounded-3 mb-3 text-start">
                                <small class="text-muted d-block">THRESHOLD MINIMAL BELANJA:</small>
                                <strong class="text-primary fs-6">{{ formatRupiah(t.min_spending) }}</strong>

                                <small class="text-muted d-block mt-2">MULTIPLIER POIN:</small>
                                <strong class="text-success fs-6">{{ t.point_multiplier }}x Poin</strong>

                                <small class="text-muted d-block mt-2">DISKON KHUSUS MEMBER:</small>
                                <strong class="text-warning text-dark fs-6">{{ t.discount_percentage }}% Off</strong>
                            </div>

                            <div class="mt-auto">
                                <span class="badge bg-secondary px-3 py-2 rounded-pill">{{ t.customers_count || 0 }} Member Terdaftar</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
