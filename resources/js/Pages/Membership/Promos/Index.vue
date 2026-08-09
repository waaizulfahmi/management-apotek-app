<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    campaigns: Array,
});

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
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-star text-warning me-2"></i>Campaign & Promo Khusus Member</h2>
                        <p class="text-muted small mb-0">Program Double Point, Triple Point Weekend, dan Campaign Promo Spesial</p>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6" v-for="c in campaigns" :key="c.id">
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-gradient text-dark">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-warning text-dark fw-bold px-3 py-2 fs-6 rounded-pill">
                                    ⚡ MULTIPLIER {{ c.multiplier }}x POIN
                                </span>
                                <span class="badge bg-success">{{ c.is_active ? 'AKTIF BERJALAN' : 'ENDED' }}</span>
                            </div>

                            <h4 class="fw-bold text-dark mb-2 mt-2">{{ c.title }}</h4>
                            <p class="text-muted small mb-3">{{ c.description || 'Syarat & Ketentuan berlaku untuk transaksi member' }}</p>

                            <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                <div>
                                    <small class="text-muted d-block">PERIODE CAMPAIGN</small>
                                    <strong class="text-primary">{{ formatDate(c.start_date) }} - {{ formatDate(c.end_date) }}</strong>
                                </div>
                                <span class="badge bg-info text-dark">Target: {{ c.target_tier }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="!campaigns || campaigns.length === 0" class="col-12 text-center py-5 text-muted">
                        Belum ada Campaign Promo yang dibuat.
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
