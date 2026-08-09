<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';

const props = defineProps({
    medicines: Object,
    summary: Object,
    categories: Array,
    filters: Object,
});

const filterForm = ref({
    search: props.filters?.search || '',
    kategori: props.filters?.kategori || '',
    stock_status: props.filters?.stock_status || '',
});

const applyFilters = () => {
    router.get(route('inventory.stocks'), filterForm.value, { preserveState: true, replace: true });
};

const resetFilters = () => {
    filterForm.value = { search: '', kategori: '', stock_status: '' };
    applyFilters();
};

const selectedBatchMedicine = ref(null);

const openBatchModal = (medicine) => {
    selectedBatchMedicine.value = medicine;
    const modal = new bootstrap.Modal(document.getElementById('batchDetailModal'));
    modal.show();
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const formatNumber = (val) => {
    return new Intl.NumberFormat('id-ID').format(val || 0);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-package text-primary me-2"></i>Stok Real Time Obat & Batch Inventory</h2>
                        <p class="text-muted small mb-0">Monitoring Fisik Stok Obat Real-Time, Nilai Valuasi Aset & Rincian FEFO Batch Expired</p>
                    </div>

                    <div class="d-flex gap-2">
                        <Link :href="route('inventory.movements')" class="btn btn-outline-primary fw-bold shadow-xs">
                            <i class="bx bx-transfer me-1"></i> Lihat Kartu Stok
                        </Link>
                        <button class="btn btn-light border fw-bold shadow-xs" onclick="window.print()">
                            <i class="bx bx-printer me-1"></i> Print Laporan
                        </button>
                    </div>
                </div>

                <!-- Summary Dashboard Widgets -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Total Jenis Obat</span>
                            <h3 class="fw-bold text-primary mb-0">{{ formatNumber(summary.total_items) }}</h3>
                            <small class="text-muted" style="font-size: 0.72rem;">Master Item Terdaftar</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Total Stok Fisik Real</span>
                            <h3 class="fw-bold text-success mb-0">{{ formatNumber(summary.total_physical_stock) }}</h3>
                            <small class="text-muted" style="font-size: 0.72rem;">Unit Fisik Tersedia</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Stok Kurang / Minimum</span>
                            <h3 class="fw-bold text-warning mb-0">{{ formatNumber(summary.low_stock_count) }}</h3>
                            <small class="text-muted" style="font-size: 0.72rem;">Perlu Reorder ke PBF</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Total Valuasi Nilai Aset</span>
                            <h3 class="fw-bold text-info mb-0">{{ formatCurrency(summary.total_asset_valuation) }}</h3>
                            <small class="text-muted" style="font-size: 0.72rem;">Estimasi Nilai Rupiah Inventory</small>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search Bar -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-3 rounded-3">
                    <div class="row g-2 mb-3">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text" class="form-control" v-model="filterForm.search" @keyup.enter="applyFilters" placeholder="Cari nama obat, kode, atau kategori...">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <select class="form-select" v-model="filterForm.kategori" @change="applyFilters">
                                <option value="">Semua Kategori Obat</option>
                                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <select class="form-select" v-model="filterForm.stock_status" @change="applyFilters">
                                <option value="">Semua Status Stok</option>
                                <option value="min_stock">🟠 Stok Di Bawah Minimum</option>
                                <option value="out_of_stock">🔴 Stok Habis (0)</option>
                            </select>
                        </div>

                        <div class="col-md-1">
                            <button class="btn btn-outline-secondary w-100" @click="resetFilters" title="Reset Filter">
                                <i class="bx bx-reset"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Real-Time Stock Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Obat</th>
                                    <th>Kategori</th>
                                    <th class="text-end">Harga Jual</th>
                                    <th class="text-center">Min / Max Stok</th>
                                    <th class="text-center">Stok Real Time</th>
                                    <th class="text-end">Total Valuasi Aset</th>
                                    <th class="text-center">Status Stok</th>
                                    <th class="text-center">Rincian Batch</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in medicines.data" :key="m.kode">
                                    <td class="font-monospace fw-bold text-primary">{{ m.kode }}</td>
                                    <td>
                                        <div class="fw-bold text-dark fs-6">{{ m.nama }}</div>
                                        <small class="text-muted" v-if="m.near_expired_count > 0">
                                            <i class="bx bx-error text-warning me-1"></i>{{ m.near_expired_count }} batch hampir kadaluarsa
                                        </small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ m.kategori }}</span></td>
                                    <td class="text-end fw-bold text-dark">{{ formatCurrency(m.harga) }}</td>
                                    <td class="text-center small text-muted">{{ m.min_stok }} / {{ m.max_stok || (m.min_stok * 3) }}</td>
                                    <td class="text-center">
                                        <span class="fs-5 fw-bold" :class="{
                                            'text-danger': m.stok <= 0,
                                            'text-warning': m.stok > 0 && m.stok <= m.min_stok,
                                            'text-success': m.stok > m.min_stok
                                        }">
                                            {{ formatNumber(m.stok) }}
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold text-secondary">{{ formatCurrency(m.total_asset_value) }}</td>
                                    <td class="text-center">
                                        <span class="badge" :class="{
                                            'bg-danger': m.status_badge === 'OUT_OF_STOCK',
                                            'bg-warning text-dark': m.status_badge === 'LOW_STOCK',
                                            'bg-info text-dark': m.status_badge === 'NEAR_EXPIRED',
                                            'bg-success': m.status_badge === 'NORMAL'
                                        }">
                                            {{ m.status_badge === 'OUT_OF_STOCK' ? '🔴 Stok Habis' :
                                               m.status_badge === 'LOW_STOCK' ? '🟠 Stok Minimum' :
                                               m.status_badge === 'NEAR_EXPIRED' ? '🟡 Hampir Expired' : '🟢 Normal' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-xs btn-sm btn-outline-primary fw-bold" @click="openBatchModal(m)">
                                            <i class="bx bx-layer me-1"></i> {{ m.batches ? m.batches.length : 0 }} Batch
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!medicines.data || medicines.data.length === 0">
                                    <td colspan="9" class="text-center py-4 text-muted">Belum ada obat terdaftar atau tidak cocok dengan filter.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links -->
                    <Pagination :links="medicines.links" />
                </div>
            </div>
        </section>

        <!-- Modal Detail Batch & Expired FEFO -->
        <div class="modal fade" id="batchDetailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" v-if="selectedBatchMedicine">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bx bx-layer me-1"></i> Rincian Batch & Expired (FEFO): <strong>{{ selectedBatchMedicine.nama }}</strong>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Nomor Batch</th>
                                        <th>Expired Date</th>
                                        <th class="text-center">Stok Batch</th>
                                        <th>Harga Beli Unit</th>
                                        <th class="text-center">Status FEFO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="b in selectedBatchMedicine.batches" :key="b.id">
                                        <td class="font-monospace fw-bold text-dark">{{ b.batch_number }}</td>
                                        <td class="fw-bold" :class="b.expired_date < new Date().toISOString().split('T')[0] ? 'text-danger' : 'text-dark'">
                                            {{ b.expired_date }}
                                        </td>
                                        <td class="text-center fw-bold text-primary">{{ b.stock }}</td>
                                        <td>{{ formatCurrency(b.unit_price) }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-success">FEFO Priorities</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!selectedBatchMedicine.batches || selectedBatchMedicine.batches.length === 0">
                                        <td colspan="5" class="text-center py-3 text-muted">Belum ada rincian batch terdaftar untuk obat ini.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
