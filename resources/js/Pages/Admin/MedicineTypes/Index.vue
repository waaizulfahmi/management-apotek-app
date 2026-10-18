<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref, watch } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    types: Object,
    stats: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const showAddModal = ref(false);
const showEditModal = ref(false);
const editingType = ref(null);

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
    router.get(route('admin.medicine-types.index'), { search: search.value }, { preserveState: true, replace: true });
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
    addForm.post(route('admin.medicine-types.store'), {
        onSuccess: () => {
            closeAddModal();
        }
    });
};

const openEditModal = (item) => {
    editingType.value = item;
    editForm.name = item.name;
    editForm.description = item.description || '';
    editForm.is_active = Boolean(item.is_active);
    editForm.clearErrors();
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingType.value = null;
};

const submitEdit = () => {
    if (!editingType.value) return;
    editForm.put(route('admin.medicine-types.update', editingType.value.id), {
        onSuccess: () => {
            closeEditModal();
        }
    });
};

const toggleStatus = (item) => {
    router.post(route('admin.medicine-types.toggle-status', item.id), {}, { preserveScroll: true });
};

const deleteType = (item) => {
    if (confirm(`Apakah Anda yakin ingin menghapus jenis obat '${item.name}'? Data dapat dipulihkan dari menu Trash.`)) {
        router.delete(route('admin.medicine-types.destroy', item.id), { preserveScroll: true });
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
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill small fw-semibold">
                                <i class="bx bx-shape-polygon me-1"></i>Master Farmasi
                            </span>
                        </div>
                        <h2 class="fw-bold text-dark mb-0">Master Jenis Obat (Bentuk Sediaan)</h2>
                        <p class="text-muted small mb-0">Kelola master bentuk fisik sediaan obat (Tablet, Kapsul, Sirup, Salep, Injeksi, dll.)</p>
                    </div>

                    <button class="btn btn-primary d-flex align-items-center gap-2 shadow-sm rounded-3 px-3 py-2" @click="openAddModal">
                        <i class="bx bx-plus fs-5"></i>
                        <span>Tambah Jenis Obat</span>
                    </button>
                </div>

                <!-- Navigation Tabs / Quick Switcher -->
                <div class="d-flex gap-2 mb-4 p-1 bg-light rounded-3 border" style="width: fit-content;">
                    <Link :href="route('admin.units.index')" class="btn btn-sm rounded-2 px-3 fw-semibold text-secondary">
                        <i class="bx bx-purchase-tag-alt me-1"></i>Master Satuan
                    </Link>
                    <Link :href="route('admin.medicine-types.index')" class="btn btn-sm rounded-2 px-3 fw-semibold bg-white text-primary shadow-xs">
                        <i class="bx bx-shape-polygon me-1"></i>Master Jenis Obat
                    </Link>
                    <Link :href="route('admin.medicine-categories.index')" class="btn btn-sm rounded-2 px-3 fw-semibold text-secondary">
                        <i class="bx bx-category-alt me-1"></i>Master Kategori Obat
                    </Link>
                </div>

                <!-- Stat Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold d-block mb-1">Total Jenis Obat</span>
                                    <h3 class="fw-bold text-dark mb-0">{{ stats?.total || 0 }}</h3>
                                </div>
                                <div class="rounded-4 bg-primary-subtle text-primary p-3 d-flex align-items-center justify-content-center">
                                    <i class="bx bx-shape-polygon fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold d-block mb-1">Jenis Aktif Digunakan</span>
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
                                    <span class="text-muted small fw-semibold d-block mb-1">Dinonaktifkan</span>
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
                            <input type="text" class="form-control bg-light border-0" placeholder="Cari nama atau deskripsi..." v-model="search">
                        </div>

                        <div class="text-muted small">
                            Total: <strong class="text-dark">{{ types.total || 0 }}</strong> Jenis Sediaan
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light text-uppercase small text-muted">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Nama Jenis Obat</th>
                                    <th>Keterangan / Karakteristik Fisik</th>
                                    <th class="text-center" style="width: 140px;">Jumlah Obat</th>
                                    <th>Status</th>
                                    <th>Tanggal Dibuat</th>
                                    <th class="text-end" style="width: 150px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(type, index) in types.data" :key="type.id">
                                    <td class="text-muted small fw-semibold">{{ (types.current_page - 1) * types.per_page + index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-icon rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-weight: 700;">
                                                {{ type.name.substring(0, 2).toUpperCase() }}
                                            </div>
                                            <div>
                                                <span class="fw-bold text-dark d-block">{{ type.name }}</span>
                                                <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.68rem;">{{ type.slug || '-' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted small" style="max-width: 340px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            {{ type.description || 'Tidak ada keterangan khusus.' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1">
                                            <i class="bx bx-capsule me-1"></i>{{ type.obats_count || 0 }} Obat
                                        </span>
                                    </td>
                                    <td>
                                        <button 
                                            class="btn btn-sm border-0 rounded-pill px-3 py-1 fw-medium"
                                            :class="type.is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'"
                                            @click="toggleStatus(type)"
                                            title="Klik untuk mengubah status aktif/nonaktif"
                                        >
                                            <i class="bx me-1" :class="type.is_active ? 'bx-check-circle' : 'bx-x-circle'"></i>
                                            {{ type.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="text-muted small">{{ formatDate(type.created_at) }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <button class="btn btn-light btn-sm rounded-circle p-2" @click="openEditModal(type)" title="Edit Jenis Obat">
                                                <i class="bx bx-edit text-primary" style="font-size: 1.1rem;"></i>
                                            </button>
                                            <button class="btn btn-light btn-sm rounded-circle p-2" @click="deleteType(type)" title="Hapus Jenis Obat (Soft Delete)">
                                                <i class="bx bx-trash text-danger" style="font-size: 1.1rem;"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!types.data || types.data.length === 0">
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bx bx-shape-polygon fs-1 d-block mb-2 text-secondary"></i>
                                        Belum ada data jenis obat. Silakan klik tombol "Tambah Jenis Obat".
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4" v-if="types.links && types.links.length > 3">
                        <span class="text-muted small">Menampilkan {{ types.from || 0 }} - {{ types.to || 0 }} dari {{ types.total || 0 }} data</span>
                        <div class="pagination-buttons d-flex gap-1">
                            <button
                                v-for="(link, i) in types.links"
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

        <!-- Modal Tambah Jenis Obat -->
        <div class="modal fade show d-block" tabindex="-1" v-if="showAddModal" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Tambah Jenis Obat Baru</h5>
                        <button type="button" class="btn-close" @click="closeAddModal"></button>
                    </div>
                    <form @submit.prevent="submitAdd">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Nama Jenis Obat / Bentuk Sediaan <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control rounded-3" 
                                    placeholder="Contoh: Tablet, Kapsul, Puyer, Sirup..." 
                                    v-model="addForm.name"
                                    :class="{ 'is-invalid': addForm.errors.name }"
                                    required
                                >
                                <div class="invalid-feedback" v-if="addForm.errors.name">{{ addForm.errors.name }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Deskripsi / Keterangan Bentuk Sediaan</label>
                                <textarea 
                                    class="form-control rounded-3" 
                                    rows="3" 
                                    placeholder="Contoh: Sediaan padat berbentuk butiran kempa..." 
                                    v-model="addForm.description"
                                ></textarea>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="addTypeStatus" v-model="addForm.is_active">
                                <label class="form-check-label small fw-semibold" for="addTypeStatus">Status Aktif (Dapat dipilih di form obat)</label>
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

        <!-- Modal Edit Jenis Obat -->
        <div class="modal fade show d-block" tabindex="-1" v-if="showEditModal" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Edit Jenis Obat</h5>
                        <button type="button" class="btn-close" @click="closeEditModal"></button>
                    </div>
                    <form @submit.prevent="submitEdit">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Nama Jenis Obat <span class="text-danger">*</span></label>
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
                                <label class="form-label small fw-semibold text-muted">Deskripsi / Keterangan</label>
                                <textarea 
                                    class="form-control rounded-3" 
                                    rows="3" 
                                    v-model="editForm.description"
                                ></textarea>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editTypeStatus" v-model="editForm.is_active">
                                <label class="form-check-label small fw-semibold" for="editTypeStatus">Status Aktif</label>
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
