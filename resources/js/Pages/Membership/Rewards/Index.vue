<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    rewards: Array,
    redemptions: Array,
});

const formRedeem = useForm({
    customer_id: '',
    reward_id: '',
});

const openRedeemModal = (reward) => {
    formRedeem.reward_id = reward.id;
    const modalEl = document.getElementById('redeemModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
};

const submitRedeem = () => {
    formRedeem.post(route('membership.rewards.redeem'), {
        onSuccess: () => {
            formRedeem.reset();
            const modalEl = document.getElementById('redeemModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
        }
    });
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
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-gift text-primary me-2"></i>Katalog Point & Reward Member</h2>
                        <p class="text-muted small mb-0">Tukarkan poin member dengan voucher potongan belanja dan hadiah spesial apotek</p>
                    </div>
                </div>

                <!-- Reward Catalog Grid -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4" v-for="r in rewards" :key="r.id">
                        <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                                    <i class="bx bx-purchase-tag-alt fs-2"></i>
                                </div>
                                <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill fs-6">
                                    {{ r.required_points.toLocaleString() }} Pts
                                </span>
                            </div>
                            <h5 class="fw-bold text-dark mb-1">{{ r.name }}</h5>
                            <p class="text-muted small mb-3 flex-grow-1">{{ r.description }}</p>

                            <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                <div>
                                    <small class="text-muted d-block">NILAI REWARD</small>
                                    <strong class="text-success fs-5">{{ formatRupiah(r.reward_value) }}</strong>
                                </div>
                                <button class="btn btn-primary rounded-pill shadow-xs" @click="openRedeemModal(r)">
                                    <i class="bx bx-gift me-1"></i>Tukar Poin
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Redemptions Table -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <h5 class="fw-bold text-dark mb-0"><i class="bx bx-receipt text-success me-2"></i>Klaim Reward Terakhir</h5>
                    </div>
                    <div class="card-body p-0 mt-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Kode Klaim</th>
                                        <th>Member</th>
                                        <th>Nama Reward</th>
                                        <th>Poin Digunakan</th>
                                        <th>Tanggal Tukar</th>
                                        <th>Status Klaim</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="rd in redemptions" :key="rd.id">
                                        <td class="ps-4 font-monospace fw-bold text-primary">{{ rd.redemption_code }}</td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ rd.customer?.name }}</div>
                                            <small class="text-muted">{{ rd.customer?.code }}</small>
                                        </td>
                                        <td>{{ rd.reward?.name }}</td>
                                        <td class="fw-bold text-danger">-{{ rd.points_used }} Pts</td>
                                        <td>{{ formatDate(rd.redeemed_at) }}</td>
                                        <td><span class="badge bg-success">{{ rd.status }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Redeem Modal -->
        <div class="modal fade" id="redeemModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold text-dark">Penukaran Poin Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitRedeem">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">ID Customer / Member ID <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" v-model="formRedeem.customer_id" required placeholder="Masukkan ID Pelanggan (contoh: 1)">
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="formRedeem.processing">Konfirmasi Tukar Poin</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
