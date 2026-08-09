<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';

import { showConfirm } from '@/Utils/swal';

const props = defineProps({
    kasirs: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');

const handleSearch = () => {
    router.get(route('admin.kasir.index'), { search: search.value }, { preserveState: true, replace: true });
};

// Form Tambah
const createForm = useForm({
    nama: '',
    username: '',
    email: '',
    password: '',
    no_hp: '',
    profil: null,
});

const submitCreate = () => {
    createForm.post(route('admin.kasir.store'), {
        onSuccess: () => {
            createForm.reset();
            const modalEl = document.getElementById('tambahKasirModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        },
    });
};

// Form Edit
const editKasirData = ref(null);
const editForm = useForm({
    _method: 'PUT',
    nama: '',
    username: '',
    email: '',
    password: '',
    no_hp: '',
    profil: null,
});

const openEditModal = (kasir) => {
    editKasirData.value = kasir;
    editForm.nama = kasir.nama;
    editForm.username = kasir.username;
    editForm.email = kasir.email;
    editForm.password = '';
    editForm.no_hp = kasir.no_hp;
    editForm.profil = null;

    const modalEl = document.getElementById('editKasirModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
};

const submitEdit = () => {
    if (!editKasirData.value) return;
    editForm.post(route('admin.kasir.update', editKasirData.value.id), {
        onSuccess: () => {
            const modalEl = document.getElementById('editKasirModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        },
    });
};

const deleteKasir = (id) => {
    showConfirm("Hapus Kasir", "Apakah Anda yakin ingin menghapus data Kasir ini?", () => {
        router.delete(route('admin.kasir.destroy', id));
    });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark mb-0">Data Kasir</h2>

                    <div class="d-flex align-items-center gap-3">
                        <div class="input-group" style="max-width: 220px;">
                            <span class="input-group-text bg-primary text-white"><i class="bx bx-search"></i></span>
                            <input type="text" class="form-control" v-model="search" @keyup.enter="handleSearch" placeholder="Cari Kasir...">
                        </div>

                        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tambahKasirModal">
                            <i class="bx bx-plus"></i> Tambah
                        </button>
                    </div>
                </div>

                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th class="text-center">ID</th>
                                <th>Profil</th>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>No. Telp</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="kasir in kasirs.data" :key="kasir.id">
                                <td class="text-center">{{ kasir.id }}</td>
                                <td>
                                    <img :src="kasir.profil ? `/Assets/Kasir/${kasir.profil}` : '/Assets/img/default-medicine.png'" @error="(e) => e.target.src = '/Assets/img/default-medicine.png'" alt="Profil Image" style="width: 60px; height: 60px; object-fit: cover; border-radius: 50%;">
                                </td>
                                <td>{{ kasir.nama }}</td>
                                <td>{{ kasir.username }}</td>
                                <td>{{ kasir.email }}</td>
                                <td>{{ kasir.no_hp }}</td>
                                <td class="text-center">
                                    <button class="btn btn-warning btn-sm rounded-2 me-1 text-white" @click="openEditModal(kasir)">
                                        <i class="bx bx-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm rounded-2" @click="deleteKasir(kasir.id)">
                                        <i class="bx bx-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!kasirs.data || kasirs.data.length === 0">
                                <td colspan="7" class="text-center py-4 text-muted">Tidak ada data kasir.</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination Links -->
                    <div class="d-flex justify-content-center mt-4" v-if="kasirs.links">
                        <nav>
                            <ul class="pagination">
                                <li v-for="(link, index) in kasirs.links" :key="index" class="page-item" :class="{ 'active': link.active, 'disabled': !link.url }">
                                    <Link class="page-link" :href="link.url || '#'" v-html="link.label"></Link>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </section>

        <!-- Modal Tambah Kasir -->
        <div class="modal fade" id="tambahKasirModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Tambah Kasir Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitCreate">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nama Kasir</label>
                                <input type="text" class="form-control" v-model="createForm.nama" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" v-model="createForm.username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" v-model="createForm.email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" v-model="createForm.password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">No. Telepon / HP</label>
                                <input type="text" class="form-control" v-model="createForm.no_hp" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Foto Profil</label>
                                <input type="file" class="form-control" @input="createForm.profil = $event.target.files[0]">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                            <button type="submit" class="btn btn-primary" :disabled="createForm.processing">Simpan Kasir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Kasir -->
        <div class="modal fade" id="editKasirModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title">Edit Data Kasir</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitEdit">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nama Kasir</label>
                                <input type="text" class="form-control" v-model="editForm.nama" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" v-model="editForm.username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" v-model="editForm.email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password (Biarkan kosong jika tidak diubah)</label>
                                <input type="password" class="form-control" v-model="editForm.password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">No. Telepon / HP</label>
                                <input type="text" class="form-control" v-model="editForm.no_hp" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Foto Profil (Biarkan kosong jika tidak diubah)</label>
                                <input type="file" class="form-control" @input="editForm.profil = $event.target.files[0]">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                            <button type="submit" class="btn btn-warning text-white" :disabled="editForm.processing">Update Kasir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
