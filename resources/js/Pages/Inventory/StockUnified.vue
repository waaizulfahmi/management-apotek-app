<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    activeTab: {
        type: String,
        default: 'outlet'
    },
    // Outlet Tab Props
    products: Object,
    outlets: Array,
    selectedOutlet: Object,
    availableGlobalProducts: Array,
    outletStats: Object,
    // Realtime Tab Props
    medicines: Object,
    summary: Object,
    selectedRealtimeOutlet: Object,
    // Shared Props
    categories: Array,
    filters: Object,
});

// Tab Switcher
const currentTab = ref(props.activeTab || 'outlet');

const switchTab = (tab) => {
    if (currentTab.value === tab) return;
    currentTab.value = tab;
    router.get(route('inventory.stocks'), { tab: tab }, { preserveState: false, replace: true });
};

// ==========================================
// OUTLET TAB STATE & ACTIONS
// ==========================================
const outletSearch = ref(props.filters?.search || '');
const outletCategory = ref(props.filters?.kategori || '');
const outletStatus = ref(props.filters?.status || 'ALL');
const outletStockFilter = ref(props.filters?.stock_filter || 'all');

const applyOutletFilters = (customStockFilter = null) => {
    if (customStockFilter !== null) {
        outletStockFilter.value = customStockFilter;
    }
    router.get(route('inventory.stocks'), {
        tab: 'outlet',
        outlet_id: props.selectedOutlet?.id,
        search: outletSearch.value,
        kategori: outletCategory.value,
        status: outletStatus.value,
        stock_filter: outletStockFilter.value,
    }, { preserveState: true, replace: true });
};

const handleOutletChange = (outletId) => {
    router.get(route('inventory.stocks'), {
        tab: 'outlet',
        outlet_id: outletId,
        search: outletSearch.value,
        kategori: outletCategory.value,
        status: outletStatus.value,
        stock_filter: outletStockFilter.value,
    }, { preserveState: false, replace: true });
};

const resetOutletFilters = () => {
    outletSearch.value = '';
    outletCategory.value = '';
    outletStatus.value = 'ALL';
    outletStockFilter.value = 'all';
    applyOutletFilters();
};

// Add Products Modal
const showAddModal = ref(false);
const selectAllGlobal = ref(false);
const addForm = useForm({
    outlet_id: props.selectedOutlet?.id,
    obat_kodes: [],
});

const openAddModal = () => {
    addForm.outlet_id = props.selectedOutlet?.id;
    addForm.obat_kodes = [];
    selectAllGlobal.value = false;
    showAddModal.value = true;
};

const toggleSelectAllGlobal = () => {
    if (selectAllGlobal.value && props.availableGlobalProducts) {
        addForm.obat_kodes = props.availableGlobalProducts.map(p => p.kode);
    } else {
        addForm.obat_kodes = [];
    }
};

const submitAddProducts = () => {
    if (addForm.obat_kodes.length === 0) {
        Swal.fire('Peringatan', 'Silakan pilih setidaknya satu produk untuk ditambahkan.', 'warning');
        return;
    }
    addForm.post(route('inventory.outlet-products.attach'), {
        onSuccess: () => {
            showAddModal.value = false;
            Swal.fire('Berhasil', 'Produk berhasil ditambahkan ke outlet!', 'success');
        }
    });
};

// Custom Price Modal
const showPriceModal = ref(false);
const editingProduct = ref(null);
const priceForm = useForm({
    price: null,
});

const openEditPriceModal = (product) => {
    editingProduct.value = product;
    priceForm.price = product.custom_price !== null ? product.custom_price : '';
    showPriceModal.value = true;
};

const submitUpdatePrice = () => {
    if (!editingProduct.value) return;
    priceForm.put(route('inventory.outlet-products.update-price', editingProduct.value.relation_id), {
        onSuccess: () => {
            showPriceModal.value = false;
            Swal.fire('Berhasil', 'Harga khusus outlet berhasil diperbarui!', 'success');
        }
    });
};

// Toggle Product Status in Outlet
const toggleProductStatus = (product) => {
    const actionName = product.outlet_is_active ? 'nonaktifkan' : 'aktifkan';
    Swal.fire({
        title: `Konfirmasi ${actionName}`,
        text: `Apakah Anda yakin ingin me-${actionName} produk "${product.nama}" pada outlet ini?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: `Ya, ${actionName}`,
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('inventory.outlet-products.toggle-status', product.relation_id), {}, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Berhasil', `Status produk berhasil diperbarui!`, 'success')
            });
        }
    });
};

// Detach Product
const detachProduct = (product) => {
    Swal.fire({
        title: 'Hapus dari Outlet?',
        text: `Produk "${product.nama}" akan dihapus dari outlet ini, namun Master Produk Global tetap aman.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus dari Outlet',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('inventory.outlet-products.detach', product.relation_id), {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Dihapus', 'Produk berhasil dihapus dari outlet ini.', 'success')
            });
        }
    });
};

