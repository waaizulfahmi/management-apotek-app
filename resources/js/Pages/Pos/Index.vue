<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import RupiahInput from '@/Components/RupiahInput.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import { showSuccess, showWarning, showError } from '@/Utils/swal';

const props = defineProps({
    medicines: Array,
    customers: Array,
    prescriptions: Array,
    masterShifts: Array,
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

// Confirmation Modal State
const showConfirmModal = ref(false);

// Auto-select initial master shift matching current time
const getInitialMasterShift = () => {
    if (!props.masterShifts || props.masterShifts.length === 0) return null;
    const now = new Date();
    const curStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0') + ':00';

    const matched = props.masterShifts.find(s => {
        const start = s.start_time;
        const end = s.end_time;
        if (start <= end) {
            return curStr >= start && curStr <= end;
        } else {
            return curStr >= start || curStr <= end;
        }
    });

    return matched ? matched : props.masterShifts[0];
};

const initialMasterShift = getInitialMasterShift();

// Active Shift State
const activeShift = ref(null);
const showOpenShiftModal = ref(false);
const openShiftForm = useForm({
    master_shift_id: initialMasterShift ? initialMasterShift.id : '',
    shift_name: initialMasterShift ? initialMasterShift.name : 'Shift Pagi',
    opening_cash: 500000,
    outlet_id: '',
});

const selectedMasterShiftInPos = computed(() => {
    if (!props.masterShifts) return null;
    return props.masterShifts.find(s => s.id === openShiftForm.master_shift_id);
});

watch(() => openShiftForm.master_shift_id, (newId) => {
    const s = props.masterShifts?.find(x => x.id === newId);
    if (s) {
        openShiftForm.shift_name = s.name;
    }
});

const isPosShiftTimeMismatch = computed(() => {
    if (!selectedMasterShiftInPos.value) return false;
    const now = new Date();
    const curStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0') + ':00';

    const start = selectedMasterShiftInPos.value.start_time;
    const end = selectedMasterShiftInPos.value.end_time;

    if (start <= end) {
        return curStr < start || curStr > end;
    } else {
        return curStr < start && curStr > end;
    }
});

const fetchActiveShift = async () => {
    try {
        const res = await axios.get(route('shifts.active'));
        activeShift.value = res.data.active_shift;
    } catch (e) {
        console.error('Failed to fetch active shift', e);
    }
};

const submitOpenShift = () => {
    openShiftForm.post(route('shifts.open'), {
        onSuccess: () => {
            showOpenShiftModal.value = false;
            fetchActiveShift();
        }
    });
};

// Prescription Selection State
const selectedPrescription = ref(null);
const showPrescriptionModal = ref(false);

const selectPrescription = (rx) => {
    selectedPrescription.value = rx;
    if (rx.customer_id) {
        selectedCustomer.value = rx.customer_id;
    }
    
    // Auto-fill cart with items prescribed
    if (rx.items && rx.items.length > 0) {
        cart.value = [];
        rx.items.forEach(item => {
            const med = props.medicines.find(m => m.kode === item.medicine_id);
            cart.value.push({
                kode: item.medicine_id,
                nama: item.medicine_name || (med ? med.nama : item.medicine_id),
                harga: item.unit_price ? Number(item.unit_price) : (med ? Number(med.harga) : 0),
                quantity: item.quantity || 1,
                maxStok: med ? med.stok : (item.stock || 999),
            });
        });
    }

    showSuccess(
        'Resep Dokter Dimuat!',
        `Resep ${rx.prescription_number} (${rx.patient_name || 'Pasien Umum'}) berhasil dihubungkan ke Keranjang Kasir!`
    );
    showPrescriptionModal.value = false;
};

const clearPrescription = () => {
    selectedPrescription.value = null;
    showWarning('Resep Dilepas', 'Transaksi dialihkan kembali ke Penjualan Bebas (Tanpa Resep).');
};

onMounted(() => {
    fetchActiveShift();

    // Auto-select prescription if rx_id query parameter is present in URL
    const urlParams = new URLSearchParams(window.location.search);
    const rxIdParam = urlParams.get('rx_id');
    if (rxIdParam && props.prescriptions) {
        const foundRx = props.prescriptions.find(r => r.id == rxIdParam);
        if (foundRx) {
            selectPrescription(foundRx);
        }
    }
});

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

const handleCheckout = () => {
    if (!activeShift.value) {
        showWarning('Shift Belum Dibuka!', 'Shift belum dibuka. Silakan buka shift terlebih dahulu.');
        showOpenShiftModal.value = true;
        return;
    }
    if (cart.value.length === 0) {
        showWarning('Keranjang Kosong!', 'Tambahkan minimal 1 obat ke keranjang belanja.');
        return;
    }
    if (paidAmount.value < grandTotal.value) {
        showWarning('Pembayaran Kurang!', 'Jumlah uang pembayaran kurang dari total belanja.');
        return;
    }

    // Open Confirmation Modal
    showConfirmModal.value = true;
};

const executeFinalCheckout = async () => {
    showConfirmModal.value = false;
    isProcessing.value = true;
    try {
        const payload = {
            items: cart.value,
            customer_id: selectedCustomer.value || null,
            prescription_id: selectedPrescription.value ? selectedPrescription.value.id : null,
            discount: discount.value,
            tax: taxAmount.value,
            payment_method: paymentMethod.value,
            paid_amount: paidAmount.value,
        };

        const res = await axios.post(route('pos.checkout'), payload);
        if (res.data.success) {
            const invoiceNumber = res.data.data.invoice_number;
            receiptData.value = {
                invoice_number: invoiceNumber,
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

            // Reset cart & inputs
            cart.value = [];
            paidAmount.value = 0;
            discount.value = 0;
            selectedPrescription.value = null;

            // Alert Penjualan Berhasil
            await showSuccess(
                'Penjualan Berhasil!',
                `Transaksi telah berhasil diproses dengan Nomor Invoice: ${invoiceNumber}`
            );

            // Open Receipt Modal
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
                                <div class="d-flex align-items-center gap-2">
                                    <h4 class="fw-bold mb-0 text-dark"><i class="bx bx-store me-2 text-primary"></i>Kasir / POS</h4>
                                    <Link v-if="activeShift" :href="route('shifts.show', activeShift.id)" class="badge bg-success font-monospace text-decoration-none p-2">
                                        <i class="bx bx-check-circle me-1"></i> Shift: {{ activeShift.shift_name }}
                                    </Link>
                                    <span v-else class="badge bg-warning text-dark font-monospace p-2" @click="showOpenShiftModal = true" style="cursor: pointer;">
                                        <i class="bx bx-error me-1"></i> Shift Belum Dibuka (Buka)
                                    </span>
                                </div>
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

                            <!-- Prescription Selector & Linked Badge -->
                            <div class="mb-3">
                                <div v-if="selectedPrescription" class="p-2 px-3 rounded-3 border border-success bg-success bg-opacity-10 d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <span class="badge bg-success text-uppercase me-2"><i class="bx bx-notepad me-1"></i>Resep Dokter</span>
                                        <strong class="text-dark font-monospace">{{ selectedPrescription.prescription_number }}</strong>
                                        <div class="small text-muted mb-0">Pasien: <strong>{{ selectedPrescription.patient_name || 'Umum' }}</strong> | Dokter: {{ selectedPrescription.doctor_name || '-' }}</div>
                                    </div>
                                    <button type="button" @click="clearPrescription" class="btn btn-sm btn-outline-danger py-0 px-2" title="Lepas Resep">
                                        <i class="bx bx-x"></i> Lepas
                                    </button>
                                </div>
                                <div v-else class="d-flex justify-content-between align-items-center p-2 rounded-3 border bg-light">
                                    <span class="small text-muted fw-semibold"><i class="bx bx-notepad text-primary me-1"></i>Transaksi Resep Dokter?</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" @click="showPrescriptionModal = true">
                                        <i class="bx bx-plus me-1"></i> Pilih Resep
                                    </button>
                                </div>
                            </div>

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
                            <img v-if="$page.props.app_settings?.pharmacy_logo" :src="$page.props.app_settings.pharmacy_logo" alt="Logo Apotek" style="max-height: 40px; object-fit: contain;" class="mb-1 d-block mx-auto">
                            <h5 class="fw-bold mb-0">{{ $page.props.app_settings?.pharmacy_name || 'APOTEK MEDIKA SORA' }}</h5>
                            <small class="d-block">{{ $page.props.app_settings?.pharmacy_address || 'Jl. Raya Farmasi No. 10' }}</small>
                            <small class="d-block">Telp: {{ $page.props.app_settings?.pharmacy_phone || '021-5551234' }}</small>
                            <small v-if="$page.props.app_settings?.pharmacist_name" class="d-block text-muted" style="font-size: 0.68rem;">Apoteker: {{ $page.props.app_settings.pharmacist_name }}</small>
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

        <!-- MODAL KONFIRMASI FINAL PENJUALAN -->
        <div v-if="showConfirmModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.65);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">
                            <i class="bx bx-check-shield me-2"></i>Konfirmasi Final Penjualan
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="showConfirmModal = false"></button>
                    </div>
                    <div class="modal-body text-dark">
                        <div class="alert alert-info border-0 d-flex align-items-center mb-3">
                            <i class="bx bx-info-circle fs-4 me-2"></i>
                            <div>Periksa kembali rincian nama obat, jumlah unit, dan nominal pembayaran kasir sebelum menyelesaikan transaksi.</div>
                        </div>

                        <!-- Summary Header -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <small class="text-muted d-block">Pelanggan / Customer</small>
                                <strong class="text-dark fs-6">{{ selectedCustomerDetails?.name || 'Customer Umum' }}</strong>
                            </div>
                            <div class="col-md-6 text-end">
                                <small class="text-muted d-block">Metode Pembayaran</small>
                                <span class="badge bg-primary text-uppercase fs-6">{{ paymentMethod }}</span>
                            </div>
                        </div>

                        <!-- Table Review Barang -->
                        <h6 class="fw-bold text-dark mb-2"><i class="bx bx-list-check text-primary me-1"></i>Daftar Obat Dibeli ({{ cart.length }} Item)</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered align-middle">
                                <thead class="bg-light text-dark">
                                    <tr>
                                        <th style="width: 35px;" class="text-center">No</th>
                                        <th>Nama Obat</th>
                                        <th class="text-center" style="width: 70px;">Qty</th>
                                        <th class="text-end" style="width: 120px;">Harga Satuan</th>
                                        <th class="text-end" style="width: 130px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, idx) in cart" :key="item.kode">
                                        <td class="text-center text-muted">{{ idx + 1 }}</td>
                                        <td class="fw-bold text-dark">{{ item.nama }}</td>
                                        <td class="text-center font-monospace fw-bold fs-6">{{ item.quantity }}</td>
                                        <td class="text-end text-muted">{{ formatCurrency(item.harga) }}</td>
                                        <td class="text-end fw-bold text-dark">{{ formatCurrency(item.harga * item.quantity) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="4" class="text-end fw-bold">Grand Total:</td>
                                        <td class="text-end fw-bold text-primary fs-6">{{ formatCurrency(grandTotal) }}</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td colspan="4" class="text-end fw-bold">Jumlah Uang Dibayar:</td>
                                        <td class="text-end fw-bold text-success fs-6">{{ formatCurrency(paidAmount) }}</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td colspan="4" class="text-end fw-bold">Uang Kembalian:</td>
                                        <td class="text-end fw-bold text-dark fs-6">{{ formatCurrency(changeAmount) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" @click="showConfirmModal = false">
                            <i class="bx bx-x me-1"></i> Batal & Review Ulang
                        </button>
                        <button type="button" class="btn btn-success btn-lg fw-bold px-4 shadow-sm" :disabled="isProcessing" @click="executeFinalCheckout">
                            <i class="bx bx-check-circle me-1"></i> PROSES FINAL PENJUALAN
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- BUKA SHIFT MODAL IN POS -->
        <div v-if="showOpenShiftModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);" aria-modal="true" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title fw-bold"><i class="bx bx-time-five me-2"></i>Buka Shift Kasir Terlebih Dahulu</h5>
                        <button type="button" class="btn-close" @click="showOpenShiftModal = false"></button>
                    </div>
                    <form @submit.prevent="submitOpenShift">
                        <div class="modal-body text-dark">
                            <div class="alert alert-warning border-0 small mb-3">
                                <i class="bx bx-error-circle me-1"></i> Shift belum dibuka. Kasir wajib membuka shift terlebih dahulu sebelum dapat memproses transaksi kasir.
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted d-flex justify-content-between align-items-center">
                                    <span>Pilihan Master Shift</span>
                                    <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">
                                        <i class="bx bx-time me-1"></i>Jam Sekarang: {{ new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }} WIB
                                    </span>
                                </label>
                                <select v-model="openShiftForm.master_shift_id" class="form-select form-select-lg fw-bold text-primary">
                                    <option v-for="ms in masterShifts" :key="ms.id" :value="ms.id">
                                        {{ ms.name }} ({{ ms.start_time.substring(0,5) }} – {{ ms.end_time.substring(0,5) }} WIB)
                                    </option>
                                </select>
                                <small v-if="selectedMasterShiftInPos" class="text-muted mt-1.5 d-block">
                                    <i class="bx bx-info-circle me-1 text-primary"></i>
                                    Jadwal Operasional: <strong>{{ selectedMasterShiftInPos.start_time.substring(0,5) }} – {{ selectedMasterShiftInPos.end_time.substring(0,5) }} WIB</strong> (Toleransi: {{ selectedMasterShiftInPos.grace_minutes }} menit).
                                </small>
                            </div>

                            <!-- Alert Warning if Mismatch -->
                            <div v-if="isPosShiftTimeMismatch" class="alert alert-warning border border-warning border-opacity-50 rounded-3 p-3 mb-3 shadow-xs">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bx bx-error text-warning fs-3 flex-shrink-0"></i>
                                    <div>
                                        <strong class="text-dark d-block">⚠️ PERINGATAN JAM OPERASIONAL SHIFT!</strong>
                                        <p class="text-dark small mb-0 mt-0.5">
                                            Jam saat ini (<strong>{{ new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }} WIB</strong>) berada di luar jam operasional resmi <strong>{{ selectedMasterShiftInPos?.name }}</strong> ({{ selectedMasterShiftInPos?.start_time?.substring(0,5) }} – {{ selectedMasterShiftInPos?.end_time?.substring(0,5) }} WIB).
                                        </p>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2 pt-2 border-top border-warning border-opacity-25">
                                    * Membuka shift di luar jam operasional akan dicatat dengan status <em>Di Luar Jadwal</em>.
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Modal Awal Kas (Laci Kasir) - Rp</label>
                                <input type="number" v-model.number="openShiftForm.opening_cash" min="0" class="form-control form-control-lg font-monospace fw-bold text-primary" required>
                                <small class="text-muted">Masukkan modal tunai yang ada di laci kasir saat membuka shift ini.</small>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showOpenShiftModal = false">Batal</button>
                            <button type="submit" :disabled="openShiftForm.processing" class="btn btn-warning fw-bold px-4">
                                <i class="bx bx-check-circle me-1"></i> BUKA SHIFT & MULAI
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===== MODAL PILIH RESEP DOKTER ===== -->
        <div v-if="showPrescriptionModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bx bx-notepad me-2"></i>Pilih Resep Dokter (Terverifikasi)</h5>
                        <button type="button" class="btn-close btn-close-white" @click="showPrescriptionModal = false"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-dark">
                                    <tr>
                                        <th>No. Resep</th>
                                        <th>Pasien / Pelanggan</th>
                                        <th>Dokter Penanggung Jawab</th>
                                        <th>Tanggal Resep</th>
                                        <th class="text-center">Total Item</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="rx in (prescriptions || [])" :key="rx.id">
                                        <td class="fw-bold text-primary font-monospace">{{ rx.prescription_number }}</td>
                                        <td class="fw-bold text-dark">{{ rx.patient_name || 'Pasien Umum / Non-Member' }}</td>
                                        <td>{{ rx.doctor_name || 'Dokter Spesialis' }}</td>
                                        <td>{{ rx.prescription_date }}</td>
                                        <td class="text-center fw-bold">{{ rx.items ? rx.items.length : 0 }} Item</td>
                                        <td class="text-center">
                                            <button type="button" @click="selectPrescription(rx)" class="btn btn-sm btn-success fw-bold">
                                                <i class="bx bx-cart me-1"></i> Gunakan Resep
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!prescriptions || prescriptions.length === 0">
                                        <td colspan="6" class="text-center py-4 text-muted">Belum ada resep terverifikasi yang siap diproses.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="showPrescriptionModal = false">Tutup</button>
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
