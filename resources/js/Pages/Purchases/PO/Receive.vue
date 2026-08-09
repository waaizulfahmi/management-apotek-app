<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    po: Object,
    items: Array,
});

const receiveForm = useForm({
    supplier_invoice_number: 'INV-' + Math.floor(100000 + Math.random() * 900000),
    received_date: new Date().toISOString().slice(0, 10),
    notes: '',
    items: [],
});

onMounted(() => {
    receiveForm.items = props.items.map(item => ({
        item_id: item.id,
        medicine_name: item.medicine_name,
        batch_number: 'BCH-' + Math.floor(1000 + Math.random() * 9000),
        expired_date: new Date(Date.now() + 365*24*60*60*1000).toISOString().slice(0, 10),
        received_quantity: item.outstanding_quantity > 0 ? item.outstanding_quantity : item.order_quantity,
        unit_price: item.unit_price,
    }));
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const submitReceive = () => {
    receiveForm.post(route('purchases.po.receive.store', props.po.id));
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <span class="badge bg-success mb-1">GOODS RECEIPT & INVENTORY POSTING</span>
                        <h3 class="fw-bold text-dark mb-0">Penerimaan Barang Fisik PO {{ po.po_number }}</h3>
                        <p class="text-muted small mb-0">Supplier: <strong>{{ po.supplier_name }}</strong> | Gudang: {{ po.warehouse_name }}</p>
                    </div>

                    <Link :href="route('purchases.po.index')" class="btn btn-outline-secondary">
                        ← Kembali ke Daftar PO
                    </Link>
                </div>

                <form @submit.prevent="submitReceive">
                    <div class="card border-0 shadow-sm p-4 bg-white rounded-3 mb-4">
                        <h6 class="fw-bold text-primary mb-3">1. Informasi Faktur / Invoice PBF</h6>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label font-weight-bold">Nomor Faktur / Invoice PBF</label>
                                <input type="text" class="form-control" v-model="receiveForm.supplier_invoice_number" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-weight-bold">Tanggal Diterima Fisik</label>
                                <input type="date" class="form-control" v-model="receiveForm.received_date" required>
                            </div>
                        </div>

                        <h6 class="fw-bold text-primary mb-3">2. Input Nomor Batch & Tanggal Expired Fisik Obat</h6>

                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle">
                                <thead class="bg-primary text-white">
                                    <tr>
                                        <th>Nama Obat</th>
                                        <th style="width: 180px;">Nomor Batch Fisik</th>
                                        <th style="width: 170px;">Tanggal Expired</th>
                                        <th class="text-center" style="width: 130px;">Qty Diterima</th>
                                        <th class="text-end">Harga Beli</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in receiveForm.items" :key="item.item_id">
                                        <td class="fw-bold text-dark">{{ item.medicine_name }}</td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm fw-bold font-monospace text-primary" v-model="item.batch_number" required>
                                        </td>
                                        <td>
                                            <input type="date" class="form-control form-control-sm" v-model="item.expired_date" required>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm text-center fw-bold text-success" v-model.number="item.received_quantity" min="1" required>
                                        </td>
                                        <td class="text-end fw-bold">{{ formatCurrency(item.unit_price) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded-3 border">
                            <span class="text-muted small">Mencatat Batch FEFO, menambah stok fisik di gudang, dan menerbitkan kewajiban Hutang Supplier secara otomatis.</span>
                            <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm px-4" :disabled="receiveForm.processing">
                                <i class="bx bx-check-double me-1"></i> SIMPAN PENERIMAN & ADJUST STOK FEFO
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </LegacyLayout>
</template>
