<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    purchases: Object,
    suppliers: Array,
    medicines: Array,
});

const createForm = useForm({
    supplier_id: '',
    purchase_date: new Date().toISOString().slice(0, 10),
    due_date: new Date(Date.now() + 30*24*60*60*1000).toISOString().slice(0, 10),
    paid_amount: 0,
    discount: 0,
    notes: '',
    items: [
        { medicine_id: '', batch_number: '', expired_date: '', quantity: 1, unit_price: 0, sell_price: 0 }
    ],
});

const addItem = () => {
    createForm.items.push({ medicine_id: '', batch_number: '', expired_date: '', quantity: 1, unit_price: 0, sell_price: 0 });
};

const removeItem = (idx) => {
    if (createForm.items.length > 1) {
        createForm.items.splice(idx, 1);
    }
};

const submitPurchase = () => {
    createForm.post(route('purchases.store'), {
        onSuccess: () => {
            createForm.reset();
            const modalEl = document.getElementById('tambahPurchaseModal');
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
                    <h2 class="fw-bold text-dark mb-0"><i class="bx bx-cart-download text-primary me-2"></i>Pembelian Obat (Purchase Order & Goods Received)</h2>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tambahPurchaseModal">
                        <i class="bx bx-plus"></i> Catat Pembelian Baru
                    </button>
                </div>

                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>No. PO</th>
                                <th>Supplier</th>
                                <th>Petugas</th>
                                <th>Tgl Pembelian</th>
                                <th>Status Bayar</th>
                                <th>Total Pembelian</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in purchases.data" :key="p.id">
                                <td class="fw-bold">{{ p.po_number }}</td>
                                <td>{{ p.supplier_name }}</td>
                                <td>{{ p.user_name }}</td>
                                <td>{{ p.purchase_date }}</td>
                                <td>
                                    <span class="badge" :class="p.payment_status === 'paid' ? 'bg-success' : 'bg-warning'">
                                        {{ p.payment_status.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="fw-bold text-primary">{{ formatCurrency(p.grand_total) }}</td>
                                <td>
                                    <Link :href="route('returns.create', { type: 'purchase', reference_id: p.id })" class="btn btn-sm btn-outline-danger">
                                        RETUR
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!purchases.data || purchases.data.length === 0">
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada riwayat pembelian.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Modal Tambah PO -->
        <div class="modal fade" id="tambahPurchaseModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Form Input Pembelian & Penerimaan Batch Obat</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitPurchase">
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Supplier Utama</label>
                                    <select class="form-select" v-model="createForm.supplier_id" required>
                                        <option value="">-- Pilih Supplier --</option>
                                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }} ({{ s.code }})</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tanggal Pembelian</label>
                                    <input type="date" class="form-control" v-model="createForm.purchase_date" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Jatuh Tempo Bayar</label>
                                    <input type="date" class="form-control" v-model="createForm.due_date" required>
                                </div>
                            </div>

                            <h6 class="fw-bold border-bottom pb-2 mt-4 mb-3">Daftar Item Obat & Input Batch FEFO</h6>

                            <div v-for="(item, idx) in createForm.items" :key="idx" class="row g-2 align-items-center mb-3 bg-light p-2 rounded">
                                <div class="col-md-3">
                                    <label class="small text-muted">Obat</label>
                                    <select class="form-select form-select-sm" v-model="item.medicine_id" required>
                                        <option value="">-- Pilih Obat --</option>
                                        <option v-for="m in medicines" :key="m.kode" :value="m.kode">{{ m.nama }} ({{ m.kode }})</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small text-muted">No. Batch</label>
                                    <input type="text" class="form-control form-control-sm" v-model="item.batch_number" placeholder="Contoh: BATCH-01" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="small text-muted">Expired Date</label>
                                    <input type="date" class="form-control form-control-sm" v-model="item.expired_date" required>
                                </div>
                                <div class="col-md-1">
                                    <label class="small text-muted">Qty</label>
                                    <input type="number" class="form-control form-control-sm" v-model.number="item.quantity" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="small text-muted">Harga Beli (Rp)</label>
                                    <input type="number" class="form-control form-control-sm" v-model.number="item.unit_price" required>
                                </div>
                                <div class="col-md-1 text-end">
                                    <label class="small text-muted d-block">&nbsp;</label>
                                    <button type="button" class="btn btn-danger btn-sm" @click="removeItem(idx)">X</button>
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" @click="addItem">+ Tambah Baris Obat</button>

                            <div class="row">
                                <div class="col-md-6 offset-md-6 bg-light p-3 rounded">
                                    <div class="mb-2">
                                        <label class="form-label small">Jumlah Sudah Dibayar (Rp)</label>
                                        <input type="number" class="form-control" v-model.number="createForm.paid_amount">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="createForm.processing">Simpan Pembelian & Update Stok</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
