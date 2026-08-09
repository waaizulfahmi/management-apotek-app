<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import RupiahInput from '@/Components/RupiahInput.vue';
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { showSuccess, showWarning, showError } from '@/Utils/swal';

const props = defineProps({
    suppliers: Array,
    medicines: Array,
});

const selectedSupplier = ref(props.suppliers[0] || null);

const poForm = useForm({
    supplier_id: props.suppliers[0]?.id || 1,
    warehouse_name: 'Gudang Utama',
    order_date: new Date().toISOString().slice(0, 10),
    expected_delivery_date: new Date(Date.now() + 3*24*60*60*1000).toISOString().slice(0, 10),
    payment_term_days: props.suppliers[0]?.payment_terms_days || 30,
    discount_percent: 0,
    discount_amount: 0,
    tax_percent: 11,
    shipping_cost: 0,
    notes_supplier: 'Mohon kirim barang dengan expired date minimal 12 bulan.',
    notes_internal: '',
    items: [],
});

const searchQuery = ref('');

const filteredMedicines = computed(() => {
    if (!searchQuery.value) return [];
    return props.medicines.filter(m => 
        m.nama.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
        m.kode.toLowerCase().includes(searchQuery.value.toLowerCase())
    ).slice(0, 5);
});

const onSupplierChange = () => {
    const supp = props.suppliers.find(s => s.id === poForm.supplier_id);
    if (supp) {
        selectedSupplier.value = supp;
        poForm.payment_term_days = supp.payment_terms_days || 30;
    }
};

const addItem = (med) => {
    const existing = poForm.items.find(i => i.medicine_id === med.kode);
    if (existing) {
        existing.order_quantity += 1;
    } else {
        poForm.items.push({
            medicine_id: med.kode,
            medicine_name: med.nama,
            order_quantity: 10,
            bonus_quantity: 0,
            unit_price: Math.round(med.harga * 0.7),
            discount_percent: 0,
            discount_amount: 0,
            subtotal: Math.round(med.harga * 0.7) * 10,
        });
    }
    searchQuery.value = '';
};

const removeItem = (index) => {
    poForm.items.splice(index, 1);
};

// Smart Reorder Engine Integration
const isLoadingRecommendation = ref(false);

const loadSmartRecommendations = async () => {
    isLoadingRecommendation.value = true;
    try {
        const res = await axios.get(route('purchases.po.recommendations'));
        if (res.data.success && res.data.data.length > 0) {
            res.data.data.forEach(rec => {
                const existing = poForm.items.find(i => i.medicine_id === rec.medicine_id);
                if (!existing) {
                    poForm.items.push({
                        medicine_id: rec.medicine_id,
                        medicine_name: rec.medicine_name,
                        order_quantity: rec.recommended_qty,
                        bonus_quantity: 0,
                        unit_price: rec.unit_price,
                        discount_percent: 0,
                        discount_amount: 0,
                        subtotal: rec.recommended_qty * rec.unit_price,
                    });
                }
            });
            showSuccess('Smart Reorder Berhasil!', `Berhasil menambahkan ${res.data.data.length} obat rekomendasi (stok di bawah minimum) ke dalam PO.`);
        } else {
            showWarning('Stok Aman', 'Semua stok obat saat ini masih mencukupi atau di atas batas minimum.');
        }
    } catch (err) {
        showError('Gagal', 'Gagal mengambil rekomendasi stok obat.');
    } finally {
        isLoadingRecommendation.value = false;
    }
};

const calculatedSubtotal = computed(() => {
    return poForm.items.reduce((sum, item) => {
        return sum + ((item.order_quantity * item.unit_price) - item.discount_amount);
    }, 0);
});

const calculatedTax = computed(() => {
    const taxable = calculatedSubtotal.value - poForm.discount_amount;
    return (taxable * poForm.tax_percent) / 100;
});

