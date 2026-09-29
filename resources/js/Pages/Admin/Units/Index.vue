<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    units: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const showAddModal = ref(false);
const showEditModal = ref(false);
const editingUnit = ref(null);

const addForm = useForm({
    name: '',
    is_active: true,
});

const editForm = useForm({
    name: '',
    is_active: true,
});

const handleSearch = () => {
    router.get(route('admin.units.index'), { search: search.value }, { preserveState: true, replace: true });
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
    addForm.post(route('admin.units.store'), {
        onSuccess: () => {
            closeAddModal();
        }
    });
};

const openEditModal = (unit) => {
    editingUnit.value = unit;
    editForm.name = unit.name;
    editForm.is_active = unit.is_active;
    editForm.clearErrors();
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingUnit.value = null;
};

const submitEdit = () => {
    if (!editingUnit.value) return;
    editForm.put(route('admin.units.update', editingUnit.value.id), {
        onSuccess: () => {
            closeEditModal();
        }
    });
};

const toggleStatus = (unit) => {
    router.post(route('admin.units.toggle-status', unit.id), {}, { preserveScroll: true });
};

const deleteUnit = (unit) => {
    if (confirm(`Apakah Anda yakin ingin menghapus (soft delete) satuan '${unit.name}'?`)) {
        router.delete(route('admin.units.destroy', unit.id), { preserveScroll: true });
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
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold text-dark mb-0">Master Satuan Obat</h2>
                        <p class="text-muted small mb-0">Kelola master data satuan kemasan obat & produk apotek</p>
                    </div>

                    <button class="btn btn-primary d-flex align-items-center gap-2 shadow-sm rounded-3 px-3 py-2" @click="openAddModal">
                        <i class="bx bx-plus fs-5"></i>
                        <span>Tambah Satuan</span>
                    </button>
                </div>

                <!-- Table Card -->
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                        <div class="input-group style-search" style="max-width: 320px;">
                            <span class="input-group-text bg-light border-0"><i class="bx bx-search text-muted"></i></span>
                            <input type="text" class="form-control bg-light border-0" placeholder="Cari nama satuan..." v-model="search">
                        </div>

                        <div class="text-muted small">
                            Total: <strong class="text-dark">{{ units.total || 0 }}</strong> Satuan
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light text-uppercase small text-muted">
                                <tr>
                                    <th style="width: 70px;">#</th>
                                    <th>Nama Satuan</th>
                                    <th>Status</th>
                                    <th>Tanggal Dibuat</th>
                                    <th class="text-end" style="width: 160px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(unit, index) in units.data" :key="unit.id">
                                    <td class="text-muted small fw-semibold">{{ (units.current_page - 1) * units.per_page + index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-icon rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-weight: 600;">
                                                {{ unit.name.substring(0, 2).toUpperCase() }}
                                            </div>
                                            <div>
                                                <span class="fw-bold text-dark d-block">{{ unit.name }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <button 
                                            class="btn btn-sm border-0 rounded-pill px-3 py-1 fw-medium"
                                            :class="unit.is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'"
                                            @click="toggleStatus(unit)"
                                            title="Klik untuk mengubah status"
                                        >
                                            <i class="bx me-1" :class="unit.is_active ? 'bx-check-circle' : 'bx-x-circle'"></i>
                                            {{ unit.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="text-muted small">{{ formatDate(unit.created_at) }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <button class="btn btn-light btn-sm rounded-circle p-2" @click="openEditModal(unit)" title="Edit Satuan">
                                                <i class="bx bx-edit text-primary" style="font-size: 1.1rem;"></i>
                                            </button>
                                            <button class="btn btn-light btn-sm rounded-circle p-2" @click="deleteUnit(unit)" title="Soft Delete Satuan">
                                                <i class="bx bx-trash text-danger" style="font-size: 1.1rem;"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!units.data || units.data.length === 0">
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bx bx-purchase-tag-alt fs-1 d-block mb-2 text-secondary"></i>
                                        Belum ada data satuan. Silakan tambahkan satuan baru.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4" v-if="units.links && units.links.length > 3">
                        <span class="text-muted small">Menampilkan {{ units.from || 0 }} - {{ units.to || 0 }} dari {{ units.total || 0 }} data</span>
                        <div class="pagination-buttons d-flex gap-1">
                            <button
                                v-for="(link, i) in units.links"
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

        <!-- Modal Tambah Satuan -->
        <div class="modal fade show d-block" tabindex="-1" v-if="showAddModal" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Tambah Satuan Baru</h5>
                        <button type="button" class="btn-close" @click="closeAddModal"></button>
                    </div>
                    <form @submit.prevent="submitAdd">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Nama Satuan <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control rounded-3" 
                                    placeholder="Contoh: Box, Strip, Tablet, Botol..." 
                                    v-model="addForm.name"
                                    :class="{ 'is-invalid': addForm.errors.name }"
                                    required
                                >
                                <div class="invalid-feedback" v-if="addForm.errors.name">{{ addForm.errors.name }}</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="addActive" v-model="addForm.is_active">
                                <label class="form-check-label small fw-semibold" for="addActive">Satuan Aktif</label>
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-light rounded-3 px-3" @click="closeAddModal">Batal</button>
                            <button type="submit" class="btn btn-primary rounded-3 px-4" :disabled="addForm.processing">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Satuan -->
        <div class="modal fade show d-block" tabindex="-1" v-if="showEditModal" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Edit Satuan</h5>
                        <button type="button" class="btn-close" @click="closeEditModal"></button>
                    </div>
                    <form @submit.prevent="submitEdit">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Nama Satuan <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control rounded-3" 
                                    placeholder="Nama satuan" 
                                    v-model="editForm.name"
                                    :class="{ 'is-invalid': editForm.errors.name }"
                                    required
                                >
                                <div class="invalid-feedback" v-if="editForm.errors.name">{{ editForm.errors.name }}</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editActive" v-model="editForm.is_active">
                                <label class="form-check-label small fw-semibold" for="editActive">Satuan Aktif</label>
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-light rounded-3 px-3" @click="closeEditModal">Batal</button>
                            <button type="submit" class="btn btn-primary rounded-3 px-4" :disabled="editForm.processing">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
