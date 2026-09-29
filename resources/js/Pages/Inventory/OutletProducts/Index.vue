<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    products: Object,
    outlets: Array,
    selectedOutlet: Object,
    availableGlobalProducts: Array,
    categories: Array,
    filters: Object,
});

const searchFilter = ref(props.filters?.search || '');
const categoryFilter = ref(props.filters?.kategori || '');
const statusFilter = ref(props.filters?.status || 'ALL');

const showAddModal = ref(false);
const showPriceModal = ref(false);
const editingProduct = ref(null);

const addForm = useForm({
    outlet_id: props.selectedOutlet?.id,
    obat_kodes: [],
});

const priceForm = useForm({
    price: null,
});

const handleOutletChange = (outletId) => {
    router.get(route('inventory.outlet-products.index'), {
        outlet_id: outletId,
        search: searchFilter.value,
        kategori: categoryFilter.value,
        status: statusFilter.value,
    }, { preserveState: false, replace: true });
};

const applyFilters = () => {
    router.get(route('inventory.outlet-products.index'), {
        outlet_id: props.selectedOutlet?.id,
        search: searchFilter.value,
        kategori: categoryFilter.value,
        status: statusFilter.value,
    }, { preserveState: true, replace: true });
};

const openAddModal = () => {
    addForm.outlet_id = props.selectedOutlet?.id;
    addForm.obat_kodes = [];
    showAddModal.value = true;
};

const submitAddProducts = () => {
    if (addForm.obat_kodes.length === 0) {
        Swal.fire('Peringatan', 'Silakan pilih setidaknya satu produk untuk ditambahkan.', 'warning');
        return;
    }
    addForm.post(route('inventory.outlet-products.attach'), {
        onSuccess: () => {
            showAddModal.value = false;
            Swal.fire('Berhasil', 'Produk telah ditambahkan ke outlet!', 'success');
        }
    });
};

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

const formatRupiah = (val) => {
    if (val === null || val === undefined || isNaN(val)) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
};

const selectAllGlobal = ref(false);
const toggleSelectAllGlobal = () => {
    if (selectAllGlobal.value) {
        addForm.obat_kodes = props.availableGlobalProducts.map(p => p.kode);
    } else {
        addForm.obat_kodes = [];
    }
};
</script>

