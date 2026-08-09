<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';

const props = defineProps({
    opname: Object,
    items: Array,
    summary: Object,
});

const filterOnlyDiff = ref(true);

const filteredItems = computed(() => {
    if (filterOnlyDiff.value) {
        return props.items.filter(i => i.difference !== 0);
    }
    return props.items;
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const handleApprove = () => {
    showConfirm(
        "Approve & Finalize Stok Opname",
        `Apakah Anda yakin ingin menyetujui (Approve & Finalize) Stok Opname ${props.opname.opname_number}? Stok obat di sistem utama akan langsung di-adjust!`,
        () => {
            router.post(route('inventory.opname.approve', props.opname.id));
        }
    );
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <span class="badge bg-warning text-dark mb-1">REVIEW & APPROVAL STOK OPNAME</span>
                        <h3 class="fw-bold text-dark mb-0">{{ opname.opname_number }}</h3>
                        <p class="text-muted small mb-0">Gudang: <strong>{{ opname.warehouse_name }}</strong> | Tanggal: {{ opname.opname_date }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <Link :href="route('inventory.opname')" class="btn btn-outline-secondary">
                            ← Kembali ke Daftar SO
                        </Link>
                        <template v-if="opname.status !== 'Completed'">
                            <button class="btn btn-success fw-bold shadow-sm px-4" @click="handleApprove">
                                <i class="bx bx-check-double me-1"></i> APPROVE & ADJUST STOK OTOMATIS
                            </button>
                        </template>
                        <template v-else>
                            <span class="badge bg-success fs-6 p-2"><i class="bx bx-check-circle me-1"></i> SO COMPLETED & ADJUSTED</span>
                        </template>
                    </div>
                </div>

                <!-- Executive Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3">
                            <span class="text-muted small">Total Item Di-Scope</span>
                            <h4 class="fw-bold text-dark mb-0">{{ summary.total }} Item</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3">
                            <span class="text-muted small">MATCH (Stok Sesuai)</span>
                            <h4 class="fw-bold text-success mb-0">{{ summary.match }} Item</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3">
                            <span class="text-muted small">SHORTAGE (Kekurangan Stok)</span>
                            <h4 class="fw-bold text-danger mb-0">{{ summary.shortage }} Item</h4>
                            <small class="text-danger">Estimasi Nilai: {{ formatCurrency(summary.shortage_val) }}</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3">
                            <span class="text-muted small">SURPLUS (Kelebihan Stok)</span>
                            <h4 class="fw-bold text-warning mb-0">{{ summary.surplus }} Item</h4>
                            <small class="text-warning">Estimasi Nilai: {{ formatCurrency(summary.surplus_val) }}</small>
                        </div>
                    </div>
                </div>

                <!-- Items Differences Table -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">Review Item Hasil Penghitungan Fisik</h5>
                        <div class="form-check form-switch small">
                            <input class="form-check-input" type="checkbox" id="filterDiffReview" v-model="filterOnlyDiff">
                            <label class="form-check-label fw-bold" for="filterDiffReview">Hanya Tampilkan Item Berselisih</label>
                        </div>
                    </div>

                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>Nama Obat</th>
                                <th>Batch Number</th>
                                <th class="text-center">Stok Sistem</th>
                                <th class="text-center">Stok Fisik</th>
                                <th class="text-center">Selisih</th>
                                <th>Status Selisih</th>
                                <th>Alasan & Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in filteredItems" :key="item.id">
                                <td class="fw-bold">{{ item.medicine_name }}</td>
                                <td><small class="text-muted">{{ item.batch_number || '-' }}</small></td>
                                <td class="text-center">{{ item.system_stock }}</td>
                                <td class="text-center fw-bold">{{ item.physical_stock }}</td>
                                <td class="text-center fw-bold" :class="item.difference !== 0 ? 'text-danger' : 'text-success'">
                                    {{ item.difference > 0 ? '+' : '' }}{{ item.difference }}
                                </td>
                                <td>
                                    <span class="badge" :class="{
                                        'bg-success': item.match_status === 'MATCH',
                                        'bg-danger': item.match_status === 'SHORTAGE',
                                        'bg-warning text-dark': item.match_status === 'SURPLUS'
                                    }">
                                        {{ item.match_status }}
                                    </span>
                                </td>
                                <td>
                                    <small>{{ item.reason || 'Pemeriksaan Rutin' }}</small>
                                </td>
                            </tr>
                            <tr v-if="!filteredItems || filteredItems.length === 0">
                                <td colspan="7" class="text-center py-4 text-muted">Seluruh stok obat fisik sesuai sempurna dengan stok sistem (MATCH)!</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
