<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import RupiahInput from '@/Components/RupiahInput.vue';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { showWarning, showError } from '@/Utils/swal';

const props = defineProps({
    medicines: Array,
    customers: Array,
});

const searchQuery = ref('');
const selectedCategory = ref('');
const cart = ref([]);
const selectedCustomer = ref('');
const discount = ref(0);
const taxPercent = ref(0);
const paymentMethod = ref('cash');
const paidAmount = ref(0);
const isProcessing = ref(false);
const receiptData = ref(null);

const filteredMedicines = computed(() => {
    return props.medicines.filter(m => {
        const matchesSearch = m.nama.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                              m.kode.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesCat = !selectedCategory.value || m.kategori === selectedCategory.value;
        return matchesSearch && matchesCat;
    });
});

const addToCart = (medicine) => {
    const existing = cart.value.find(item => item.kode === medicine.kode);
    if (existing) {
        if (existing.quantity + 1 > medicine.stok) {
            showWarning('Stok Tidak Cukup!', `Stok ${medicine.nama} hanya tersisa ${medicine.stok} unit.`);
            return;
        }
        existing.quantity += 1;
    } else {
        cart.value.push({
            kode: medicine.kode,
            nama: medicine.nama,
            harga: Number(medicine.harga),
            quantity: 1,
            maxStok: medicine.stok,
        });
    }
};

const updateQty = (item, delta) => {
    const newQty = item.quantity + delta;
    if (newQty <= 0) {
        cart.value = cart.value.filter(i => i.kode !== item.kode);
    } else if (newQty > item.maxStok) {
        showWarning('Stok Maksimal!', `Stok obat ini hanya tersisa ${item.maxStok} unit.`);
    } else {
        item.quantity = newQty;
    }
};

const subtotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + (item.harga * item.quantity), 0);
});

const taxAmount = computed(() => {
    return (subtotal.value - discount.value) * (taxPercent.value / 100);
});

const grandTotal = computed(() => {
    return Math.max(0, (subtotal.value - discount.value) + taxAmount.value);
});

const changeAmount = computed(() => {
    return Math.max(0, paidAmount.value - grandTotal.value);
});

const selectedCustomerDetails = computed(() => {
    if (!selectedCustomer.value) return null;
    return props.customers.find(c => c.id == selectedCustomer.value) || null;
});

