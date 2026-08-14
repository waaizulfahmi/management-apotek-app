<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    type: String, // 'sale' or 'purchase'
    transaction: Object, // The original sale or purchase model
});

const isSale = computed(() => props.type === 'sale');

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

// We initialize the form items with 0 quantity to return by default
const initItems = () => {
    if (!props.transaction || !props.transaction.items) return [];
    
    return props.transaction.items.map(item => {
        const maxQty = isSale.value ? item.quantity : item.quantity_ordered;
        
        return {
            medicine_id: item.medicine_id,
            medicine_name: item.medicine?.nama || item.medicine_id,
            batch_id: item.batch_id,
            max_qty: maxQty,
            quantity: 0,
            unit_price: isSale.value ? item.unit_price : item.unit_price,
            reason: '',
            condition: isSale.value ? 'Baik' : null,
            selected: false
        };
    });
};

const form = useForm({
    type: props.type,
    reference_id: props.transaction?.id,
    items: [],
    total_amount: 0,
    refund_method: 'Cash',
    refund_amount: 0,
    notes: '',
});

const returnItems = ref(initItems());

const calculateTotal = () => {
    let total = 0;
    returnItems.value.forEach(item => {
        if (item.selected && item.quantity > 0) {
            total += (item.quantity * item.unit_price);
        }
    });
    form.total_amount = total;
    form.refund_amount = total; // Default refund amount to total return value
};

