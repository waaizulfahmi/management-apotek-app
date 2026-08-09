<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';

const props = defineProps({
    orders: Object,
    suppliers: Array,
    metrics: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const supplierFilter = ref(props.filters?.supplier_id || '');

const handleFilter = () => {
    router.get(route('purchases.po.index'), {
        search: search.value,
        status: statusFilter.value,
        supplier_id: supplierFilter.value,
    }, { preserveState: true, replace: true });
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-cart-download text-primary me-2"></i>Purchase Order (PO) ke PBF</h2>
                        <p class="text-muted small mb-0">Manajemen Pengadaan & Pemesanan Obat ke Supplier</p>
                    </div>

                    <Link :href="route('purchases.po.create')" class="btn btn-primary shadow-sm fw-bold px-4">
                        <i class="bx bx-plus me-1"></i> + Buat PO Baru
                    </Link>
                </div>

                <!-- Dashboard Summary Metrics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">PO Bulan Ini</span>
                            <h4 class="fw-bold text-primary mb-0">{{ metrics.total_month }}</h4>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Menunggu Approval</span>
                            <h4 class="fw-bold text-warning mb-0">{{ metrics.waiting_approval }}</h4>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Disetujui (Approved)</span>
                            <h4 class="fw-bold text-success mb-0">{{ metrics.approved }}</h4>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Dikirim (Sent)</span>
                            <h4 class="fw-bold text-info mb-0">{{ metrics.sent }}</h4>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Sebagian Diterima</span>
                            <h4 class="fw-bold text-warning mb-0">{{ metrics.partial_received }}</h4>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-3 text-center">
                            <span class="text-muted small">Selesai (Received)</span>
                            <h4 class="fw-bold text-success mb-0">{{ metrics.received }}</h4>
                        </div>
                    </div>
                </div>

                <!-- Table & Filter Bar -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div class="d-flex gap-2 align-items-center">
                            <div class="input-group input-group-sm" style="max-width: 220px;">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text" class="form-control" v-model="search" @keyup.enter="handleFilter" placeholder="Cari No. PO...">
                            </div>

                            <select class="form-select form-select-sm" style="width: 160px;" v-model="supplierFilter" @change="handleFilter">
                                <option value="">Semua Supplier PBF</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>

                            <select class="form-select form-select-sm" style="width: 140px;" v-model="statusFilter" @change="handleFilter">
                                <option value="">Semua Status</option>
                                <option value="DRAFT">DRAFT</option>
                                <option value="WAITING_APPROVAL">WAITING APPROVAL</option>
                                <option value="APPROVED">APPROVED</option>
                                <option value="SENT">SENT</option>
                                <option value="PARTIAL_RECEIVED">PARTIAL RECEIVED</option>
                                <option value="RECEIVED">RECEIVED</option>
                            </select>
                        </div>
                    </div>

                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>No. PO</th>
                                <th>Tanggal</th>
                                <th>PBF / Supplier</th>
                                <th class="text-center">Total Item</th>
                                <th class="text-end">Total PO (Rp)</th>
                                <th>Status</th>
                                <th>Dibuat Oleh</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="po in orders.data" :key="po.id">
                                <td class="fw-bold text-primary">{{ po.po_number }}</td>
                                <td>{{ po.order_date }}</td>
                                <td class="fw-bold text-dark">{{ po.supplier_name }}</td>
                                <td class="text-center fw-bold">{{ po.total_items }}</td>
                                <td class="text-end fw-bold text-primary">{{ formatCurrency(po.grand_total) }}</td>
                                <td>
                                    <span class="badge" :class="{
                                        'bg-secondary': po.status === 'DRAFT',
                                        'bg-warning text-dark': po.status === 'WAITING_APPROVAL' || po.status === 'PARTIAL_RECEIVED',
                                        'bg-info text-dark': po.status === 'APPROVED' || po.status === 'SENT',
                                        'bg-success': po.status === 'RECEIVED'
                                    }">
                                        {{ po.status }}
                                    </span>
                                </td>
                                <td>{{ po.created_by_name }}</td>
                                <td class="text-center">
                                    <template v-if="po.status === 'APPROVED' || po.status === 'SENT' || po.status === 'PARTIAL_RECEIVED'">
                                        <Link :href="route('purchases.po.receive', po.id)" class="btn btn-sm btn-success fw-bold">
                                            <i class="bx bx-package"></i> Terima Barang
                                        </Link>
                                    </template>
                                    <template v-else>
                                        <button class="btn btn-sm btn-outline-secondary">
                                            <i class="bx bx-show"></i> Detail
                                        </button>
                                    </template>
                                </td>
                            </tr>
                            <tr v-if="!orders.data || orders.data.length === 0">
                                <td colspan="8" class="text-center py-4 text-muted">Belum ada data Purchase Order (PO).</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination Links -->
                    <Pagination :links="orders.links" />
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
