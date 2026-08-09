<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import RupiahInput from '@/Components/RupiahInput.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    payables: Object,
    cashAccounts: Array,
});

const selectedPayable = ref(null);

const payForm = useForm({
    cash_bank_account_id: props.cashAccounts[0]?.id || 1,
    amount: 0,
    payment_date: new Date().toISOString().slice(0, 10),
    notes: '',
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const openPayModal = (p) => {
    selectedPayable.value = p;
    payForm.amount = p.remaining_amount;
    const modalEl = document.getElementById('payPayableModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
};

const submitPayment = () => {
    if (!selectedPayable.value) return;
    payForm.post(route('finance.payables.pay', selectedPayable.value.id), {
        onSuccess: () => {
            const modalEl = document.getElementById('payPayableModal');
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
                <h2 class="fw-bold text-dark mb-4"><i class="bx bx-file text-danger me-2"></i>Manajemen Hutang Supplier (Accounts Payable)</h2>

                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>No. Hutang / Inv</th>
                                <th>Supplier</th>
                                <th>Jatuh Tempo</th>
                                <th class="text-end">Total Hutang</th>
                                <th class="text-end">Telah Dibayar</th>
                                <th class="text-end">Sisa Hutang</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in payables.data" :key="p.id">
                                <td class="fw-bold text-primary">{{ p.payable_number }}<br><small class="text-muted">{{ p.invoice_number }}</small></td>
                                <td class="fw-bold">{{ p.supplier_name }}</td>
                                <td><span class="badge bg-light text-danger border">{{ p.due_date }}</span></td>
                                <td class="text-end">{{ formatCurrency(p.total_amount) }}</td>
                                <td class="text-end text-success">{{ formatCurrency(p.paid_amount) }}</td>
                                <td class="text-end fw-bold text-danger">{{ formatCurrency(p.remaining_amount) }}</td>
                                <td>
                                    <span class="badge" :class="{
                                        'bg-danger': p.status === 'unpaid',
                                        'bg-warning text-dark': p.status === 'partial',
                                        'bg-success': p.status === 'paid'
                                    }">
                                        {{ p.status.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button v-if="p.status !== 'paid'" class="btn btn-sm btn-success fw-bold" @click="openPayModal(p)">
                                        <i class="bx bx-money"></i> Bayar Hutang
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!payables.data || payables.data.length === 0">
                                <td colspan="8" class="text-center py-4 text-muted">Belum ada kewajiban hutang supplier.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Modal Bayar Hutang -->
        <div class="modal fade" id="payPayableModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content" v-if="selectedPayable">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Pembayaran Hutang Supplier</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitPayment">
                        <div class="modal-body">
                            <div class="alert alert-info py-2 small">
                                Supplier: <strong>{{ selectedPayable.supplier_name }}</strong><br>
                                Rekening Tujuan: {{ selectedPayable.bank_name }} - {{ selectedPayable.bank_account }}<br>
                                Sisa Tagihan: <strong class="text-danger">{{ formatCurrency(selectedPayable.remaining_amount) }}</strong>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Sumber Kas / Bank Pembayar</label>
                                <select class="form-select" v-model="payForm.cash_bank_account_id" required>
                                    <option v-for="acc in cashAccounts" :key="acc.id" :value="acc.id">
                                        {{ acc.name }} (Saldo: {{ formatCurrency(acc.current_balance) }})
                                    </option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nominal Pembayaran (Rp)</label>
                                <RupiahInput v-model="payForm.amount" className="form-control-lg fw-bold text-success" :max="selectedPayable.remaining_amount" required />
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tanggal Bayar</label>
                                <input type="date" class="form-control" v-model="payForm.payment_date" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success fw-bold" :disabled="payForm.processing">Proses Pembayaran</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
