<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    customers: Object,
    filters: Object,
});

const createForm = useForm({
    code: 'CUST-' + Math.floor(100 + Math.random() * 900),
    name: '',
    gender: 'L',
    phone: '',
    email: '',
    address: '',
    medical_notes: '',
    allergies: '',
    membership_level: 'regular',
});

const submitCustomer = () => {
    createForm.post(route('customers.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.code = 'CUST-' + Math.floor(100 + Math.random() * 900);
            const modalEl = document.getElementById('tambahCustModal');
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
                    <h2 class="fw-bold text-dark mb-0"><i class="bx bx-group text-primary me-2"></i>Master Pelanggan & Customer Membership</h2>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tambahCustModal">
                        <i class="bx bx-plus"></i> Daftar Pelanggan Baru
                    </button>
                </div>

                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>Kode</th>
                                <th>Nama Pelanggan</th>
                                <th>No. HP</th>
                                <th>Level Membership</th>
                                <th>Total Poin</th>
                                <th>Alergi Obat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in customers.data" :key="c.id">
                                <td class="fw-bold text-primary">{{ c.code }}</td>
                                <td class="fw-bold">{{ c.name }}</td>
                                <td>{{ c.phone }}</td>
                                <td>
                                    <span class="badge" :class="{
                                        'bg-secondary': c.membership_level === 'regular',
                                        'bg-info text-dark': c.membership_level === 'silver',
                                        'bg-warning text-dark': c.membership_level === 'gold',
                                        'bg-dark text-white': c.membership_level === 'platinum'
                                    }">
                                        {{ c.membership_level.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="fw-bold text-success">{{ c.points }} Pts</td>
                                <td><span class="badge bg-danger">{{ c.allergies || 'Tidak ada' }}</span></td>
                            </tr>
                            <tr v-if="!customers.data || customers.data.length === 0">
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada pelanggan terdaftar.</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination Links -->
                    <Pagination :links="customers.links" />
                </div>
            </div>
        </section>

        <!-- Modal Tambah Cust -->
        <div class="modal fade" id="tambahCustModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Daftar Pelanggan / Member Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitCustomer">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nama Pelanggan</label>
                                <input type="text" class="form-control" v-model="createForm.name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">No. Telepon / HP</label>
                                <input type="text" class="form-control" v-model="createForm.phone" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Level Member</label>
                                <select class="form-select" v-model="createForm.membership_level">
                                    <option value="regular">Regular</option>
                                    <option value="silver">Silver</option>
                                    <option value="gold">Gold</option>
                                    <option value="platinum">Platinum</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Alergi Obat (jika ada)</label>
                                <input type="text" class="form-control" v-model="createForm.allergies" placeholder="Contoh: Alergi Penisilin">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="createForm.processing">Simpan Member</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
