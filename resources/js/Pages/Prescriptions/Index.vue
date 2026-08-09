<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    prescriptions: Object,
    customers: Array,
    doctors: Array,
    medicines: Array,
});

const createForm = useForm({
    customer_id: '',
    doctor_id: '',
    prescription_date: new Date().toISOString().slice(0, 10),
    notes: '',
    items: [
        { medicine_id: '', dosage: '500mg', frequency: '3x1 Sehari', quantity: 1, instructions: 'Diminum sesudah makan' }
    ]
});

const addItem = () => {
    createForm.items.push({ medicine_id: '', dosage: '500mg', frequency: '3x1 Sehari', quantity: 1, instructions: 'Diminum sesudah makan' });
};

const submitPrescription = () => {
    createForm.post(route('prescriptions.store'), {
        onSuccess: () => {
            createForm.reset();
            const modalEl = document.getElementById('tambahRxModal');
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
                    <h2 class="fw-bold text-dark mb-0"><i class="bx bx-notepad text-primary me-2"></i>Resep Dokter & Validasi Apoteker</h2>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tambahRxModal">
                        <i class="bx bx-plus"></i> Input Resep Baru
                    </button>
                </div>

                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>No. Resep</th>
                                <th>Pasien / Pelanggan</th>
                                <th>Dokter Penanggung Jawab</th>
                                <th>Apoteker Verifikator</th>
                                <th>Tanggal Resep</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="rx in prescriptions.data" :key="rx.id">
                                <td class="fw-bold text-primary">{{ rx.prescription_number }}</td>
                                <td>{{ rx.patient_name || 'Umum' }}</td>
                                <td>{{ rx.doctor_name || 'Mandiri' }}</td>
                                <td>{{ rx.pharmacist_name || '-' }}</td>
                                <td>{{ rx.prescription_date }}</td>
                                <td>
                                    <span class="badge bg-success">{{ rx.status.toUpperCase() }}</span>
                                </td>
                            </tr>
                            <tr v-if="!prescriptions.data || prescriptions.data.length === 0">
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada resep terdaftar.</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination Links -->
                    <Pagination :links="prescriptions.links" />
                </div>
            </div>
        </section>

        <!-- Modal Tambah Resep -->
        <div class="modal fade" id="tambahRxModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Input & Validasi Resep Dokter</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitPrescription">
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Pasien / Pelanggan</label>
                                    <select class="form-select" v-model="createForm.customer_id">
                                        <option value="">-- Pilih Pasien --</option>
                                        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Dokter Penulis Resep</label>
                                    <select class="form-select" v-model="createForm.doctor_id">
                                        <option value="">-- Pilih Dokter --</option>
                                        <option v-for="d in doctors" :key="d.id" :value="d.id">{{ d.name }} ({{ d.specialty }})</option>
                                    </select>
                                </div>
                            </div>

                            <h6 class="fw-bold border-bottom pb-2 mt-4 mb-3">Obat Resep & Dosis Aturan Pakai</h6>

                            <div v-for="(item, idx) in createForm.items" :key="idx" class="row g-2 align-items-center mb-3 bg-light p-2 rounded">
                                <div class="col-md-4">
                                    <label class="small text-muted">Obat</label>
                                    <select class="form-select form-select-sm" v-model="item.medicine_id" required>
                                        <option value="">-- Pilih Obat --</option>
                                        <option v-for="m in medicines" :key="m.kode" :value="m.kode">{{ m.nama }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">Frekuensi Dosis</label>
                                    <input type="text" class="form-control form-control-sm" v-model="item.frequency" placeholder="Contoh: 3x1 Sehari">
                                </div>
                                <div class="col-md-2">
                                    <label class="small text-muted">Jumlah</label>
                                    <input type="number" class="form-control form-control-sm" v-model.number="item.quantity" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">Instruksi / Aturan Pakai</label>
                                    <input type="text" class="form-control form-control-sm" v-model="item.instructions" placeholder="Sesudah makan">
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" @click="addItem">+ Tambah Obat Resep</button>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="createForm.processing">Verifikasi & Simpan Resep</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
