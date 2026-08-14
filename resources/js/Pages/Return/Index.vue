<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    returns: Object,
    filters: Object,
});

const search = ref(props.filters.return_number || '');
const typeFilter = ref(props.filters.type || '');
const statusFilter = ref(props.filters.status || '');

let debounceTimer = null;
const debouncedSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(
            route('returns.index'),
            { return_number: search.value, type: typeFilter.value, status: statusFilter.value },
            { preserveState: true, replace: true }
        );
    }, 300);
};

watch([search, typeFilter, statusFilter], () => {
    debouncedSearch();
});

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
};

const getStatusBadgeClass = (status) => {
    switch(status) {
        case 'DRAFT': return 'bg-secondary';
        case 'PENDING': return 'bg-warning text-dark';
        case 'APPROVED': return 'bg-info text-dark';
        case 'COMPLETED': return 'bg-success';
        case 'REJECTED': 
        case 'CANCELLED': return 'bg-danger';
        default: return 'bg-secondary';
    }
};

const getTypeLabel = (type) => {
    return type === 'sale' ? 'Retur Penjualan' : 'Retur Pembelian';
};

const getPartnerName = (ret) => {
    if (ret.type === 'sale') {
        return ret.customer ? ret.customer.name : 'Customer Umum';
    } else {
        return ret.supplier ? ret.supplier.name : '-';
    }
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark mb-0">
                        <i class="bx bx-repost text-primary me-2"></i>Riwayat Retur Barang
                    </h2>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm p-3 mb-4" style="border-radius: 12px;">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bx bx-search"></i></span>
                                <input v-model="search" type="text" placeholder="Cari Nomor Retur..." class="form-control border-start-0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select v-model="typeFilter" class="form-select">
                                <option value="">-- Semua Jenis Retur --</option>
                                <option value="sale">Retur Penjualan</option>
                                <option value="purchase">Retur Pembelian</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select v-model="statusFilter" class="form-select">
                                <option value="">-- Semua Status --</option>
                                <option value="PENDING">Pending</option>
                                <option value="APPROVED">Approved</option>
                                <option value="COMPLETED">Completed</option>
                                <option value="CANCELLED">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>No Retur / Tanggal</th>
                                    <th>Jenis / Pihak</th>
                                    <th>Dibuat Oleh</th>
                                    <th>Total Nilai</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="ret in returns.data" :key="ret.id">
                                    <td>
                                        <div class="fw-bold text-dark">{{ ret.return_number }}</div>
                                        <small class="text-muted">{{ formatDate(ret.created_at) }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ getTypeLabel(ret.type) }}</div>
                                        <small class="text-muted">{{ getPartnerName(ret) }}</small>
                                    </td>
                                    <td class="text-secondary">{{ ret.user?.name || '-' }}</td>
                                    <td class="fw-bold text-primary">{{ formatCurrency(ret.total_amount) }}</td>
                                    <td>
                                        <span :class="['badge', getStatusBadgeClass(ret.status)]">
                                            {{ ret.status }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <Link :href="route('returns.show', ret.id)" class="btn btn-sm btn-outline-primary">
                                            <i class="bx bx-show me-1"></i> Detail
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="returns.data.length === 0">
                                    <td colspan="6" class="text-center py-4 text-muted">Tidak ada data retur yang ditemukan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top" v-if="returns.links && returns.links.length > 3">
                        <small class="text-muted">
                            Menampilkan {{ returns.from }} - {{ returns.to }} dari {{ returns.total }} hasil
                        </small>
                        <div class="btn-group btn-group-sm">
                            <template v-for="(link, k) in returns.links" :key="k">
                                <div v-if="link.url === null" class="btn btn-outline-secondary disabled" v-html="link.label"></div>
                                <Link v-else :href="link.url" :class="['btn', link.active ? 'btn-primary' : 'btn-outline-secondary']" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
