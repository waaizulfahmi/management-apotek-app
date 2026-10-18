<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref, watch } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    categories: Object,
    stats: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const showAddModal = ref(false);
const showEditModal = ref(false);
const editingCategory = ref(null);

const addForm = useForm({
    name: '',
    description: '',
    is_active: true,
});

const editForm = useForm({
    name: '',
    description: '',
    is_active: true,
});

const handleSearch = () => {
    router.get(route('admin.medicine-categories.index'), { search: search.value }, { preserveState: true, replace: true });
};

watch(search, () => {
    handleSearch();
});

const openAddModal = () => {
    addForm.reset();
    addForm.clearErrors();
    showAddModal.value = true;
};

const closeAddModal = () => {
    showAddModal.value = false;
};

const submitAdd = () => {
    addForm.post(route('admin.medicine-categories.store'), {
        onSuccess: () => {
            closeAddModal();
        }
    });
};

const openEditModal = (item) => {
    editingCategory.value = item;
    editForm.name = item.name;
    editForm.description = item.description || '';
    editForm.is_active = Boolean(item.is_active);
    editForm.clearErrors();
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingCategory.value = null;
};

const submitEdit = () => {
    if (!editingCategory.value) return;
    editForm.put(route('admin.medicine-categories.update', editingCategory.value.id), {
        onSuccess: () => {
            closeEditModal();
        }
    });
};

const toggleStatus = (item) => {
    router.post(route('admin.medicine-categories.toggle-status', item.id), {}, { preserveScroll: true });
};