const calculatedGrandTotal = computed(() => {
    return calculatedSubtotal.value - poForm.discount_amount + calculatedTax.value + Number(poForm.shipping_cost);
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const submitPo = () => {
    if (poForm.items.length === 0) {
        showWarning('PO Kosong!', 'Tambahkan minimal 1 item obat ke dalam Purchase Order (PO).');
        return;
    }
    poForm.post(route('purchases.po.store'));
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-plus-circle text-primary me-2"></i>Wizard Pembuatan Purchase Order (PO)</h2>
                        <p class="text-muted small mb-0">Pemesanan Obat Resmi ke PBF / Supplier Terdaftar</p>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-warning text-dark fw-bold shadow-sm" @click="loadSmartRecommendations" :disabled="isLoadingRecommendation">
                            <i class="bx bx-bulb me-1"></i> 💡 Rekomendasi PO (Smart Reorder)
                        </button>
                        <Link :href="route('purchases.po.index')" class="btn btn-outline-secondary">
                            ← Batal / Kembali
                        </Link>
                    </div>
                </div>

                <form @submit.prevent="submitPo">
                    <div class="row">
                        <!-- Left Column: Supplier & Options -->
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm p-3 mb-3 bg-white rounded-3">
                                <h6 class="fw-bold text-primary mb-3">1. Informasi PBF & Supplier</h6>

                                <div class="mb-3">
                                    <label class="form-label font-weight-bold">Pilih PBF / Supplier</label>
                                    <select class="form-select" v-model="poForm.supplier_id" @change="onSupplierChange" required>
                                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }} ({{ s.code }})</option>
                                    </select>
                                </div>

                                <template v-if="selectedSupplier">
                                    <div class="bg-light p-2 rounded small mb-3 border">
                                        <div><strong>Alamat:</strong> {{ selectedSupplier.address || '-' }}</div>
                                        <div><strong>PJ / Kontak:</strong> {{ selectedSupplier.contact_person }} ({{ selectedSupplier.phone }})</div>
                                        <div><strong>Termin Bayar:</strong> {{ selectedSupplier.payment_terms_days }} Hari</div>
                                    </div>
                                </template>

                                <div class="mb-3">
                                    <label class="form-label small">Gudang Tujuan</label>
                                    <select class="form-select form-select-sm" v-model="poForm.warehouse_name" required>
                                        <option value="Gudang Utama">Gudang Utama</option>
                                        <option value="Gudang Belakang">Gudang Belakang</option>
                                    </select>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small">Tanggal PO</label>
                                        <input type="date" class="form-control form-control-sm" v-model="poForm.order_date" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small">Estimasi Tiba</label>
                                        <input type="date" class="form-control form-control-sm" v-model="poForm.expected_delivery_date">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small">Catatan Untuk PBF</label>
                                    <textarea class="form-control form-control-sm" v-model="poForm.notes_supplier" rows="2"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Item Selection & Calculation Table -->
                        <div class="col-md-8">
                            <div class="card border-0 shadow-sm p-3 mb-3 bg-white rounded-3">
                                <h6 class="fw-bold text-primary mb-3">2. Tambahkan Item Obat ke PO</h6>

                                <!-- Fast Search Box -->
                                <div class="position-relative mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                        <input type="text" class="form-control" v-model="searchQuery" placeholder="Ketik nama obat atau barcode untuk menambahkan...">
                                    </div>

                                    <!-- Dropdown Autocomplete Result -->
                                    <div v-if="filteredMedicines.length > 0" class="list-group position-absolute w-100 shadow-lg z-3 mt-1">
                                        <button v-for="m in filteredMedicines" :key="m.kode" type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" @click="addItem(m)">
                                            <div>
                                                <strong>{{ m.nama }}</strong> <small class="text-muted">({{ m.kode }})</small>
                                            </div>
                                            <span class="badge bg-primary">Stok: {{ m.stok }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- PO Items Table -->
                                <div class="table-responsive mb-3">
                                    <table class="table table-hover table-sm align-middle small">
                                        <thead class="bg-primary text-white">
                                            <tr>
                                                <th>Obat</th>
                                                <th style="width: 100px;">Qty Order</th>
                                                <th style="width: 80px;">Bonus</th>
                                                <th style="width: 130px;">Harga Beli (Rp)</th>
                                                <th class="text-end">Subtotal</th>
                                                <th class="text-center">Hapus</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, idx) in poForm.items" :key="item.medicine_id">
                                                <td class="fw-bold">{{ item.medicine_name }}</td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm text-center fw-bold" v-model.number="item.order_quantity" min="1">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm text-center" v-model.number="item.bonus_quantity" min="0">
                                                </td>
                                                <td>
                                                    <RupiahInput v-model="item.unit_price" className="form-control-sm text-end font-monospace fw-bold" />
                                                </td>
                                                <td class="text-end fw-bold text-primary">
                                                    {{ formatCurrency((item.order_quantity * item.unit_price) - item.discount_amount) }}
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-outline-danger p-1" @click="removeItem(idx)">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr v-if="poForm.items.length === 0">
                                                <td colspan="6" class="text-center py-4 text-muted">Belum ada obat yang ditambahkan. Gunakan kolom pencarian di atas atau tombol 💡 Rekomendasi PO.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Summary & Grand Total Calculation -->
                                <div class="bg-light p-3 rounded-3 border">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between mb-1 small">
                                                <span>Subtotal Items:</span>
                                                <strong class="text-dark">{{ formatCurrency(calculatedSubtotal) }}</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-1 small">
                                                <span>PPN (11%):</span>
                                                <strong class="text-muted">{{ formatCurrency(calculatedTax) }}</strong>
                                            </div>
                                        </div>

                                        <div class="col-md-6 text-end">
                                            <span class="text-muted small d-block">GRAND TOTAL PURCHASE ORDER</span>
                                            <h3 class="fw-bold text-primary mb-2">{{ formatCurrency(calculatedGrandTotal) }}</h3>

                                            <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm w-100" :disabled="poForm.processing">
                                                <i class="bx bx-check me-1"></i> SIMPAN & PROSES PO
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </LegacyLayout>
</template>