// ==========================================
// REAL-TIME TAB STATE & ACTIONS (WITH OUTLET CLARITY)
// ==========================================
const realtimeSearch = ref(props.filters?.search || '');
const realtimeCategory = ref(props.filters?.kategori || '');
const realtimeStockStatus = ref(props.filters?.stock_status || '');
const realtimeOutletId = ref(props.filters?.realtime_outlet_id || 'all');

const applyRealtimeFilters = () => {
    router.get(route('inventory.stocks'), {
        tab: 'realtime',
        search: realtimeSearch.value,
        kategori: realtimeCategory.value,
        stock_status: realtimeStockStatus.value,
        realtime_outlet_id: realtimeOutletId.value,
    }, { preserveState: true, replace: true });
};

const resetRealtimeFilters = () => {
    realtimeSearch.value = '';
    realtimeCategory.value = '';
    realtimeStockStatus.value = '';
    realtimeOutletId.value = 'all';
    applyRealtimeFilters();
};

const selectedBatchMedicine = ref(null);
const showBatchModal = ref(false);

const openBatchModal = (medicine) => {
    selectedBatchMedicine.value = medicine;
    showBatchModal.value = true;
};

// Helpers
const formatCurrency = (val) => {
    if (val === null || val === undefined || isNaN(val)) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
};

const formatNumber = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '0';
    return new Intl.NumberFormat('id-ID').format(val);
};
</script>