const handleCheckout = async () => {
    if (cart.value.length === 0) {
        showWarning('Keranjang Kosong!', 'Tambahkan minimal 1 obat ke keranjang belanja.');
        return;
    }
    if (paidAmount.value < grandTotal.value) {
        showWarning('Pembayaran Kurang!', 'Jumlah uang pembayaran kurang dari total belanja.');
        return;
    }

    isProcessing.value = true;
    try {
        const payload = {
            items: cart.value,
            customer_id: selectedCustomer.value || null,
            discount: discount.value,
            tax: taxAmount.value,
            payment_method: paymentMethod.value,
            paid_amount: paidAmount.value,
        };

        const res = await axios.post(route('pos.checkout'), payload);
        if (res.data.success) {
            receiptData.value = {
                invoice_number: res.data.data.invoice_number,
                items: [...cart.value],
                subtotal: subtotal.value,
                discount: discount.value,
                tax: taxAmount.value,
                grand_total: res.data.data.grand_total,
                paid_amount: res.data.data.paid_amount,
                change_amount: res.data.data.change_amount,
                payment_method: paymentMethod.value,
                date: new Date().toLocaleString('id-ID'),
            };

            // Reset cart
            cart.value = [];
            paidAmount.value = 0;
            discount.value = 0;

            const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
            modal.show();
        }
    } catch (err) {
        showError('Gagal Checkout!', err.response?.data?.message || 'Gagal memproses transaksi kasir.');
    } finally {
        isProcessing.value = false;
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const printReceipt = () => {
    const printEl = document.getElementById('printableReceipt');
    if (!printEl) return;

    const printContents = printEl.innerHTML;
    const printWindow = window.open('', '_blank', 'width=400,height=600');
    
    printWindow.document.write('<!DOCTYPE html><html><head><title>Struk Pembayaran Apotek</title>');
    printWindow.document.write('<style>');
    printWindow.document.write(`
        @page { size: 80mm auto; margin: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            width: 76mm;
            margin: 0 auto;
            padding: 8px;
            font-size: 12px;
            color: #000;
            background: #fff;
        }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .small { font-size: 11px; }
        .d-flex { display: flex; }
        .justify-content-between { justify-content: space-between; }
        .border-dashed { border-top: 1px dashed #000; }
        .my-1 { margin-top: 4px; margin-bottom: 4px; }
        .mb-0 { margin-bottom: 0; }
        .mb-2 { margin-bottom: 6px; }
        .mt-2 { margin-top: 8px; }
        .d-block { display: block; }
    `);
    printWindow.document.write('</style></head><body>');
    printWindow.document.write(printContents);
    printWindow.document.write('</body></html>');
    
    printWindow.document.close();
    printWindow.focus();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 300);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="row">
                    <!-- Left Column: Product Selection & Filter -->
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm p-3 mb-3" style="border-radius: 12px;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="fw-bold mb-0 text-dark"><i class="bx bx-store me-2 text-primary"></i>Kasir / POS</h4>
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control" v-model="searchQuery" placeholder="Scan Barcode / Cari Obat...">
                                </div>
                            </div>

                            <!-- Medicine Cards Grid -->
                            <div class="row g-3" style="max-height: 520px; overflow-y: auto;">
                                <div class="col-md-4" v-for="med in filteredMedicines" :key="med.kode">
                                    <div class="card h-100 border shadow-xs text-center p-2 product-card" @click="addToCart(med)" style="cursor: pointer; border-radius: 10px; transition: transform 0.2s;">
                                        <img :src="`/Assets/Obat/${med.gambar}`" @error="(e) => e.target.src = '/Assets/img/default-medicine.png'" alt="obat" class="img-fluid mb-2 rounded shadow-xs" style="height: 90px; object-fit: cover; width: 100%;">
                                        <h6 class="fw-bold mb-1 text-truncate" style="font-size: 0.9rem;">{{ med.nama }}</h6>
                                        <p class="text-muted mb-1 small">{{ med.kode }} | Stok: <span class="badge bg-info">{{ med.stok }}</span></p>
                                        <span class="fw-bold text-primary">{{ formatCurrency(med.harga) }}</span>
                                    </div>
                                </div>
                                <div v-if="filteredMedicines.length === 0" class="col-12 text-center py-5 text-muted">
                                    Obat tidak ditemukan.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Cart & Payment Checkout -->
                    <div class="col-md-5">
                        <div class="card border-0 shadow-sm p-3" style="border-radius: 12px; background: #fff;">
                            <h5 class="fw-bold mb-3"><i class="bx bx-shopping-bag me-2 text-success"></i>Keranjang Transaksi</h5>

                            <!-- Customer Selection -->
                            <div class="mb-3">
                                <label class="form-label small text-muted">Pelanggan / Member Apotek</label>
                                <select class="form-select form-select-sm mb-2" v-model="selectedCustomer">
                                    <option value="">-- Umum / Non-Member --</option>
                                    <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.membership_level ? c.membership_level.toUpperCase() : 'REGULAR' }} - {{ c.points }} Pts)</option>
                                </select>

                                <!-- Member Loyalty Summary & Drug Allergy Badge -->
                                <div v-if="selectedCustomerDetails" class="p-3 rounded-3 border border-primary border-opacity-25 bg-primary bg-opacity-10 text-dark">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <span class="badge bg-primary text-uppercase me-1">{{ selectedCustomerDetails.membership_level || 'MEMBER' }}</span>
                                            <strong class="text-dark">{{ selectedCustomerDetails.name }}</strong>
                                        </div>
                                        <span class="badge bg-success font-monospace">{{ selectedCustomerDetails.points }} Pts</span>
                                    </div>

                                    <!-- Drug Allergy Warning Badge -->
                                    <div class="mt-2 p-2 rounded bg-danger bg-opacity-15 border border-danger border-opacity-25 text-danger small">
                                        <div class="fw-bold mb-0">
                                            <i class="bx bx-error-circle me-1 fs-6 align-middle"></i>
                                            Alergi Obat: <span class="badge bg-danger text-white ms-1">{{ selectedCustomerDetails.allergies || 'Tidak ada riwayat alergi' }}</span>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center small text-muted mt-2 pt-1 border-top border-secondary border-opacity-25">
                                        <span>Estimasi Poin Masuk: <strong class="text-success">+{{ Math.floor(grandTotal / 10000) }} Pts</strong></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Cart Items List -->
                            <div class="cart-list mb-3" style="max-height: 240px; overflow-y: auto;">
                                <div v-for="item in cart" :key="item.kode" class="d-flex justify-content-between align-items-center border-bottom py-2">
                                    <div>
                                        <h6 class="fw-bold mb-0" style="font-size: 0.85rem;">{{ item.nama }}</h6>
                                        <small class="text-muted">{{ formatCurrency(item.harga) }} x {{ item.quantity }}</small>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <button class="btn btn-sm btn-outline-danger px-2" @click="updateQty(item, -1)">-</button>
                                        <span class="fw-bold">{{ item.quantity }}</span>
                                        <button class="btn btn-sm btn-outline-primary px-2" @click="updateQty(item, 1)">+</button>
                                        <span class="fw-bold text-dark ms-2" style="font-size: 0.9rem;">{{ formatCurrency(item.harga * item.quantity) }}</span>
                                    </div>
                                </div>
                                <div v-if="cart.length === 0" class="text-center py-4 text-muted small">
                                    Belum ada obat dipilih.
                                </div>
                            </div>

                            <!-- Summary & Calculation -->
                            <div class="bg-light p-3 rounded mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Subtotal</span>
                                    <span class="fw-bold">{{ formatCurrency(subtotal) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>Diskon (Rp)</span>
                                    <div style="width: 120px;">
                                        <RupiahInput v-model="discount" className="form-control-sm text-end" placeholder="0" />
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>PPN (%)</span>
                                    <input type="number" class="form-control form-control-sm text-end" style="width: 70px;" v-model.number="taxPercent">
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between fs-5 fw-bold text-primary">
                                    <span>Grand Total</span>
                                    <span>{{ formatCurrency(grandTotal) }}</span>
                                </div>
                            </div>

                            <!-- Payment Section -->
                            <div class="mb-3">
                                <label class="form-label small text-muted">Metode Pembayaran</label>
                                <select class="form-select mb-2" v-model="paymentMethod">
                                    <option value="cash">Cash / Tunai</option>
                                    <option value="qris">QRIS</option>
                                    <option value="debit">Kartu Debit</option>
                                    <option value="transfer">Bank Transfer</option>
                                </select>
                                <label class="form-label small text-muted">Jumlah Bayar (Rp)</label>
                                <RupiahInput v-model="paidAmount" className="form-control-lg fw-bold text-end text-primary" placeholder="0" />
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-bold">Kembalian:</span>
                                <span class="fw-bold fs-5" :class="changeAmount >= 0 ? 'text-success' : 'text-danger'">{{ formatCurrency(changeAmount) }}</span>
                            </div>

                            <button class="btn btn-success btn-lg w-100 shadow-sm fw-bold" :disabled="isProcessing || cart.length === 0" @click="handleCheckout">
                                <i class="bx bx-check-circle me-1"></i> Bayar & Cetak Struk
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Struk Thermal Receipt Modal -->
        <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-sm">
                <div class="modal-content" v-if="receiptData">
                    <div class="modal-body p-3 font-monospace" id="printableReceipt">
                        <div class="text-center mb-2">
                            <h5 class="fw-bold mb-0">APOTEK MEDIKA SORE</h5>
                            <small class="d-block">Jl. Raya Farmasi No. 10</small>
                            <small class="d-block">Telp: 021-5551234</small>
                        </div>
                        <hr class="my-1 border-dashed">
                        <div class="small mb-2">
                            <div>No: {{ receiptData.invoice_number }}</div>
                            <div>Tgl: {{ receiptData.date }}</div>
                            <div>Metode: {{ receiptData.payment_method.toUpperCase() }}</div>
                        </div>
                        <hr class="my-1 border-dashed">
                        <div v-for="i in receiptData.items" :key="i.kode" class="small d-flex justify-content-between">
                            <span>{{ i.nama }} x{{ i.quantity }}</span>
                            <span>{{ formatCurrency(i.harga * i.quantity) }}</span>
                        </div>
                        <hr class="my-1 border-dashed">
                        <div class="small d-flex justify-content-between fw-bold">
                            <span>TOTAL</span>
                            <span>{{ formatCurrency(receiptData.grand_total) }}</span>
                        </div>
                        <div class="small d-flex justify-content-between">
                            <span>BAYAR</span>
                            <span>{{ formatCurrency(receiptData.paid_amount) }}</span>
                        </div>
                        <div class="small d-flex justify-content-between">
                            <span>KEMBALI</span>
                            <span>{{ formatCurrency(receiptData.change_amount) }}</span>
                        </div>
                        <hr class="my-1 border-dashed">
                        <div class="text-center small mt-2">
                            <p class="mb-0">Terima kasih atas kunjungan Anda!</p>
                            <small>Semoga Lekas Sembuh</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                        <button type="button" class="btn btn-primary btn-sm" @click="printReceipt"><i class="bx bx-printer me-1"></i> Print</button>
                    </div>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>

<style scoped>
.product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
}
</style>