const deleteCategory = (item) => {
    if (confirm(`Apakah Anda yakin ingin menghapus kategori obat '${item.name}'? Data dapat dipulihkan dari menu Trash.`)) {
        router.delete(route('admin.medicine-categories.destroy', item.id), { preserveScroll: true });
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill small fw-semibold">
                                <i class="bx bx-category-alt me-1"></i>Master Farmakologi
                            </span>
                        </div>
                        <h2 class="fw-bold text-dark mb-0">Master Kategori Obat (Golongan Terapi)</h2>
                        <p class="text-muted small mb-0">Kelola master klasifikasi kategori obat (Antibiotik, Analgesik, Vitamin, Antihistamin, dll.)</p>
                    </div>

                    <button class="btn btn-primary d-flex align-items-center gap-2 shadow-sm rounded-3 px-3 py-2" @click="openAddModal">
                        <i class="bx bx-plus fs-5"></i>
                        <span>Tambah Kategori Obat</span>
                    </button>
                </div>

                <!-- Navigation Tabs / Quick Switcher -->
                <div class="d-flex gap-2 mb-4 p-1 bg-light rounded-3 border" style="width: fit-content;">
                    <Link :href="route('admin.units.index')" class="btn btn-sm rounded-2 px-3 fw-semibold text-secondary">
                        <i class="bx bx-purchase-tag-alt me-1"></i>Master Satuan
                    </Link>
                    <Link :href="route('admin.medicine-types.index')" class="btn btn-sm rounded-2 px-3 fw-semibold text-secondary">
                        <i class="bx bx-shape-polygon me-1"></i>Master Jenis Obat
                    </Link>
                    <Link :href="route('admin.medicine-categories.index')" class="btn btn-sm rounded-2 px-3 fw-semibold bg-white text-primary shadow-xs">
                        <i class="bx bx-category-alt me-1"></i>Master Kategori Obat
                    </Link>
                </div>

                <!-- Stat Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold d-block mb-1">Total Kategori Obat</span>
                                    <h3 class="fw-bold text-dark mb-0">{{ stats?.total || 0 }}</h3>
                                </div>
                                <div class="rounded-4 bg-primary-subtle text-primary p-3 d-flex align-items-center justify-content-center">
                                    <i class="bx bx-category-alt fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold d-block mb-1">Kategori Aktif</span>
                                    <h3 class="fw-bold text-success mb-0">{{ stats?.active || 0 }}</h3>
                                </div>
                                <div class="rounded-4 bg-success-subtle text-success p-3 d-flex align-items-center justify-content-center">
                                    <i class="bx bx-check-circle fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold d-block mb-1">Kategori Nonaktif</span>
                                    <h3 class="fw-bold text-secondary mb-0">{{ stats?.inactive || 0 }}</h3>
                                </div>
                                <div class="rounded-4 bg-secondary-subtle text-secondary p-3 d-flex align-items-center justify-content-center">
                                    <i class="bx bx-pause-circle fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                        <div class="input-group style-search" style="max-width: 320px;">
                            <span class="input-group-text bg-light border-0"><i class="bx bx-search text-muted"></i></span>
                            <input type="text" class="form-control bg-light border-0" placeholder="Cari kategori atau deskripsi..." v-model="search">
                        </div>

                        <div class="text-muted small">
                            Total: <strong class="text-dark">{{ categories.total || 0 }}</strong> Kategori
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light text-uppercase small text-muted">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Nama Kategori Obat</th>
                                    <th>Kegunaan / Indikasi Terapi</th>
                                    <th class="text-center" style="width: 140px;">Jumlah Obat</th>
                                    <th>Status</th>
                                    <th>Tanggal Dibuat</th>
                                    <th class="text-end" style="width: 150px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(cat, index) in categories.data" :key="cat.id">
                                    <td class="text-muted small fw-semibold">{{ (categories.current_page - 1) * categories.per_page + index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-icon rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-weight: 700;">
                                                {{ cat.name.substring(0, 2).toUpperCase() }}
                                            </div>
                                            <div>
                                                <span class="fw-bold text-dark d-block">{{ cat.name }}</span>
                                                <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.68rem;">{{ cat.slug || '-' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted small" style="max-width: 340px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            {{ cat.description || 'Tidak ada deskripsi khusus.' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1">
                                            <i class="bx bx-capsule me-1"></i>{{ cat.obats_count || 0 }} Obat
                                        </span>
                                    </td>
                                    <td>
                                        <button 
                                            class="btn btn-sm border-0 rounded-pill px-3 py-1 fw-medium"
                                            :class="cat.is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'"
                                            @click="toggleStatus(cat)"
                                            title="Klik untuk mengubah status aktif/nonaktif"
                                        >
                                            <i class="bx me-1" :class="cat.is_active ? 'bx-check-circle' : 'bx-x-circle'"></i>
                                            {{ cat.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="text-muted small">{{ formatDate(cat.created_at) }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <button class="btn btn-light btn-sm rounded-circle p-2" @click="openEditModal(cat)" title="Edit Kategori Obat">
                                                <i class="bx bx-edit text-primary" style="font-size: 1.1rem;"></i>
                                            </button>
                                            <button class="btn btn-light btn-sm rounded-circle p-2" @click="deleteCategory(cat)" title="Hapus Kategori (Soft Delete)">
                                                <i class="bx bx-trash text-danger" style="font-size: 1.1rem;"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!categories.data || categories.data.length === 0">
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bx bx-category-alt fs-1 d-block mb-2 text-secondary"></i>
                                        Belum ada data kategori obat. Silakan klik tombol "Tambah Kategori Obat".
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4" v-if="categories.links && categories.links.length > 3">
                        <span class="text-muted small">Menampilkan {{ categories.from || 0 }} - {{ categories.to || 0 }} dari {{ categories.total || 0 }} data</span>
                        <div class="pagination-buttons d-flex gap-1">
                            <button
                                v-for="(link, i) in categories.links"
                                :key="i"
                                class="btn btn-sm rounded-3"
                                :class="link.active ? 'btn-primary' : 'btn-light'"
                                :disabled="!link.url"
                                @click="router.get(link.url)"
                                v-html="link.label"
                            ></button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Modal Tambah Kategori Obat -->
        <div class="modal fade show d-block" tabindex="-1" v-if="showAddModal" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Tambah Kategori Obat Baru</h5>
                        <button type="button" class="btn-close" @click="closeAddModal"></button>
                    </div>
                    <form @submit.prevent="submitAdd">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Nama Kategori / Golongan Terapi <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control rounded-3" 
                                    placeholder="Contoh: Antibiotik, Analgesik, Antasida..." 
                                    v-model="addForm.name"
                                    :class="{ 'is-invalid': addForm.errors.name }"
                                    required
                                >
                                <div class="invalid-feedback" v-if="addForm.errors.name">{{ addForm.errors.name }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Kegunaan / Indikasi Medis</label>
                                <textarea 
                                    class="form-control rounded-3" 
                                    rows="3" 
                                    placeholder="Contoh: Mengobati infeksi saluran pernapasan..." 
                                    v-model="addForm.description"
                                ></textarea>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="addCatStatus" v-model="addForm.is_active">
                                <label class="form-check-label small fw-semibold" for="addCatStatus">Status Aktif (Dapat dipilih di form obat)</label>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light rounded-3 px-3" @click="closeAddModal">Batal</button>
                            <button type="submit" class="btn btn-primary rounded-3 px-4" :disabled="addForm.processing">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Kategori Obat -->
        <div class="modal fade show d-block" tabindex="-1" v-if="showEditModal" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Edit Kategori Obat</h5>
                        <button type="button" class="btn-close" @click="closeEditModal"></button>
                    </div>
                    <form @submit.prevent="submitEdit">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Nama Kategori Obat <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control rounded-3" 
                                    v-model="editForm.name"
                                    :class="{ 'is-invalid': editForm.errors.name }"
                                    required
                                >
                                <div class="invalid-feedback" v-if="editForm.errors.name">{{ editForm.errors.name }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Kegunaan / Indikasi Medis</label>
                                <textarea 
                                    class="form-control rounded-3" 
                                    rows="3" 
                                    v-model="editForm.description"
                                ></textarea>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editCatStatus" v-model="editForm.is_active">
                                <label class="form-check-label small fw-semibold" for="editCatStatus">Status Aktif</label>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light rounded-3 px-3" @click="closeEditModal">Batal</button>
                            <button type="submit" class="btn btn-primary rounded-3 px-4" :disabled="editForm.processing">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
