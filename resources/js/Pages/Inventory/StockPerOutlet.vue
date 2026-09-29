<script setup>
import { ref } from 'vue';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { router, Link } from '@inertiajs/vue3';

const props = defineProps({
    stocks: Object,
    outlets: Array,
    selectedOutlet: Object,
    categories: Array,
    filters: Object,
});

const outletIdFilter = ref(props.filters?.outlet_id || props.selectedOutlet?.id || '');
const searchFilter = ref(props.filters?.search || '');
const categoryFilter = ref(props.filters?.kategori || '');
const stockFilter = ref(props.filters?.stock_filter || 'all');

const applyFilter = () => {
    router.get(route('inventory.stocks-per-outlet'), {
        outlet_id: outletIdFilter.value,
        search: searchFilter.value,
        kategori: categoryFilter.value,
        stock_filter: stockFilter.value,
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">
                            <i class="bx bx-store-alt text-primary me-2"></i>Stok Per Outlet / Cabang
                        </h2>
                        <p class="text-muted small mb-0">Lihat dan bandingkan ketersediaan stok fisik produk pada outlet yang dipilih.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <Link :href="route('stock-transfers.index')" class="btn btn-outline-primary shadow-xs">
                            <i class="bx bx-transfer-alt me-1"></i> Transfer Stok Antar Outlet
                        </Link>
                    </div>
                </div>

                <!-- Filter Toolbar -->
                <div class="card border-0 shadow-xs mb-4 rounded-3">
                    <div class="card-body p-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-secondary mb-1">Pilih Outlet</label>
                                <select class="form-select" v-model="outletIdFilter" @change="applyFilter">
                                    <option v-for="out in outlets" :key="out.id" :value="out.id">
                                        {{ out.name }} {{ out.is_main ? '(Pusat)' : '' }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-secondary mb-1">Pencarian</label>
                                <input type="text" class="form-control" v-model="searchFilter" @keyup.enter="applyFilter" placeholder="Nama obat / kode...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-secondary mb-1">Kategori Obat</label>
                                <select class="form-select" v-model="categoryFilter" @change="applyFilter">
                                    <option value="">Semua Kategori</option>
                                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-secondary mb-1">Kondisi Stok</label>
                                <select class="form-select" v-model="stockFilter" @change="applyFilter">
                                    <option value="all">Semua Kondisi</option>
                                    <option value="low">⚠️ Stok Rendah (&le; Min Stock)</option>
                                    <option value="empty">🚨 Stok Habis (0)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Outlet Info Header Banner -->
                <div class="card border-0 shadow-md rounded-4 mb-4 overflow-hidden position-relative bg-gradient-outlet text-white">
                    <div class="card-body p-4 position-relative z-1">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-4 bg-white bg-opacity-20 backdrop-blur p-3 d-flex align-items-center justify-content-center shadow-xs" style="width: 56px; height: 56px; min-width: 56px;">
                                    <i class="bx bx-store-alt text-warning fs-1"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h4 class="fw-bold text-white mb-0">{{ selectedOutlet?.name }}</h4>
                                        <span v-if="selectedOutlet?.is_main" class="badge bg-warning text-dark fw-bold px-2.5 py-1 rounded-pill shadow-xs">
                                            <i class="bx bx-crown me-1"></i>Pusat
                                        </span>
                                        <span v-else class="badge bg-white bg-opacity-20 text-white border border-white border-opacity-25 rounded-pill px-2.5 py-1 style-xs">
                                            <i class="bx bx-building me-1"></i>Cabang
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 text-white-50 style-xs">
                                        <span><i class="bx bx-map me-1 text-warning"></i>{{ selectedOutlet?.address || 'Alamat outlet belum diatur' }}</span>
                                        <span v-if="selectedOutlet?.code"><i class="bx bx-barcode me-1 text-warning"></i>Kode: <code class="text-white fw-bold">{{ selectedOutlet?.code }}</code></span>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2 bg-black bg-opacity-20 backdrop-blur px-3.5 py-2 rounded-3 border border-white border-opacity-10">
                                <i class="bx bx-package fs-4 text-warning"></i>
                                <div>
                                    <small class="text-white-50 d-block style-xs">Total Data Stok:</small>
                                    <span class="fw-bold text-white fs-6">{{ stocks?.total || stocks?.data?.length || 0 }} Jenis Obat</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stock Table -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-4 rounded-3 overflow-hidden shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>Kode Produk</th>
                                    <th>Nama Produk</th>
                                    <th>Kategori</th>
                                    <th>Lokasi Rak</th>
                                    <th class="text-end">Min Stok</th>
                                    <th class="text-end">Stok Outlet Ini</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in stocks.data" :key="item.kode">
                                    <td><code class="fw-bold text-primary">{{ item.kode }}</code></td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ item.nama }}</div>
                                        <span class="badge bg-light text-dark border small">{{ item.jenis_obat }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark" v-if="item.kategori">{{ item.kategori }}</span>
                                        <span v-else class="text-muted small">-</span>
                                    </td>
                                    <td>
                                        <span v-if="item.rack_location" class="font-monospace small"><i class="bx bx-map-pin me-1 text-primary"></i>{{ item.rack_location }}</span>
                                        <span v-else class="text-muted small">-</span>
                                    </td>
                                    <td class="text-end fw-semibold text-secondary">{{ Number(item.min_stock).toLocaleString('id-ID') }}</td>
                                    <td class="text-end fw-bold fs-5" :class="{ 'text-danger': item.current_stock <= 0, 'text-warning': item.current_stock > 0 && item.current_stock <= item.min_stock, 'text-success': item.current_stock > item.min_stock }">
                                        {{ Number(item.current_stock).toLocaleString('id-ID') }}
                                    </td>
                                    <td class="text-center">
                                        <span v-if="item.current_stock <= 0" class="badge bg-danger">Habis</span>
                                        <span v-else-if="item.current_stock <= item.min_stock" class="badge bg-warning text-dark">Stok Rendah</span>
                                        <span v-else class="badge bg-success">Aman</span>
                                    </td>
                                </tr>
                                <tr v-if="!stocks.data || stocks.data.length === 0">
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bx bx-package fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                        Tidak ada produk atau stok ditemukan untuk filter ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <Pagination :links="stocks.links" />
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>

<style scoped>
.bg-gradient-outlet {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    box-shadow: 0 8px 25px rgba(30, 60, 114, 0.25);
}
:global(body.dark-mode) .bg-gradient-outlet {
    background: linear-gradient(135deg, #111e38 0%, #1e3c72 100%);
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
}
.backdrop-blur {
    backdrop-filter: blur(8px);
}
.style-xs {
    font-size: 0.78rem;
}
</style>
