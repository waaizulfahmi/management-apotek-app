<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { showConfirm, showSuccess } from '@/Utils/swal';

const props = defineProps({
    shifts: Array,
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const selectedShiftId = ref(null);

const form = useForm({
    name: '',
    start_time: '07:00',
    end_time: '15:00',
    grace_minutes: 15,
    description: '',
    is_active: true,
});

const editForm = useForm({
    name: '',
    start_time: '',
    end_time: '',
    grace_minutes: 15,
    description: '',
    is_active: true,
});

const openCreateModal = () => {
    form.reset();
    showCreateModal.value = true;
};

const submitCreate = () => {
    form.post(route('master-shifts.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
            showSuccess('Berhasil!', 'Master Shift baru berhasil ditambahkan.');
        }
    });
};

const openEditModal = (shift) => {
    selectedShiftId.value = shift.id;
    editForm.name = shift.name;
    editForm.start_time = shift.start_time.substring(0, 5);
    editForm.end_time = shift.end_time.substring(0, 5);
    editForm.grace_minutes = shift.grace_minutes;
    editForm.description = shift.description || '';
    editForm.is_active = Boolean(shift.is_active);
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.put(route('master-shifts.update', selectedShiftId.value), {
        onSuccess: () => {
            showEditModal.value = false;
            showSuccess('Berhasil!', 'Master Shift berhasil diperbarui.');
        }
    });
};

const toggleActive = (shift) => {
    const actionStr = shift.is_active ? 'Nonaktifkan' : 'Aktifkan';
    showConfirm(
        `${actionStr} ${shift.name}?`,
        `Shift ${shift.name} akan ${actionStr.toLowerCase()} dalam pilihan buka shift kasir.`,
        () => {
            router.post(route('master-shifts.toggle', shift.id));
        }
    );
};

const deleteShift = (shift) => {
    showConfirm(
        `Hapus ${shift.name}?`,
        'Data master shift yang dihapus tidak akan muncul lagi di sistem.',
        () => {
            router.delete(route('master-shifts.destroy', shift.id));
        }
    );
};

const formatTime = (timeStr) => {
    if (!timeStr) return '-';
    return timeStr.substring(0, 5) + ' WIB';
};
</script>

<template>
    <Head title="Mastering Shift Kasir" />
    <LegacyLayout>
        <div class="content p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold mb-1 text-dark">
                        <i class="bx bx-time text-primary me-2"></i>Mastering Jadwal Shift Kasir
                    </h4>
                    <p class="text-muted small mb-0">Kelola master data shift operasional, jam masuk, jam keluar, dan toleransi keterlambatan</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary shadow-sm fw-bold" @click="openCreateModal">
                        <i class="bx bx-plus-circle me-1"></i> Tambah Master Shift
                    </button>
                </div>
            </div>

            <!-- Master Shift Cards / Table -->
            <div class="row g-4 mb-4">
                <div v-for="s in shifts" :key="s.id" class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-3 h-100 position-relative overflow-hidden" :class="{ 'opacity-75 bg-light': !s.is_active }">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge rounded-pill px-3 py-1.5" :class="s.is_active ? 'bg-primary' : 'bg-secondary'">
                                    {{ s.is_active ? 'AKTIF' : 'NONAKTIF' }}
                                </span>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border-0" data-bs-toggle="dropdown">
                                        <i class="bx bx-dots-vertical-rounded fs-5"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <li>
                                            <button class="dropdown-item small" @click="openEditModal(s)">
                                                <i class="bx bx-edit me-2 text-primary"></i> Edit Shift
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item small" @click="toggleActive(s)">
                                                <i class="bx" :class="s.is_active ? 'bx-x-circle text-warning' : 'bx-check-circle text-success'"></i>
                                                {{ s.is_active ? ' Nonaktifkan' : ' Aktifkan' }}
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item small text-danger" @click="deleteShift(s)">
                                                <i class="bx bx-trash me-2"></i> Hapus Shift
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-1">{{ s.name }}</h5>
                            <p class="text-muted small mb-3">{{ s.description || 'Tidak ada keterangan' }}</p>

                            <div class="bg-light p-3 rounded-3 border mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="small text-muted"><i class="bx bx-time-five me-1 text-primary"></i>Jam Operasional:</span>
                                </div>
                                <div class="fw-extrabold text-primary fs-5 font-monospace">
                                    {{ formatTime(s.start_time) }} – {{ formatTime(s.end_time) }}
                                </div>
                            </div>

                            <div class="small text-muted d-flex justify-content-between align-items-center">
                                <span>Toleransi: <strong>{{ s.grace_minutes }} menit</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table View Summary -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold text-dark mb-0"><i class="bx bx-list-ul me-2 text-primary"></i>Daftar Lengkap Master Shift</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle small mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Nama Shift</th>
                                <th>Jam Masuk</th>
                                <th>Jam Selesai</th>
                                <th>Toleransi</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in shifts" :key="s.id">
                                <td class="ps-4 font-monospace fw-bold text-primary">{{ s.name }}</td>
                                <td><span class="badge bg-light text-dark border font-monospace fs-6">{{ formatTime(s.start_time) }}</span></td>
                                <td><span class="badge bg-light text-dark border font-monospace fs-6">{{ formatTime(s.end_time) }}</span></td>
                                <td>{{ s.grace_minutes }} Menit</td>
                                <td>
                                    <span class="badge rounded-pill" :class="s.is_active ? 'bg-success' : 'bg-secondary'">
                                        {{ s.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="text-muted">{{ s.description || '-' }}</td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary me-1" @click="openEditModal(s)" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" @click="deleteShift(s)" title="Hapus">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL TAMBAH MASTER SHIFT -->
        <div v-if="showCreateModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="fw-bold"><i class="bx bx-plus-circle me-2 text-primary"></i>Tambah Master Shift Baru</h5>
                        <button type="button" class="btn-close" @click="showCreateModal = false"></button>
                    </div>
                    <form @submit.prevent="submitCreate">
                        <div class="modal-body py-3">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Nama Shift</label>
                                <input type="text" class="form-control" v-model="form.name" required placeholder="Contoh: Shift Pagi, Shift Siang, Shift Malam">
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-dark">Jam Masuk (Mulai)</label>
                                    <input type="time" class="form-control font-monospace" v-model="form.start_time" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-dark">Jam Keluar (Selesai)</label>
                                    <input type="time" class="form-control font-monospace" v-model="form.end_time" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Toleransi Keterlambatan (Menit)</label>
                                <input type="number" class="form-control" v-model.number="form.grace_minutes" required min="0" max="120">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Keterangan / Deskripsi</label>
                                <textarea class="form-control" rows="2" v-model="form.description" placeholder="Deskripsi atau catatan khusus shift..."></textarea>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="createIsActive" v-model="form.is_active">
                                <label class="form-check-label small fw-semibold cursor-pointer" for="createIsActive">Aktifkan Shift Ini Langsung</label>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" @click="showCreateModal = false">Batal</button>
                            <button type="submit" class="btn btn-primary fw-bold" :disabled="form.processing">Simpan Shift</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL EDIT MASTER SHIFT -->
        <div v-if="showEditModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="fw-bold"><i class="bx bx-edit me-2 text-primary"></i>Edit Master Shift</h5>
                        <button type="button" class="btn-close" @click="showEditModal = false"></button>
                    </div>
                    <form @submit.prevent="submitEdit">
                        <div class="modal-body py-3">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Nama Shift</label>
                                <input type="text" class="form-control" v-model="editForm.name" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-dark">Jam Masuk (Mulai)</label>
                                    <input type="time" class="form-control font-monospace" v-model="editForm.start_time" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-dark">Jam Keluar (Selesai)</label>
                                    <input type="time" class="form-control font-monospace" v-model="editForm.end_time" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Toleransi Keterlambatan (Menit)</label>
                                <input type="number" class="form-control" v-model.number="editForm.grace_minutes" required min="0" max="120">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Keterangan / Deskripsi</label>
                                <textarea class="form-control" rows="2" v-model="editForm.description"></textarea>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="editIsActive" v-model="editForm.is_active">
                                <label class="form-check-label small fw-semibold cursor-pointer" for="editIsActive">Status Shift Aktif</label>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" @click="showEditModal = false">Batal</button>
                            <button type="submit" class="btn btn-primary fw-bold" :disabled="editForm.processing">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