<template>
    <Head title="Manajemen Produk Per Outlet" />

    <LegacyLayout>
        <div class="container-fluid py-3">
            <!-- Header Banner Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-gradient-primary text-white p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bx bx-store-alt fs-2 text-warning"></i>
                            <h4 class="fw-bold mb-0">Manajemen Produk Per Outlet</h4>
                        </div>
                        <p class="mb-0 opacity-75 small">
                            Kelola ketersediaan produk, stok fisik, dan harga khusus per outlet/cabang apotek.
                        </p>
                    </div>

                    <!-- Outlet Selector Card -->
                    <div class="bg-white bg-opacity-10 backdrop-blur p-2.5 rounded-3 border border-white border-opacity-25 d-flex align-items-center gap-3">
                        <div>
                            <small class="text-white-50 d-block style-xs">Outlet Terpilih:</small>
                            <div class="dropdown">
                                <button class="btn btn-warning btn-sm dropdown-toggle fw-bold text-dark rounded-pill px-3 shadow-xs" type="button" data-bs-toggle="dropdown">
                                    <i class="bx bx-map-pin me-1"></i>{{ selectedOutlet?.name || 'Pilih Outlet' }}
                                    <span v-if="selectedOutlet?.is_main" class="badge bg-dark text-warning ms-1">Pusat</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li v-for="out in outlets" :key="out.id">
                                        <button class="dropdown-item d-flex align-items-center justify-content-between py-2" @click="handleOutletChange(out.id)">
                                            <span>
                                                <i class="bx bx-store me-1 text-primary"></i> {{ out.name }}
                                                <small v-if="out.is_main" class="text-warning font-semibold">(Pusat)</small>
                                            </span>
                                            <i v-if="selectedOutlet?.id === out.id" class="bx bx-check text-success fs-5 ms-2"></i>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <button @click="openAddModal" class="btn btn-light text-primary fw-bold btn-sm rounded-pill shadow-xs d-flex align-items-center gap-1.5 px-3 py-2">
                            <i class="bx bx-plus-circle fs-5"></i>
                            <span>Tambahkan Produk ke Outlet Ini</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filter Controls & Search -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    <i class="bx bx-search fs-5"></i>
                                </span>
                                <input v-model="searchFilter" @keyup.enter="applyFilters" type="text" 
                                       class="form-control border-start-0 ps-0" placeholder="Cari nama obat atau kode..." />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select v-model="categoryFilter" @change="applyFilters" class="form-select">
                                <option value="">Semua Kategori</option>
                                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select v-model="statusFilter" @change="applyFilters" class="form-select">
                                <option value="ALL">Semua Status</option>
                                <option value="ACTIVE">Aktif (Bisa Dijual)</option>
                                <option value="INACTIVE">Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button @click="applyFilters" class="btn btn-primary rounded-3">
                                <i class="bx bx-filter-alt me-1"></i> Filter Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Table Card -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="bx bx-list-check text-primary fs-5"></i>
                        <span>Daftar Produk Outlet:</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary">{{ selectedOutlet?.name }}</span>
                    </h6>
                    <small class="text-muted">Total: <strong>{{ products.total }}</strong> produk terdaftar</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted style-xs text-uppercase">
                            <tr>
                                <th class="ps-4" style="width: 120px;">Kode</th>
                                <th>Nama Produk</th>
                                <th>Kategori</th>
                                <th class="text-center">Stok Outlet</th>
                                <th class="text-end">Harga Jual</th>
                                <th class="text-center">Status Outlet</th>
                                <th class="text-center pe-4" style="width: 140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="products.data.length === 0">
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bx bx-box fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    <span>Belum ada produk terdaftar di outlet ini atau hasil pencarian tidak ditemukan.</span>
                                </td>
                            </tr>
                            <tr v-for="item in products.data" :key="item.relation_id">
                                <td class="ps-4 fw-mono text-primary fw-semibold">{{ item.obat_id }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ item.nama }}</div>
                                    <small class="text-muted" style="font-size: 0.72rem;">Jenis: {{ item.jenis_obat || '-' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1 small">
                                        {{ item.kategori || 'Umum' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill px-3 py-1.5"
                                          :class="item.current_stock > item.min_stok ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : (item.current_stock > 0 ? 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25' : 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25')">
                                        {{ item.current_stock }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div v-if="item.custom_price !== null" class="fw-bold text-success">
                                        {{ formatRupiah(item.custom_price) }}
                                        <span class="badge bg-success text-white style-xs d-block ms-auto" style="width: fit-content;">Harga Khusus</span>
                                    </div>
                                    <div v-else class="fw-semibold text-dark">
                                        {{ formatRupiah(item.default_price) }}
                                        <small class="text-muted d-block style-xs">(Default Global)</small>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill px-2.5 py-1"
                                          :class="item.outlet_is_active ? 'bg-success text-white' : 'bg-danger text-white'">
                                        <i class="bx me-1" :class="item.outlet_is_active ? 'bx-check-circle' : 'bx-x-circle'"></i>
                                        {{ item.outlet_is_active ? 'AKTIF' : 'NONAKTIF' }}
                                    </span>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="btn-group btn-group-sm shadow-xs">
                                        <button @click="openEditPriceModal(item)" class="btn btn-outline-primary" title="Set Harga Khusus Outlet">
                                            <i class="bx bx-purchase-tag"></i>
                                        </button>
                                        <button @click="toggleProductStatus(item)" class="btn" :class="item.outlet_is_active ? 'btn-outline-warning' : 'btn-outline-success'" :title="item.outlet_is_active ? 'Nonaktifkan di Outlet' : 'Aktifkan di Outlet'">
                                            <i class="bx" :class="item.outlet_is_active ? 'bx-block' : 'bx-check-shield'"></i>
                                        </button>
                                        <button @click="detachProduct(item)" class="btn btn-outline-danger" title="Hapus dari Outlet ini">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div v-if="products.links && products.links.length > 3" class="card-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted">Menampilkan {{ products.from || 0 }} - {{ products.to || 0 }} dari {{ products.total }} data</small>
                    <div class="btn-group">
                        <Link v-for="(link, k) in products.links" :key="k" 
                              :href="link.url || '#'" 
                              class="btn btn-sm"
                              :class="link.active ? 'btn-primary' : 'btn-outline-secondary'"
                              v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Tambahkan Produk Global ke Outlet -->
        <div v-if="showAddModal" class="modal fade show d-block bg-dark bg-opacity-50" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow rounded-4">
                    <div class="modal-header bg-primary text-white rounded-top-4">
                        <h5 class="modal-title fw-bold">
                            <i class="bx bx-plus-circle me-1"></i> Tambahkan Produk ke Outlet "{{ selectedOutlet?.name }}"
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="showAddModal = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted small">
                            Pilih produk dari Katalog Master Produk Global yang belum terdaftar pada outlet ini.
                        </p>

                        <div v-if="availableGlobalProducts.length === 0" class="alert alert-info border-0 rounded-3 text-center py-4">
                            <i class="bx bx-check-double fs-2 d-block mb-2 text-info"></i>
                            <strong>Semua produk global sudah terdaftar di outlet ini!</strong>
                        </div>
                        <div v-else>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAll" v-model="selectAllGlobal" @change="toggleSelectAllGlobal" />
                                    <label class="form-check-label fw-bold text-dark" for="selectAll">Pilih Semua ({{ availableGlobalProducts.length }} produk)</label>
                                </div>
                                <span class="badge bg-primary rounded-pill px-3">{{ addForm.obat_kodes.length }} Dipilih</span>
                            </div>

                            <div class="border rounded-3 p-2 bg-light style-custom-scroll" style="max-height: 320px; overflow-y: auto;">
                                <div v-for="gProduct in availableGlobalProducts" :key="gProduct.kode" class="form-check p-2 border-bottom hover-bg-white rounded">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" :id="'chk_' + gProduct.kode" :value="gProduct.kode" v-model="addForm.obat_kodes" />
                                    <label class="form-check-label w-100 d-flex justify-content-between align-items-center" :for="'chk_' + gProduct.kode">
                                        <div>
                                            <strong class="text-dark">{{ gProduct.nama }}</strong>
                                            <small class="text-muted ms-2 font-mono">({{ gProduct.kode }})</small>
                                        </div>
                                        <div>
                                            <span class="badge bg-secondary me-2">{{ gProduct.kategori || 'Umum' }}</span>
                                            <span class="fw-bold text-success small">{{ formatRupiah(gProduct.harga) }}</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-secondary rounded-3" @click="showAddModal = false">Batal</button>
                        <button type="button" class="btn btn-primary rounded-3" :disabled="addForm.processing || addForm.obat_kodes.length === 0" @click="submitAddProducts">
                            <i class="bx bx-check me-1"></i> Tambahkan {{ addForm.obat_kodes.length }} Produk
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Edit Harga Khusus Outlet -->
        <div v-if="showPriceModal" class="modal fade show d-block bg-dark bg-opacity-50" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow rounded-4">
                    <div class="modal-header bg-primary text-white rounded-top-4">
                        <h5 class="modal-title fw-bold">
                            <i class="bx bx-purchase-tag me-1"></i> Set Harga Khusus Outlet
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="showPriceModal = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-light border mb-3">
                            <div class="fw-bold text-dark">{{ editingProduct?.nama }}</div>
                            <small class="text-muted">Kode: {{ editingProduct?.obat_id }} | Harga Default Global: <strong>{{ formatRupiah(editingProduct?.default_price) }}</strong></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Harga Jual Khusus Outlet (Rp)</label>
                            <input v-model="priceForm.price" type="number" class="form-control form-control-lg" placeholder="Kosongkan jika ingin pakai Harga Default Global" />
                            <small class="text-muted d-block mt-1">Jika dikosongkan, outlet ini akan otomatis menggunakan Harga Default Global ({{ formatRupiah(editingProduct?.default_price) }}).</small>
                        </div>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-secondary rounded-3" @click="showPriceModal = false">Batal</button>
                        <button type="button" class="btn btn-primary rounded-3" :disabled="priceForm.processing" @click="submitUpdatePrice">
                            <i class="bx bx-save me-1"></i> Simpan Harga Outlet
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>

<style scoped>
.bg-gradient-primary {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
}

.backdrop-blur {
    backdrop-filter: blur(10px);
}

.style-xs {
    font-size: 0.72rem;
}

.hover-bg-white:hover {
    background-color: #ffffff;
}
</style>