<template>
    <Head title="Stok & Produk Outlet" />

    <LegacyLayout>
        <div class="container-fluid py-4 px-4">
            <!-- Header Page Title -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                        <i class="bx bx-layer text-primary fs-2"></i>
                        <span>Stok & Produk Outlet</span>
                    </h2>
                    <p class="text-muted small mb-0">
                        Pusat kendali ketersediaan stok obat, harga jual per cabang, dan inventaris FEFO batch real-time.
                    </p>
                </div>

                <!-- Navigation Tabs Switcher -->
                <div class="bg-light p-1.5 rounded-4 border d-inline-flex align-items-center shadow-xs">
                    <button 
                        @click="switchTab('outlet')" 
                        class="btn btn-sm rounded-3 fw-bold px-3.5 py-2 d-flex align-items-center gap-2 transition-all"
                        :class="currentTab === 'outlet' ? 'btn-primary shadow-sm' : 'text-secondary btn-ghost'">
                        <i class="bx bx-store-alt fs-5"></i>
                        <span>Stok & Produk Per Outlet</span>
                        <span v-if="selectedOutlet" class="badge rounded-pill" :class="currentTab === 'outlet' ? 'bg-white text-primary' : 'bg-secondary bg-opacity-25 text-dark'">
                            {{ selectedOutlet.name }}
                        </span>
                    </button>
                    <button 
                        @click="switchTab('realtime')" 
                        class="btn btn-sm rounded-3 fw-bold px-3.5 py-2 d-flex align-items-center gap-2 transition-all"
                        :class="currentTab === 'realtime' ? 'btn-primary shadow-sm' : 'text-secondary btn-ghost'">
                        <i class="bx bx-package fs-5"></i>
                        <span>Stok Real Time & Batch FEFO</span>
                        <span class="badge rounded-pill" :class="currentTab === 'realtime' ? 'bg-white text-primary' : 'bg-secondary bg-opacity-25 text-dark'">
                            {{ selectedRealtimeOutlet ? selectedRealtimeOutlet.name : 'Semua Outlet' }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- TAB 1: STOK & PRODUK PER OUTLET                         -->
            <!-- ======================================================== -->
            <div v-if="currentTab === 'outlet'">
                <!-- Outlet Banner & Header Controls -->
                <div class="card border-0 shadow-md rounded-4 mb-4 overflow-hidden position-relative bg-gradient-outlet text-white">
                    <div class="card-body p-4 position-relative z-1">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-4 bg-white bg-opacity-20 backdrop-blur p-3 d-flex align-items-center justify-content-center shadow-xs" style="width: 58px; height: 58px; min-width: 58px;">
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

                            <!-- Right Controls: Switcher & Actions -->
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <!-- Outlet Selector Dropdown -->
                                <div class="dropdown">
                                    <button class="btn btn-warning dropdown-toggle fw-bold text-dark rounded-pill px-3.5 py-2 shadow-xs d-flex align-items-center gap-1.5" type="button" data-bs-toggle="dropdown">
                                        <i class="bx bx-map-pin"></i>
                                        <span>Ganti Outlet: {{ selectedOutlet?.name }}</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-1">
                                        <li v-for="out in outlets" :key="out.id">
                                            <button class="dropdown-item d-flex align-items-center justify-content-between py-2 px-3" @click="handleOutletChange(out.id)">
                                                <span>
                                                    <i class="bx bx-store me-2 text-primary"></i>{{ out.name }}
                                                    <small v-if="out.is_main" class="text-warning fw-bold ms-1">(Pusat)</small>
                                                </span>
                                                <i v-if="selectedOutlet?.id === out.id" class="bx bx-check text-success fs-5 ms-3"></i>
                                            </button>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Add Product to Outlet Button -->
                                <button @click="openAddModal" class="btn btn-light text-primary fw-bold rounded-pill shadow-xs d-flex align-items-center gap-1.5 px-3.5 py-2">
                                    <i class="bx bx-plus-circle fs-5"></i>
                                    <span>Tambah Produk</span>
                                </button>

                                <!-- Transfer Stock Link -->
                                <Link :href="route('stock-transfers.index')" class="btn btn-outline-light rounded-pill shadow-xs d-flex align-items-center gap-1.5 px-3 py-2">
                                    <i class="bx bx-transfer-alt fs-5"></i>
                                    <span>Transfer Stok</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Outlet Quick Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100 cursor-pointer stat-card"
                             :class="{ 'border-primary border-2': outletStockFilter === 'all' }"
                             @click="applyOutletFilters('all')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block fw-semibold">Total Terdaftar</small>
                                    <h4 class="fw-bold text-primary mb-0 mt-1">{{ formatNumber(outletStats?.total_products) }}</h4>
                                </div>
                                <div class="rounded-3 p-2.5 bg-primary bg-opacity-10 text-primary">
                                    <i class="bx bx-package fs-3"></i>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block style-xs">Semua produk terdaftar di outlet</small>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100 cursor-pointer stat-card"
                             :class="{ 'border-success border-2': outletStockFilter === 'safe' }"
                             @click="applyOutletFilters('safe')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block fw-semibold">Stok Aman</small>
                                    <h4 class="fw-bold text-success mb-0 mt-1">{{ formatNumber(outletStats?.safe_stock) }}</h4>
                                </div>
                                <div class="rounded-3 p-2.5 bg-success bg-opacity-10 text-success">
                                    <i class="bx bx-check-shield fs-3"></i>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block style-xs">Di atas batas minimum stok</small>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100 cursor-pointer stat-card"
                             :class="{ 'border-warning border-2': outletStockFilter === 'low' }"
                             @click="applyOutletFilters('low')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block fw-semibold">Stok Menipis (&le; Min)</small>
                                    <h4 class="fw-bold text-warning mb-0 mt-1">{{ formatNumber(outletStats?.low_stock) }}</h4>
                                </div>
                                <div class="rounded-3 p-2.5 bg-warning bg-opacity-10 text-warning">
                                    <i class="bx bx-error-circle fs-3"></i>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block style-xs">Perlu reorder atau transfer</small>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100 cursor-pointer stat-card"
                             :class="{ 'border-danger border-2': outletStockFilter === 'empty' }"
                             @click="applyOutletFilters('empty')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block fw-semibold">Stok Habis (0)</small>
                                    <h4 class="fw-bold text-danger mb-0 mt-1">{{ formatNumber(outletStats?.empty_stock) }}</h4>
                                </div>
                                <div class="rounded-3 p-2.5 bg-danger bg-opacity-10 text-danger">
                                    <i class="bx bx-x-circle fs-3"></i>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block style-xs">Habis, tidak bisa ditransaksikan</small>
                        </div>
                    </div>
                </div>

                <!-- Filter Toolbar -->
                <div class="card border-0 shadow-xs rounded-4 mb-4">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 text-muted">
                                        <i class="bx bx-search fs-5"></i>
                                    </span>
                                    <input v-model="outletSearch" @keyup.enter="applyOutletFilters()" type="text" 
                                           class="form-control border-start-0 ps-0" placeholder="Cari nama atau kode obat..." />
                                </div>
                            </div>

                            <div class="col-md-3">
                                <select v-model="outletCategory" @change="applyOutletFilters()" class="form-select">
                                    <option value="">Semua Kategori</option>
                                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <select v-model="outletStockFilter" @change="applyOutletFilters()" class="form-select">
                                    <option value="all">Semua Stok</option>
                                    <option value="safe">🟢 Stok Aman</option>
                                    <option value="low">🟡 Stok Menipis</option>
                                    <option value="empty">🔴 Stok Habis</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <select v-model="outletStatus" @change="applyOutletFilters()" class="form-select">
                                    <option value="ALL">Semua Status</option>
                                    <option value="ACTIVE">Aktif (Dijual)</option>
                                    <option value="INACTIVE">Nonaktif</option>
                                </select>
                            </div>

                            <div class="col-md-1 d-grid">
                                <button @click="resetOutletFilters" class="btn btn-light border rounded-3" title="Reset Filter">
                                    <i class="bx bx-reset"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products Table Card -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-0 py-3.5 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bx bx-list-check text-primary fs-4"></i>
                            <h5 class="fw-bold mb-0 text-dark">Daftar Produk & Stok:</h5>
                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1.5 rounded-pill fw-bold">
                                {{ selectedOutlet?.name }}
                            </span>
                        </div>
                        <small class="text-muted fw-semibold">
                            Total: <strong>{{ products?.total || 0 }}</strong> produk
                        </small>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted style-xs text-uppercase">
                                <tr>
                                    <th class="ps-4" style="width: 130px;">Kode</th>
                                    <th>Nama Obat</th>
                                    <th>Kategori & Rak</th>
                                    <th class="text-center" style="width: 100px;">Min Stok</th>
                                    <th class="text-center" style="width: 130px;">Stok Outlet</th>
                                    <th class="text-end" style="width: 160px;">Harga Jual Outlet</th>
                                    <th class="text-center" style="width: 110px;">Status Jual</th>
                                    <th class="text-center pe-4" style="width: 130px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!products?.data || products.data.length === 0">
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bx bx-package fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                        <span>Tidak ada data produk atau stok yang sesuai dengan filter.</span>
                                    </td>
                                </tr>
                                <tr v-for="item in products?.data" :key="item.relation_id">
                                    <td class="ps-4">
                                        <code class="fw-bold text-primary">{{ item.obat_id }}</code>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ item.nama }}</div>
                                        <span v-if="item.jenis_obat" class="badge bg-light text-secondary border style-xs mt-0.5">
                                            {{ item.jenis_obat }}
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-0.5 small">
                                                {{ item.kategori || 'Umum' }}
                                            </span>
                                        </div>
                                        <small v-if="item.rack_location" class="text-muted style-xs d-block mt-0.5">
                                            <i class="bx bx-map-pin text-primary me-0.5"></i>{{ item.rack_location }}
                                        </small>
                                    </td>
                                    <td class="text-center fw-semibold text-secondary">
                                        {{ Number(item.min_stock).toLocaleString('id-ID') }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill px-3 py-1.5 fw-bold fs-6"
                                              :class="{
                                                  'bg-danger text-white': item.current_stock <= 0,
                                                  'bg-warning text-dark': item.current_stock > 0 && item.current_stock <= item.min_stock,
                                                  'bg-success text-white': item.current_stock > item.min_stock
                                              }">
                                            {{ Number(item.current_stock).toLocaleString('id-ID') }}
                                        </span>
                                        <small class="d-block style-xs mt-1 fw-semibold"
                                               :class="{
                                                   'text-danger': item.current_stock <= 0,
                                                   'text-warning': item.current_stock > 0 && item.current_stock <= item.min_stock,
                                                   'text-success': item.current_stock > item.min_stock
                                               }">
                                            {{ item.current_stock <= 0 ? 'Habis' : (item.current_stock <= item.min_stock ? 'Menipis' : 'Aman') }}
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <div v-if="item.custom_price !== null" class="fw-bold text-success">
                                            {{ formatCurrency(item.custom_price) }}
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 style-xs d-block ms-auto mt-0.5" style="width: fit-content;">
                                                Harga Khusus
                                            </span>
                                        </div>
                                        <div v-else class="fw-semibold text-dark">
                                            {{ formatCurrency(item.default_price) }}
                                            <small class="text-muted d-block style-xs">(Default Global)</small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button @click="toggleProductStatus(item)" 
                                                class="badge rounded-pill border-0 px-2.5 py-1 text-white cursor-pointer transition-all shadow-xs"
                                                :class="item.outlet_is_active ? 'bg-success' : 'bg-danger'"
                                                title="Klik untuk ubah status">
                                            <i class="bx me-1" :class="item.outlet_is_active ? 'bx-check-circle' : 'bx-x-circle'"></i>
                                            {{ item.outlet_is_active ? 'AKTIF' : 'NONAKTIF' }}
                                        </button>
                                    </td>
                                    <td class="text-center pe-4">
                                        <div class="btn-group btn-group-sm shadow-xs rounded-3 overflow-hidden">
                                            <button @click="openEditPriceModal(item)" class="btn btn-outline-primary" title="Set Harga Khusus Outlet">
                                                <i class="bx bx-purchase-tag"></i>
                                            </button>
                                            <button @click="toggleProductStatus(item)" class="btn" 
                                                    :class="item.outlet_is_active ? 'btn-outline-warning' : 'btn-outline-success'" 
                                                    :title="item.outlet_is_active ? 'Nonaktifkan di Outlet' : 'Aktifkan di Outlet'">
                                                <i class="bx" :class="item.outlet_is_active ? 'bx-block' : 'bx-check-shield'"></i>
                                            </button>
                                            <button @click="detachProduct(item)" class="btn btn-outline-danger" title="Hapus dari Outlet Ini">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="products?.links && products.links.length > 3" class="card-footer bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <small class="text-muted">Menampilkan {{ products.from || 0 }} - {{ products.to || 0 }} dari {{ products.total }} produk</small>
                        <Pagination :links="products.links" />
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- TAB 2: STOK REAL TIME & BATCH FEFO (WITH OUTLET CLARITY)  -->
            <!-- ======================================================== -->
            <div v-if="currentTab === 'realtime'">
                <!-- Summary Widgets -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-4 text-center h-100">
                            <span class="text-muted small fw-semibold">Total Jenis Obat</span>
                            <h3 class="fw-bold text-primary mb-0 mt-1">{{ formatNumber(summary?.total_items) }}</h3>
                            <small class="text-muted style-xs">Master Item Terdaftar</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-4 text-center h-100">
                            <span class="text-muted small fw-semibold">Total Stok Fisik Real</span>
                            <h3 class="fw-bold text-success mb-0 mt-1">{{ formatNumber(summary?.total_physical_stock) }}</h3>
                            <small class="text-muted style-xs">
                                {{ selectedRealtimeOutlet ? 'Unit di ' + selectedRealtimeOutlet.name : 'Akumulasi Seluruh Cabang' }}
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-4 text-center h-100">
                            <span class="text-muted small fw-semibold">Stok Kurang / Minimum</span>
                            <h3 class="fw-bold text-warning mb-0 mt-1">{{ formatNumber(summary?.low_stock_count) }}</h3>
                            <small class="text-muted style-xs">
                                {{ selectedRealtimeOutlet ? 'Perlu Reorder / Transfer ke Outlet Ini' : 'Perlu Reorder ke PBF' }}
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-xs p-3 rounded-4 text-center h-100">
                            <span class="text-muted small fw-semibold">Total Valuasi Aset</span>
                            <h3 class="fw-bold text-info mb-0 mt-1">{{ formatCurrency(summary?.total_asset_valuation) }}</h3>
                            <small class="text-muted style-xs">
                                {{ selectedRealtimeOutlet ? 'Nilai Aset di ' + selectedRealtimeOutlet.name : 'Estimasi Nilai Total Seluruh Cabang' }}
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Source Outlet Clarity Banner -->
                <div class="card border-0 shadow-xs rounded-4 p-3 mb-3 bg-white border-start border-4"
                     :class="selectedRealtimeOutlet ? 'border-primary' : 'border-info'">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-3 p-2.5 d-flex align-items-center justify-content-center"
                                 :class="selectedRealtimeOutlet ? 'bg-primary bg-opacity-10 text-primary' : 'bg-info bg-opacity-10 text-info'">
                                <i class="bx fs-3" :class="selectedRealtimeOutlet ? 'bx-store-alt' : 'bx-globe'"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="fw-bold text-dark mb-0">
                                        Sumber Data Stok Real Time: 
                                        <span class="text-primary" v-if="selectedRealtimeOutlet">
                                            {{ selectedRealtimeOutlet.name }}
                                        </span>
                                        <span class="text-info" v-else>
                                            Akumulasi Seluruh Cabang Apotek
                                        </span>
                                    </h6>
                                    <span v-if="selectedRealtimeOutlet?.is_main" class="badge bg-warning text-dark fw-bold px-2 py-0.5 rounded-pill style-xs">
                                        <i class="bx bx-crown me-1"></i>Pusat
                                    </span>
                                    <span v-else-if="selectedRealtimeOutlet" class="badge bg-secondary text-white px-2 py-0.5 rounded-pill style-xs">
                                        <i class="bx bx-building me-1"></i>Cabang
                                    </span>
                                    <span v-else class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-0.5 rounded-pill style-xs">
                                        Pusat + Semua Cabang
                                    </span>
                                </div>
                                <small class="text-muted d-block mt-0.5">
                                    {{ selectedRealtimeOutlet 
                                        ? 'Menampilkan ketersediaan fisik stok obat dan nilai valuasi khusus pada ' + selectedRealtimeOutlet.name + '.' 
                                        : 'Menampilkan gabungan stok fisik real dari semua cabang. Rincian per masing-masing outlet dapat dilihat langsung di kolom "Rincian Stok Per Outlet".' }}
                                </small>
                            </div>
                        </div>

                        <!-- Outlet Quick Switcher Chips -->
                        <div class="d-flex align-items-center flex-wrap gap-1.5">
                            <button @click="realtimeOutletId = 'all'; applyRealtimeFilters()"
                                    class="btn btn-sm rounded-pill transition-all d-flex align-items-center gap-1 px-3 py-1.5"
                                    :class="realtimeOutletId === 'all' ? 'btn-primary shadow-xs' : 'btn-light border text-secondary'">
                                <i class="bx bx-globe"></i>
                                <span>Semua Outlet (Global)</span>
                            </button>
                            <button v-for="out in outlets" :key="out.id" 
                                    @click="realtimeOutletId = out.id; applyRealtimeFilters()"
                                    class="btn btn-sm rounded-pill transition-all d-flex align-items-center gap-1 px-3 py-1.5"
                                    :class="realtimeOutletId == out.id ? 'btn-primary shadow-xs' : 'btn-light border text-secondary'">
                                <i class="bx" :class="out.is_main ? 'bx-crown text-warning' : 'bx-store'"></i>
                                <span>{{ out.name }}</span>
                                <small class="opacity-75 style-xs">({{ out.is_main ? 'Pusat' : 'Cabang' }})</small>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter & Action Bar -->
                <div class="card border-0 shadow-xs rounded-4 mb-4">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-center">
                            <!-- Outlet Dropdown Filter -->
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 text-primary">
                                        <i class="bx bx-store-alt"></i>
                                    </span>
                                    <select class="form-select border-start-0 ps-0" v-model="realtimeOutletId" @change="applyRealtimeFilters">
                                        <option value="all">🌐 Semua Outlet (Total Akumulasi)</option>
                                        <option v-for="out in outlets" :key="out.id" :value="out.id">
                                            {{ out.is_main ? '🏢 (Pusat)' : '🏪 (Cabang)' }} {{ out.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Search Input -->
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bx bx-search"></i></span>
                                    <input type="text" class="form-control border-start-0 ps-0" v-model="realtimeSearch" @keyup.enter="applyRealtimeFilters" placeholder="Cari obat, kode, kategori...">
                                </div>
                            </div>

                            <!-- Category Filter -->
                            <div class="col-md-2">
                                <select class="form-select" v-model="realtimeCategory" @change="applyRealtimeFilters">
                                    <option value="">Semua Kategori</option>
                                    <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                                </select>
                            </div>

                            <!-- Stock Status Filter -->
                            <div class="col-md-2">
                                <select class="form-select" v-model="realtimeStockStatus" @change="applyRealtimeFilters">
                                    <option value="">Semua Status Stok</option>
                                    <option value="min_stock">🟠 Stok &le; Minimum</option>
                                    <option value="out_of_stock">🔴 Stok Habis (0)</option>
                                </select>
                            </div>

                            <!-- Reset & Print Buttons -->
                            <div class="col-md-2 d-flex gap-2">
                                <button @click="resetRealtimeFilters" class="btn btn-light border rounded-3" title="Reset Filter">
                                    <i class="bx bx-reset"></i>
                                </button>
                                <button class="btn btn-outline-secondary w-100 rounded-3 d-flex align-items-center justify-content-center gap-1" onclick="window.print()">
                                    <i class="bx bx-printer"></i>
                                    <span>Print</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Global Medicines & Batch Table -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-0 py-3.5 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bx bx-package text-primary fs-4"></i>
                            <h5 class="fw-bold mb-0 text-dark">Data Stok Real Time & Lokasi Fisik Obat</h5>
                            <span v-if="selectedRealtimeOutlet" class="badge bg-primary bg-opacity-10 text-primary px-2.5 py-1 rounded-pill">
                                Outlet: {{ selectedRealtimeOutlet.name }}
                            </span>
                            <span v-else class="badge bg-info bg-opacity-10 text-info px-2.5 py-1 rounded-pill">
                                Menampilkan Semua Cabang
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <Link :href="route('inventory.movements')" class="btn btn-sm btn-outline-primary fw-bold rounded-pill">
                                <i class="bx bx-transfer me-1"></i> Lihat Kartu Stok
                            </Link>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted style-xs text-uppercase">
                                <tr>
                                    <th class="ps-4" style="width: 120px;">Kode</th>
                                    <th>Nama Obat</th>
                                    <th>Kategori</th>
                                    <th class="text-center" style="width: 100px;">Min / Max</th>
                                    <th class="text-center" style="min-width: 140px;">
                                        <span v-if="selectedRealtimeOutlet">
                                            Stok di {{ selectedRealtimeOutlet.code || selectedRealtimeOutlet.name }}
                                        </span>
                                        <span v-else>Total Stok Fisik</span>
                                    </th>
                                    <th style="min-width: 260px;">Rincian Stok Per Outlet</th>
                                    <th class="text-end" style="width: 140px;">Valuasi (Rp)</th>
                                    <th class="text-center" style="width: 110px;">Kondisi</th>
                                    <th class="text-center pe-4" style="width: 120px;">Batch FEFO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!medicines?.data || medicines.data.length === 0">
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="bx bx-box fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                        <span>Tidak ada data obat yang sesuai dengan kriteria pencarian.</span>
                                    </td>
                                </tr>
                                <tr v-for="item in medicines?.data" :key="item.kode">
                                    <td class="ps-4"><code class="fw-bold text-primary">{{ item.kode }}</code></td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ item.nama }}</div>
                                        <span v-if="item.jenis_obat" class="badge bg-light text-secondary border style-xs">{{ item.jenis_obat }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-0.5 small">{{ item.kategori || '-' }}</span>
                                    </td>
                                    <td class="text-center text-muted small">
                                        {{ item.min_stok }} / {{ item.max_stok }}
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold fs-6" :class="{ 'text-danger': item.stok <= 0, 'text-warning': item.stok > 0 && item.stok <= item.min_stok, 'text-success': item.stok > item.min_stok }">
                                            {{ Number(item.stok).toLocaleString('id-ID') }}
                                        </span>
                                        <small v-if="selectedRealtimeOutlet" class="text-muted d-block style-xs">
                                            Total Semua: {{ Number(item.global_stok || item.stok).toLocaleString('id-ID') }}
                                        </small>
                                        <span v-else class="badge bg-light text-secondary border d-block mx-auto mt-0.5 style-xs" style="width: fit-content;">
                                            Semua Cabang
                                        </span>
                                    </td>
                                    <!-- Rincian Stok Per Outlet -->
                                    <td>
                                        <div class="d-flex flex-wrap gap-1.5">
                                            <span v-for="ob in item.outlet_breakdown" :key="ob.outlet_id" 
                                                  class="badge style-xs px-2.5 py-1 border d-inline-flex align-items-center gap-1 rounded-pill"
                                                  :class="{
                                                      'bg-primary bg-opacity-10 text-primary border-primary border-opacity-25': ob.is_main && ob.stock > 0,
                                                      'bg-info bg-opacity-10 text-dark border-info border-opacity-25': !ob.is_main && ob.stock > 0,
                                                      'bg-danger bg-opacity-10 text-danger border-danger border-opacity-25': ob.stock <= 0
                                                  }">
                                                <i class="bx" :class="ob.is_main ? 'bx-crown text-warning' : 'bx-store'"></i>
                                                <span>{{ ob.outlet_name }}:</span>
                                                <strong class="fs-7">{{ Number(ob.stock).toLocaleString('id-ID') }}</strong>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-end fw-semibold text-dark">
                                        {{ formatCurrency(item.total_asset_value) }}
                                    </td>
                                    <td class="text-center">
                                        <span v-if="item.status_badge === 'OUT_OF_STOCK'" class="badge bg-danger">Habis</span>
                                        <span v-else-if="item.status_badge === 'LOW_STOCK'" class="badge bg-warning text-dark">Stok Rendah</span>
                                        <span v-else-if="item.status_badge === 'NEAR_EXPIRED'" class="badge bg-danger">Exp Mendekati</span>
                                        <span v-else class="badge bg-success">Aman</span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <button @click="openBatchModal(item)" class="btn btn-sm btn-outline-info rounded-pill px-3 shadow-xs">
                                            <i class="bx bx-layer me-1"></i>
                                            <span>{{ item.batches?.length || 0 }} Batch</span>
                                            <span v-if="item.near_expired_count > 0" class="badge bg-danger ms-1">!</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="medicines?.links && medicines.links.length > 3" class="card-footer bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <small class="text-muted">Menampilkan {{ medicines.from || 0 }} - {{ medicines.to || 0 }} dari {{ medicines.total }} data</small>
                        <Pagination :links="medicines.links" />
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL: Tambahkan Produk Global ke Outlet                  -->
        <!-- ======================================================== -->
        <div v-if="showAddModal" class="modal fade show d-block bg-dark bg-opacity-50" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                            <i class="bx bx-plus-circle"></i>
                            <span>Tambahkan Produk ke Outlet "{{ selectedOutlet?.name }}"</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="showAddModal = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted small">
                            Pilih produk dari Katalog Master Produk Global yang belum terdaftar pada outlet ini.
                        </p>

                        <div v-if="availableGlobalProducts?.length === 0" class="alert alert-info border-0 rounded-3 text-center py-4">
                            <i class="bx bx-check-double fs-2 d-block mb-2 text-info"></i>
                            <strong>Semua produk master global sudah terdaftar di outlet ini!</strong>
                        </div>
                        <div v-else>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAll" v-model="selectAllGlobal" @change="toggleSelectAllGlobal" />
                                    <label class="form-check-label fw-bold text-dark cursor-pointer" for="selectAll">
                                        Pilih Semua ({{ availableGlobalProducts.length }} produk)
                                    </label>
                                </div>
                                <span class="badge bg-primary rounded-pill px-3 py-1.5">{{ addForm.obat_kodes.length }} Dipilih</span>
                            </div>

                            <div class="border rounded-3 p-2 bg-light style-custom-scroll" style="max-height: 320px; overflow-y: auto;">
                                <div v-for="gProduct in availableGlobalProducts" :key="gProduct.kode" class="form-check p-2 border-bottom hover-bg-white rounded transition-all">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" :id="'chk_' + gProduct.kode" :value="gProduct.kode" v-model="addForm.obat_kodes" />
                                    <label class="form-check-label w-100 d-flex justify-content-between align-items-center cursor-pointer" :for="'chk_' + gProduct.kode">
                                        <div>
                                            <strong class="text-dark">{{ gProduct.nama }}</strong>
                                            <small class="text-muted ms-2 font-mono">({{ gProduct.kode }})</small>
                                        </div>
                                        <div>
                                            <span class="badge bg-secondary me-2">{{ gProduct.kategori || 'Umum' }}</span>
                                            <span class="fw-bold text-success small">{{ formatCurrency(gProduct.harga) }}</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary rounded-3" @click="showAddModal = false">Batal</button>
                        <button type="button" class="btn btn-primary rounded-3" :disabled="addForm.processing || addForm.obat_kodes.length === 0" @click="submitAddProducts">
                            <i class="bx bx-check me-1"></i> Tambahkan {{ addForm.obat_kodes.length }} Produk
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL: Edit Harga Khusus Outlet                           -->
        <!-- ======================================================== -->
        <div v-if="showPriceModal" class="modal fade show d-block bg-dark bg-opacity-50" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                            <i class="bx bx-purchase-tag"></i>
                            <span>Set Harga Khusus Outlet</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="showPriceModal = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-light border mb-3 rounded-3">
                            <div class="fw-bold text-dark">{{ editingProduct?.nama }}</div>
                            <small class="text-muted">
                                Kode: <code>{{ editingProduct?.obat_id }}</code> | Harga Default Global: <strong>{{ formatCurrency(editingProduct?.default_price) }}</strong>
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Harga Jual Khusus Outlet (Rp)</label>
                            <input v-model="priceForm.price" type="number" class="form-control form-control-lg" placeholder="Kosongkan jika ingin memakai Harga Default Global" />
                            <small class="text-muted d-block mt-1">Jika dikosongkan, outlet ini akan otomatis menggunakan Harga Default Global ({{ formatCurrency(editingProduct?.default_price) }}).</small>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary rounded-3" @click="showPriceModal = false">Batal</button>
                        <button type="button" class="btn btn-primary rounded-3" :disabled="priceForm.processing" @click="submitUpdatePrice">
                            <i class="bx bx-save me-1"></i> Simpan Harga Outlet
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL: Detail Batch Expired (FEFO)                        -->
        <!-- ======================================================== -->
        <div v-if="showBatchModal" class="modal fade show d-block bg-dark bg-opacity-50" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                            <i class="bx bx-layer"></i>
                            <span>Rincian Batch FEFO & Lokasi Stok</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="showBatchModal = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <!-- Medicine Header & Outlet Breakdown Alert -->
                        <div class="alert alert-light border rounded-3 mb-3 p-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h5 class="fw-bold text-dark mb-0">{{ selectedBatchMedicine?.nama }}</h5>
                                    <small class="text-muted">Kode: <code>{{ selectedBatchMedicine?.kode }}</code> | Kategori: {{ selectedBatchMedicine?.kategori || '-' }}</small>
                                </div>
                                <div>
                                    <span class="badge bg-primary fs-6 px-3 py-1.5 rounded-pill shadow-xs">
                                        Total Fisik: {{ Number(selectedBatchMedicine?.stok).toLocaleString('id-ID') }} unit
                                    </span>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div>
                                <small class="text-muted fw-bold d-block mb-1.5">
                                    <i class="bx bx-map-pin text-primary me-1"></i>Ketersediaan Fisik Stok di Setiap Outlet:
                                </small>
                                <div class="d-flex flex-wrap gap-2">
                                    <span v-for="ob in selectedBatchMedicine?.outlet_breakdown" :key="ob.outlet_id"
                                          class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-xs d-inline-flex align-items-center gap-1.5">
                                        <i class="bx" :class="ob.is_main ? 'bx-crown text-warning' : 'bx-store text-primary'"></i>
                                        <span>{{ ob.outlet_name }}:</span>
                                        <strong class="fs-7" :class="ob.stock > 0 ? 'text-success' : 'text-danger'">
                                            {{ Number(ob.stock).toLocaleString('id-ID') }} unit
                                        </strong>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Batch Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0">
                                <thead class="table-light style-xs text-uppercase">
                                    <tr>
                                        <th>No. Batch</th>
                                        <th>Tanggal Expired</th>
                                        <th class="text-end">Sisa Stok Fisik</th>
                                        <th class="text-center">Status FEFO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!selectedBatchMedicine?.batches || selectedBatchMedicine.batches.length === 0">
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            Tidak ada batch aktif dengan sisa stok fisik.
                                        </td>
                                    </tr>
                                    <tr v-for="b in selectedBatchMedicine?.batches" :key="b.id">
                                        <td><code>{{ b.batch_number }}</code></td>
                                        <td>{{ b.expired_date }}</td>
                                        <td class="text-end fw-bold">{{ Number(b.stock).toLocaleString('id-ID') }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-success">Tersedia</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary rounded-3" @click="showBatchModal = false">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>

<style scoped>
.bg-gradient-outlet {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
}

.backdrop-blur {
    backdrop-filter: blur(10px);
}

.style-xs {
    font-size: 0.73rem;
}

.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
}

.cursor-pointer {
    cursor: pointer;
}

.btn-ghost {
    background: transparent;
    border: none;
}

.btn-ghost:hover {
    background: rgba(0, 0, 0, 0.05);
}

.hover-bg-white:hover {
    background-color: #ffffff;
}

.style-custom-scroll::-webkit-scrollbar {
    width: 6px;
}
.style-custom-scroll::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 4px;
}
</style>
