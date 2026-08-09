<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';

const props = defineProps({
    opnames: Object,
    categories: Array,
    suppliers: Array,
    metrics: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const warehouseFilter = ref(props.filters?.warehouse || '');

const handleFilter = () => {
    router.get(route('inventory.opname'), {
        search: search.value,
        status: statusFilter.value,
        warehouse: warehouseFilter.value,
    }, { preserveState: true, replace: true });
};

// Wizard Form
const wizardForm = useForm({
    warehouse_name: 'Gudang Utama',
    opname_date: new Date().toISOString().slice(0, 10),
    scope_type: 'all',
    scope_filter: '',
    lock_stock: true,
    threshold_difference: 5,
    notes: '',
});

const submitWizard = () => {
    wizardForm.post(route('inventory.opname.store'), {
        onSuccess: () => {
            const modalEl = document.getElementById('wizardSoModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <!-- Header & Action -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-task text-primary me-2"></i>Manajemen Stok Opname (SO)</h2>
                        <p class="text-muted small mb-0">Audit Fisik Stok Obat & Penyesuaian Otomatis</p>
                    </div>

                    <button class="btn btn-primary shadow-sm fw-bold px-4" data-bs-toggle="modal" data-bs-target="#wizardSoModal">
                        <i class="bx bx-plus me-1"></i> + Buat Stok Opname
                    </button>
                </div>

                <!-- Dashboard Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3">
                            <span class="text-muted small">SO Aktif / Counting</span>
                            <h4 class="fw-bold text-primary mb-0">{{ metrics.active }} SO</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3">
                            <span class="text-muted small">Menunggu Approval</span>
                            <h4 class="fw-bold text-warning mb-0">{{ metrics.waiting_approval }} SO</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3">
                            <span class="text-muted small">Selesai Bulan Ini</span>
                            <h4 class="fw-bold text-success mb-0">{{ metrics.completed_month }} SO</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3">
                            <span class="text-muted small">Item Berselisih Total</span>
                            <h4 class="fw-bold text-danger mb-0">{{ metrics.total_differences }} Item</h4>
                        </div>
                    </div>
                </div>

                <!-- Table & Filter Bar -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div class="d-flex gap-2 align-items-center">
                            <div class="input-group input-group-sm" style="max-width: 220px;">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text" class="form-control" v-model="search" @keyup.enter="handleFilter" placeholder="Cari No. SO...">
                            </div>

                            <select class="form-select form-select-sm" style="width: 140px;" v-model="statusFilter" @change="handleFilter">
                                <option value="">Semua Status</option>
                                <option value="Counting">Counting</option>
                                <option value="Review">Review</option>
                                <option value="Waiting Approval">Waiting Approval</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>No. SO</th>
                                <th>Gudang</th>
                                <th>Scope</th>
                                <th>Tanggal</th>
                                <th class="text-center">Total Item</th>
                                <th class="text-center">Total Selisih</th>
                                <th>Status</th>
                                <th>Dibuat Oleh</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="so in opnames.data" :key="so.id">
                                <td class="fw-bold text-primary">{{ so.opname_number }}</td>
                                <td><span class="badge bg-light text-dark border">{{ so.warehouse_name }}</span></td>
                                <td><span class="badge bg-info text-dark">{{ so.scope_type.toUpperCase() }}</span></td>
                                <td>{{ so.opname_date }}</td>
                                <td class="text-center fw-bold">{{ so.total_items || 0 }}</td>
                                <td class="text-center fw-bold" :class="(so.total_difference || 0) !== 0 ? 'text-danger' : 'text-success'">
                                    {{ so.total_difference > 0 ? '+' : '' }}{{ so.total_difference || 0 }}
                                </td>
                                <td>
                                    <span class="badge" :class="{
                                        'bg-primary': so.status === 'Counting',
                                        'bg-warning text-dark': so.status === 'Waiting Approval',
                                        'bg-success': so.status === 'Completed',
                                        'bg-secondary': so.status === 'Draft'
                                    }">
                                        {{ so.status }}
                                    </span>
                                </td>
                                <td>{{ so.user_name }}</td>
                                <td class="text-center">
                                    <template v-if="so.status === 'Counting'">
                                        <Link :href="route('inventory.opname.counting', so.id)" class="btn btn-sm btn-primary fw-bold">
                                            <i class="bx bx-scan"></i> SO Cepat
                                        </Link>
                                    </template>
                                    <template v-else-if="so.status === 'Waiting Approval' || so.status === 'Review'">
                                        <Link :href="route('inventory.opname.review', so.id)" class="btn btn-sm btn-warning text-white fw-bold">
                                            <i class="bx bx-check-double"></i> Review
                                        </Link>
                                    </template>
                                    <template v-else>
                                        <Link :href="route('inventory.opname.review', so.id)" class="btn btn-sm btn-outline-secondary">
                                            <i class="bx bx-show"></i> Detail
                                        </Link>
                                    </template>
                                </td>
                            </tr>
                            <tr v-if="!opnames.data || opnames.data.length === 0">
                                <td colspan="9" class="text-center py-4 text-muted">Belum ada data Stok Opname.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Wizard Modal Buat SO -->
        <div class="modal fade" id="wizardSoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">+ Wizard Pembentukan Stok Opname (SO)</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitWizard">
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label font-weight-bold">Pilih Gudang / Lokasi</label>
                                    <select class="form-select" v-model="wizardForm.warehouse_name" required>
                                        <option value="Gudang Utama">Gudang Utama</option>
                                        <option value="Gudang Belakang">Gudang Belakang</option>
                                        <option value="Rak Depan Kasir">Rak Depan Kasir</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tanggal Pelaksanaan SO</label>
                                    <input type="date" class="form-control" v-model="wizardForm.opname_date" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Pilih Scope Obat yang Di-Stok Opname</label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="form-check card p-2" :class="{ 'border-primary bg-light': wizardForm.scope_type === 'all' }">
                                            <input class="form-check-input" type="radio" value="all" id="scopeAll" v-model="wizardForm.scope_type">
                                            <label class="form-check-label fw-bold" for="scopeAll">Option A — Semua Obat</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check card p-2" :class="{ 'border-primary bg-light': wizardForm.scope_type === 'category' }">
                                            <input class="form-check-input" type="radio" value="category" id="scopeCat" v-model="wizardForm.scope_type">
                                            <label class="form-check-label fw-bold" for="scopeCat">Option B — Per Kategori</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check card p-2" :class="{ 'border-primary bg-light': wizardForm.scope_type === 'stock_status' }">
                                            <input class="form-check-input" type="radio" value="stock_status" id="scopeStock" v-model="wizardForm.scope_type">
                                            <label class="form-check-label fw-bold" for="scopeStock">Option E — Filter Stok</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3" v-if="wizardForm.scope_type === 'category'">
                                <label class="form-label small text-muted">Pilih Kategori Obat</label>
                                <select class="form-select" v-model="wizardForm.scope_filter" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <option v-for="c in categories" :key="c.id" :value="c.name">{{ c.name }}</option>
                                </select>
                            </div>

                            <div class="mb-3" v-if="wizardForm.scope_type === 'stock_status'">
                                <label class="form-label small text-muted">Filter Status Stok</label>
                                <select class="form-select" v-model="wizardForm.scope_filter" required>
                                    <option value="positive">Stok > 0</option>
                                    <option value="zero">Stok = 0</option>
                                    <option value="low">Stok Menipis (<= 10)</option>
                                </select>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="lockStock" v-model="wizardForm.lock_stock">
                                        <label class="form-check-label fw-bold" for="lockStock">Lock Stock selama proses SO</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Batas Ambang Selisih Butuh Approval (Threshold Qty)</label>
                                    <input type="number" class="form-control" v-model.number="wizardForm.threshold_difference" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary fw-bold" :disabled="wizardForm.processing">Mulai Counting Mode SO Cepat</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
