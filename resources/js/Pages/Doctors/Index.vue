<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    doctors: Object,
    filters: Object,
});

const createForm = useForm({
    code: 'DOC-' + Math.floor(100 + Math.random() * 900),
    name: '',
    license_number: '',
    specialty: '',
    phone: '',
});

const submitDoctor = () => {
    createForm.post(route('doctors.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.code = 'DOC-' + Math.floor(100 + Math.random() * 900);
            const modalEl = document.getElementById('tambahDocModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark mb-0"><i class="bx bx-user-plus text-primary me-2"></i>Master Data Dokter Penulis Resep</h2>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tambahDocModal">
                        <i class="bx bx-plus"></i> Tambah Dokter Baru
                    </button>
                </div>

                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>Kode</th>
                                <th>Nama Dokter</th>
                                <th>No. SIP / Izin Praktik</th>
                                <th>Spesialisasi</th>
                                <th>Telepon</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="d in doctors.data" :key="d.id">
                                <td class="fw-bold text-primary">{{ d.code }}</td>
                                <td class="fw-bold">{{ d.name }}</td>
                                <td><code>{{ d.license_number }}</code></td>
                                <td><span class="badge bg-info text-dark">{{ d.specialty }}</span></td>
                                <td>{{ d.phone || '-' }}</td>
                            </tr>
                            <tr v-if="!doctors.data || doctors.data.length === 0">
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada dokter terdaftar.</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination Links -->
                    <Pagination :links="doctors.links" />
                </div>
            </div>
        </section>

        <!-- Modal Tambah Dokter -->
        <div class="modal fade" id="tambahDocModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Tambah Dokter Penulis Resep</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitDoctor">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nama Lengkap Dokter</label>
                                <input type="text" class="form-control" v-model="createForm.name" placeholder="dr. Budi, Sp.PD" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">No. SIP / STR (Surat Izin Praktik)</label>
                                <input type="text" class="form-control" v-model="createForm.license_number" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Spesialisasi</label>
                                <input type="text" class="form-control" v-model="createForm.specialty" placeholder="Spesialis Penyakit Dalam" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">No. HP / Kontak</label>
                                <input type="text" class="form-control" v-model="createForm.phone">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="createForm.processing">Simpan Dokter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
