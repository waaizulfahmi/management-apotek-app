<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    suppliers: Object,
    filters: Object,
});

const createForm = useForm({
    code: 'SUP-' + Math.floor(100 + Math.random() * 900),
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    npwp: '',
    bank_name: 'BCA',
    bank_account: '',
    payment_terms_days: 30,
});

const submitSupplier = () => {
    createForm.post(route('suppliers.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.code = 'SUP-' + Math.floor(100 + Math.random() * 900);
            const modalEl = document.getElementById('tambahSupplierModal');
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
                    <h2 class="fw-bold text-dark mb-0"><i class="bx bx-truck text-primary me-2"></i>Master Data Supplier Obat</h2>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tambahSupplierModal">
                        <i class="bx bx-plus"></i> Tambah Supplier Baru
                    </button>
                </div>

                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>Kode</th>
                                <th>Nama Supplier</th>
                                <th>Contact Person</th>
                                <th>Telepon</th>
                                <th>Email</th>
                                <th>Bank & Rekening</th>
                                <th>Termin Pembayaran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in suppliers.data" :key="s.id">
                                <td class="fw-bold text-primary">{{ s.code }}</td>
                                <td class="fw-bold">{{ s.name }}</td>
                                <td>{{ s.contact_person || '-' }}</td>
                                <td>{{ s.phone || '-' }}</td>
                                <td>{{ s.email || '-' }}</td>
                                <td><small class="text-muted">{{ s.bank_name }} - {{ s.bank_account }}</small></td>
                                <td><span class="badge bg-info text-dark">{{ s.payment_terms_days }} Hari</span></td>
                            </tr>
                            <tr v-if="!suppliers.data || suppliers.data.length === 0">
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada supplier terdaftar.</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination Links -->
                    <Pagination :links="suppliers.links" />
                </div>
            </div>
        </section>

        <!-- Modal Tambah Supplier -->
        <div class="modal fade" id="tambahSupplierModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Tambah Supplier Obat</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitSupplier">
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Kode Supplier</label>
                                    <input type="text" class="form-control" v-model="createForm.code" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Nama Perusahaan / Supplier</label>
                                    <input type="text" class="form-control" v-model="createForm.name" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Contact Person (PJ)</label>
                                    <input type="text" class="form-control" v-model="createForm.contact_person">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">No. Telepon</label>
                                    <input type="text" class="form-control" v-model="createForm.phone">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Alamat Lengkap</label>
                                <textarea class="form-control" v-model="createForm.address"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="createForm.processing">Simpan Supplier</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
