<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    expenses: Object,
    categories: Array,
    summary: Object,
});

const createForm = useForm({
    category_id: '',
    amount: 0,
    expense_date: new Date().toISOString().slice(0, 10),
    description: '',
});

const submitExpense = () => {
    createForm.post(route('finance.expense.store'), {
        onSuccess: () => {
            createForm.reset();
            const modalEl = document.getElementById('tambahExpenseModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    });
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark mb-0"><i class="bx bx-dollar-circle text-primary me-2"></i>Keuangan & Laporan Laba Rugi (Profit & Loss)</h2>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tambahExpenseModal">
                        <i class="bx bx-plus"></i> Catat Pengeluaran Operasional
                    </button>
                </div>

                <!-- Financial Profit & Loss Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-sm p-3 rounded-3">
                            <span class="text-muted small">Total Penjualan (Revenue)</span>
                            <h4 class="fw-bold text-primary mb-0">{{ formatCurrency(summary.revenue) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-sm p-3 rounded-3">
                            <span class="text-muted small">HPP / COGS (Harga Beli FEFO)</span>
                            <h4 class="fw-bold text-danger mb-0">{{ formatCurrency(summary.cogs) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-sm p-3 rounded-3">
                            <span class="text-muted small">Pengeluaran Operasional</span>
                            <h4 class="fw-bold text-warning mb-0">{{ formatCurrency(summary.total_expense) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white border-0 shadow-sm p-3 rounded-3">
                            <span class="text-muted small">Laba Bersih (Net Profit)</span>
                            <h4 class="fw-bold mb-0" :class="summary.net_profit >= 0 ? 'text-success' : 'text-danger'">{{ formatCurrency(summary.net_profit) }}</h4>
                        </div>
                    </div>
                </div>

                <!-- Expenses Table -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <h5 class="fw-bold mb-3">Daftar Pengeluaran Operasional</h5>
                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>Kategori</th>
                                <th>Deskripsi</th>
                                <th>Petugas Input</th>
                                <th>Tanggal</th>
                                <th class="text-end">Jumlah Pengeluaran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in expenses.data" :key="e.id">
                                <td><span class="badge bg-secondary">{{ e.category_name }}</span></td>
                                <td>{{ e.description }}</td>
                                <td>{{ e.user_name }}</td>
                                <td>{{ e.expense_date }}</td>
                                <td class="text-end fw-bold text-danger">{{ formatCurrency(e.amount) }}</td>
                            </tr>
                            <tr v-if="!expenses.data || expenses.data.length === 0">
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada pengeluaran dicatat.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Modal Tambah Expense -->
        <div class="modal fade" id="tambahExpenseModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Catat Pengeluaran Operasional</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitExpense">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Kategori Pengeluaran</label>
                                <select class="form-select" v-model="createForm.category_id" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Jumlah Pengeluaran (Rp)</label>
                                <input type="number" class="form-control" v-model.number="createForm.amount" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tanggal</label>
                                <input type="date" class="form-control" v-model="createForm.expense_date" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Deskripsi / Keterangan</label>
                                <textarea class="form-control" v-model="createForm.description" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="createForm.processing">Simpan Pengeluaran</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