const submitReturn = () => {
    form.items = returnItems.value.filter(item => item.selected && item.quantity > 0);
    
    if (form.items.length === 0) {
        alert("Pilih minimal 1 barang untuk diretur dengan kuantitas > 0.");
        return;
    }

    const invalidReason = form.items.find(i => !i.reason);
    if (invalidReason) {
        alert("Pilih alasan retur untuk semua barang yang dipilih.");
        return;
    }

    if (form.refund_amount > form.total_amount) {
        alert("Jumlah refund tidak boleh melebihi total retur.");
        return;
    }

    form.post(route('returns.store'));
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <Link :href="isSale ? route('reports.index') : route('purchases.index')" class="btn btn-outline-secondary btn-sm rounded-circle p-2">
                            <i class="bx bx-arrow-back fs-5"></i>
                        </Link>
                        <h2 class="fw-bold text-dark mb-0">
                            <i class="bx bx-repost text-primary me-2"></i>Buat Retur {{ isSale ? 'Penjualan' : 'Pembelian' }}
                        </h2>
                    </div>
                </div>

                <div v-if="!transaction" class="alert alert-warning border-0 shadow-sm d-flex align-items-center">
                    <i class="bx bx-error-circle fs-4 me-2"></i>
                    <div>Transaksi tidak ditemukan atau belum dipilih.</div>
                </div>

                <div v-else>
                    <!-- Info Card -->
                    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 12px;">
                        <h5 class="fw-bold text-primary mb-3"><i class="bx bx-info-circle me-2"></i>Informasi Transaksi Asli</h5>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Nomor Referensi</span>
                                <strong class="text-dark fs-6">{{ isSale ? transaction.invoice_number : transaction.po_number }}</strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Tanggal Transaksi</span>
                                <strong class="text-dark fs-6">{{ formatDate(isSale ? transaction.sale_date : transaction.purchase_date) }}</strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted small d-block">{{ isSale ? 'Customer' : 'Supplier' }}</span>
                                <strong class="text-dark fs-6">{{ isSale ? (transaction.customer?.name || 'Customer Umum') : (transaction.supplier?.name || '-') }}</strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Total Transaksi</span>
                                <strong class="text-primary fs-6">{{ formatCurrency(transaction.grand_total) }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Items Selection Table -->
                    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 12px;">
                        <h5 class="fw-bold text-dark mb-3"><i class="bx bx-package me-2 text-primary"></i>Pilih Barang yang Diretur</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="bg-primary text-white">
                                    <tr>
                                        <th class="text-center" style="width: 60px;">Pilih</th>
                                        <th>Produk</th>
                                        <th class="text-center" style="width: 100px;">Qty Beli</th>
                                        <th style="width: 120px;">Qty Retur</th>
                                        <th style="width: 220px;">Alasan Retur</th>
                                        <th v-if="isSale" style="width: 200px;">Kondisi Barang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in returnItems" :key="index" :class="{'table-primary': item.selected}">
                                        <td class="text-center">
                                            <input type="checkbox" v-model="item.selected" @change="calculateTotal" class="form-check-input" style="width: 20px; height: 20px; cursor: pointer;">
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ item.medicine_name }}</div>
                                            <small class="text-muted">{{ formatCurrency(item.unit_price) }} / unit</small>
                                        </td>
                                        <td class="text-center"><span class="badge bg-secondary fs-6">{{ item.max_qty }}</span></td>
                                        <td>
                                            <input type="number" min="1" :max="item.max_qty" v-model.number="item.quantity" @input="calculateTotal" :disabled="!item.selected" class="form-control form-control-sm text-center fw-bold">
                                        </td>
                                        <td>
                                            <select v-model="item.reason" :disabled="!item.selected" class="form-select form-select-sm">
                                                <option value="" disabled>-- Pilih Alasan --</option>
                                                <option value="Barang rusak">Barang rusak</option>
                                                <option value="Salah barang">Salah barang</option>
                                                <option value="Salah jumlah">Salah jumlah</option>
                                                <option v-if="isSale" value="Customer tidak cocok">Customer tidak cocok</option>
                                                <option v-if="!isSale" value="Mendekati expired">Mendekati expired</option>
                                                <option v-if="!isSale" value="Recall produk">Recall produk</option>
                                                <option value="Lainnya">Lainnya</option>
                                            </select>
                                        </td>
                                        <td v-if="isSale">
                                            <select v-model="item.condition" :disabled="!item.selected" class="form-select form-select-sm">
                                                <option value="Baik">Baik (Bisa dijual)</option>
                                                <option value="Rusak">Rusak</option>
                                                <option value="Kadaluarsa">Kadaluarsa</option>
                                                <option value="Kemasan Rusak">Kemasan Rusak</option>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Refund & Summary -->
                    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 12px;">
                        <h5 class="fw-bold text-dark mb-3"><i class="bx bx-wallet me-2 text-success"></i>Detail Pengembalian Dana (Refund / Credit Note)</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Metode Refund</label>
                                <select v-model="form.refund_method" class="form-select">
                                    <option value="Cash">Cash / Tunai</option>
                                    <option value="Transfer">Transfer Bank</option>
                                    <option value="Saldo/Store Credit">Saldo / Store Credit</option>
                                    <option v-if="!isSale" value="Credit Note">Credit Note (Potong Hutang)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Jumlah Refund / Credit Note (Rp)</label>
                                <input type="number" v-model.number="form.refund_amount" class="form-control font-monospace fw-bold" :max="form.total_amount">
                                <small class="text-muted">Maksimal: {{ formatCurrency(form.total_amount) }}</small>
                            </div>
                            
                            <div class="col-md-12">
                                <label class="form-label small text-muted">Catatan Retur</label>
                                <textarea v-model="form.notes" rows="2" class="form-control" placeholder="Tambahkan catatan khusus jika diperlukan..."></textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <div>
                                <span class="text-muted small d-block">Total Value Retur</span>
                                <h3 class="fw-bold text-primary mb-0">{{ formatCurrency(form.total_amount) }}</h3>
                            </div>
                            
                            <button @click="submitReturn" :disabled="form.processing || form.total_amount === 0" class="btn btn-primary btn-lg fw-bold px-4 shadow-sm">
                                <i class="bx bx-check-circle me-1"></i> Proses Retur (Draft/Pending)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
