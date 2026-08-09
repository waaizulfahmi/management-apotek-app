<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import RupiahInput from '@/Components/RupiahInput.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    accounts: Array,
    coas: Array,
});

const createForm = useForm({
    name: '',
    type: 'bank',
    account_number: '',
    initial_balance: 0,
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const submitAccount = () => {
    createForm.post(route('finance.accounts.store'), {
        onSuccess: () => {
            createForm.reset();
            const modalEl = document.getElementById('tambahAccModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark mb-0"><i class="bx bx-wallet text-primary me-2"></i>Manajemen Rekening Kas & Bank Apotek</h2>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tambahAccModal">
                        <i class="bx bx-plus"></i> Tambah Akun Kas/Bank
                    </button>
                </div>

                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>Kode Akun</th>
                                <th>Nama Akun Rekening</th>
                                <th>Tipe Akun</th>
                                <th>No. Rekening / ID</th>
                                <th>Saldo Awal (Rp)</th>
                                <th class="text-end">Saldo Saat Ini (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="acc in accounts" :key="acc.id">
                                <td class="fw-bold text-primary">{{ acc.account_code }}</td>
                                <td class="fw-bold">{{ acc.name }}</td>
                                <td>
                                    <span class="badge" :class="{
                                        'bg-success': acc.type === 'cash',
                                        'bg-primary': acc.type === 'bank',
                                        'bg-warning text-dark': acc.type === 'qris',
                                        'bg-info text-dark': acc.type === 'ewallet'
                                    }">
                                        {{ acc.type.toUpperCase() }}
                                    </span>
                                </td>
                                <td><code>{{ acc.account_number || '-' }}</code></td>
                                <td>{{ formatCurrency(acc.initial_balance) }}</td>
                                <td class="text-end fw-bold text-primary fs-5">{{ formatCurrency(acc.current_balance) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Modal Tambah Akun -->
        <div class="modal fade" id="tambahAccModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Tambah Akun Kas / Bank Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitAccount">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nama Rekening / Akun Kas</label>
                                <input type="text" class="form-control" v-model="createForm.name" placeholder="Contoh: Bank Mandiri Operasional" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tipe Akun</label>
                                <select class="form-select" v-model="createForm.type" required>
                                    <option value="cash">Cash (Tunai)</option>
                                    <option value="bank">Bank Transfer</option>
                                    <option value="qris">QRIS Merchant</option>
                                    <option value="ewallet">E-Wallet (Gopay/OVO/Dana)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">No. Rekening / Virtual Account</label>
                                <input type="text" class="form-control" v-model="createForm.account_number">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Saldo Awal (Rp)</label>
                                <RupiahInput v-model="createForm.initial_balance" required />
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="createForm.processing">Simpan Rekening</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
