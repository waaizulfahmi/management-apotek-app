<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    suppliers: Array,
    outlets: Array,
    medicines: Array,
    default_outlet_id: [Number, String],
    generated_receipt_number: String,
    prefilled_po: Object,
});

const form = useForm({
    invoice_number: '',
    receipt_number: props.generated_receipt_number || '',
    delivery_order_number: '',
    purchase_order_id: props.prefilled_po?.id || null,
    purchase_date: new Date().toISOString().slice(0, 10),
    received_date: new Date().toISOString().slice(0, 10),
    due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
    supplier_id: props.prefilled_po?.supplier_id || props.suppliers[0]?.id || '',
    outlet_id: props.prefilled_po?.outlet_id || props.default_outlet_id || props.outlets[0]?.id || '',
    payment_type: 'credit',
    paid_amount: 0,
    discount_type: 'nominal',
    discount_rate: 0,
    discount_amount: 0,
    tax_type: 'none',
    tax_rate: 0,
    tax_amount: 0,
    shipping_cost: 0,
    notes: '',
    document: null,
    finalize_immediately: false,
    items: [],
});

const documentPreview = ref(null);
const showPreviewModal = ref(false);
const medicineSearchQuery = ref('');
const showSearchDropdown = ref(false);

// Filter obat untuk autocomplete
const filteredMedicines = computed(() => {
    const q = medicineSearchQuery.value.trim().toLowerCase();
    if (!q) return [];
    return props.medicines.filter(m =>
        m.nama.toLowerCase().includes(q) ||
        m.kode.toLowerCase().includes(q) ||
        (m.barcode && m.barcode.toLowerCase().includes(q))
    ).slice(0, 10);
});

// Menambahkan item obat ke dalam tabel faktur
const addItem = (med) => {
    const defaultUnit = med.units?.find(u => u.is_purchase_unit) || med.units?.[0] || {
        unit_id: med.satuan_dasar_id,
        unit_name: med.satuan_dasar_name || 'Unit',
        conversion_factor: 1,
        purchase_price: med.harga_beli || 0,
    };

    form.items.push({
        medicine_id: med.kode,
        medicine_name: med.nama,
        barcode: med.barcode || '-',
        satuan_dasar_name: med.satuan_dasar_name || 'Unit',
        available_units: med.units || [],
        unit_id: defaultUnit.unit_id || null,
        unit_name: defaultUnit.unit_name,
        conversion_factor: defaultUnit.conversion_factor || 1,
        quantity: 1,
        unit_price: defaultUnit.purchase_price || med.harga_beli || 0,
        discount_percent: 0,
        discount_amount: 0,
        tax_percent: 0,
        tax_amount: 0,
        batch_number: '',
        expired_date: '',
        notes: '',
    });

    medicineSearchQuery.value = '';
    showSearchDropdown.value = false;
};

// Menghapus baris item
const removeItem = (index) => {
    form.items.splice(index, 1);
};

// Ketika satuan pembelian diubah pada baris obat
const onUnitChange = (item) => {
    const selected = item.available_units.find(u => u.unit_id == item.unit_id);
    if (selected) {
        item.unit_name = selected.unit_name;
        item.conversion_factor = selected.conversion_factor || 1;
        if (selected.purchase_price > 0) {
            item.unit_price = selected.purchase_price;
        }
    }
};

// Handle file scan upload
const onFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.document = file;
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (ev) => {
                documentPreview.value = ev.target.result;
            };
            reader.readAsDataURL(file);
        } else {
            documentPreview.value = 'pdf';
        }
    } else {
        form.document = null;
        documentPreview.value = null;
    }
};

// Kalkulasi Item & Header secara real-time
const itemCalculations = computed(() => {
    return form.items.map(item => {
        const qty = Math.max(0, parseFloat(item.quantity) || 0);
        const price = Math.max(0, parseFloat(item.unit_price) || 0);
        const conv = Math.max(0.0001, parseFloat(item.conversion_factor) || 1);
        const qtyBase = Math.round(qty * conv * 100) / 100;

        const subtotal = Math.round(qty * price * 100) / 100;

        let disc = Math.max(0, parseFloat(item.discount_amount) || 0);
        const discPct = Math.max(0, parseFloat(item.discount_percent) || 0);
        if (discPct > 0) {
            disc = Math.round(subtotal * (discPct / 100) * 100) / 100;
        }

        let tax = Math.max(0, parseFloat(item.tax_amount) || 0);
        const taxPct = Math.max(0, parseFloat(item.tax_percent) || 0);
        if (taxPct > 0) {
            tax = Math.round((subtotal - disc) * (taxPct / 100) * 100) / 100;
        }

        const net = Math.round(((subtotal - disc) + tax) * 100) / 100;

        return {
            subtotal,
            discount_amount: disc,
            tax_amount: tax,
            subtotal_net: net,
            quantity_base: qtyBase,
        };
    });
});

// Kalkulasi Header Faktur
const invoiceTotals = computed(() => {
    let subtotalItems = 0;
    let totalItemsNet = 0;
    let totalQtyBase = 0;

    itemCalculations.value.forEach(calc => {
        subtotalItems += calc.subtotal;
        totalItemsNet += calc.subtotal_net;
        totalQtyBase += calc.quantity_base;
    });

    // Invoice Discount
    let invDisc = Math.max(0, parseFloat(form.discount_amount) || 0);
    const invDiscRate = Math.max(0, parseFloat(form.discount_rate) || 0);
    if (form.discount_type === 'percentage' && invDiscRate > 0) {
        invDisc = Math.round(totalItemsNet * (invDiscRate / 100) * 100) / 100;
    }

    const afterDiscount = Math.max(0, totalItemsNet - invDisc);

    // Invoice Tax
    let invTax = 0;
    if (form.tax_type === 'ppn_11') {
        invTax = Math.round(afterDiscount * 0.11 * 100) / 100;
    } else if (form.tax_type === 'ppn_12') {
        invTax = Math.round(afterDiscount * 0.12 * 100) / 100;
    } else if (form.tax_type === 'manual' || form.tax_type === 'custom') {
        invTax = Math.max(0, parseFloat(form.tax_amount) || 0);
    }

    const shipping = Math.max(0, parseFloat(form.shipping_cost) || 0);
    const grandTotal = Math.round((afterDiscount + invTax + shipping) * 100) / 100;

    const paid = Math.max(0, parseFloat(form.paid_amount) || 0);
    const remaining = Math.max(0, grandTotal - paid);

    return {
        subtotalItems,
        totalItemsNet,
        totalQtyBase,
        discountAmount: invDisc,
        taxAmount: invTax,
        shippingCost: shipping,
        grandTotal,
        paidAmount: paid,
        remainingAmount: remaining,
    };
});

// Update otomatis form input ketika kalkulasi berubah
watch(invoiceTotals, (val) => {
    form.discount_amount = val.discountAmount;
    form.tax_amount = val.taxAmount;
}, { deep: true });

// Formatter Rupiah
const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

// Formatter Tanggal
const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

// Validasi Form sebelum simpan / finalisasi
const validateForm = () => {
    if (!form.supplier_id) {
        Swal.fire('Validasi Gagal', 'Harap pilih Supplier / PBF terlebih dahulu.', 'warning');
        return false;
    }
    if (!form.items || form.items.length === 0) {
        Swal.fire('Validasi Gagal', 'Harap tambahkan minimal 1 item obat ke dalam faktur.', 'warning');
        return false;
    }
    for (let i = 0; i < form.items.length; i++) {
        const item = form.items[i];
        if (!item.quantity || item.quantity <= 0) {
            Swal.fire('Validasi Gagal', `Jumlah barang untuk ${item.medicine_name} harus lebih besar dari 0.`, 'warning');
            return false;
        }
        if (item.unit_price < 0) {
            Swal.fire('Validasi Gagal', `Harga beli untuk ${item.medicine_name} tidak boleh negatif.`, 'warning');
            return false;
        }
    }
    return true;
};

// Simpan sebagai DRAFT
const saveDraft = () => {
    if (!validateForm()) return;
    form.finalize_immediately = false;
    form.post(route('purchases.invoices.store'), {
        preserveScroll: true,
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            Swal.fire('Gagal Menyimpan', firstErr || 'Periksa kembali data formulir.', 'error');
        }
    });
};

// Buka Modal Preview sebelum Finalisasi
const openFinalizePreview = () => {
    if (!validateForm()) return;

    // Cek peringatan batch / expiry
    const missingBatches = form.items.filter(i => !i.batch_number || !i.expired_date);
    if (missingBatches.length > 0) {
        Swal.fire({
            title: 'Batch & Expired Date Belum Lengkap',
            text: `Terdapat ${missingBatches.length} item obat yang belum memiliki nomor batch atau tanggal kedaluwarsa. Untuk finalisasi, batch dan ED wajib diisi demi kepatuhan BPOM & FEFO persediaan.`,
            icon: 'warning',
        });
        return;
    }

    showPreviewModal.value = true;
};

// Eksekusi Simpan & Langsung Finalisasi
const executeFinalize = () => {
    form.finalize_immediately = true;
    showPreviewModal.value = false;
    form.post(route('purchases.invoices.store'), {
        preserveScroll: true,
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            Swal.fire('Finalisasi Gagal', firstErr || 'Gagal memproses finalisasi faktur.', 'error');
        }
    });
};

// Load prefilled PO items jika ada
onMounted(() => {
    if (props.prefilled_po && props.prefilled_po.items?.length > 0) {
        props.prefilled_po.items.forEach(poItem => {
            const med = props.medicines.find(m => m.kode === poItem.medicine_id);
            if (med) {
                const defaultUnit = med.units?.find(u => u.unit_id === poItem.unit_id) || med.units?.[0] || {
                    unit_id: med.satuan_dasar_id,
                    unit_name: med.satuan_dasar_name || 'Unit',
                    conversion_factor: 1,
                    purchase_price: poItem.unit_price || med.harga_beli || 0,
                };

                form.items.push({
                    medicine_id: med.kode,
                    medicine_name: med.nama,
                    barcode: med.barcode || '-',
                    satuan_dasar_name: med.satuan_dasar_name || 'Unit',
                    available_units: med.units || [],
                    unit_id: defaultUnit.unit_id || null,
                    unit_name: defaultUnit.unit_name,
                    conversion_factor: defaultUnit.conversion_factor || 1,
                    quantity: poItem.outstanding_quantity || poItem.order_quantity || 1,
                    unit_price: poItem.unit_price || defaultUnit.purchase_price || 0,
                    discount_percent: 0,
                    discount_amount: 0,
                    tax_percent: 0,
                    tax_amount: 0,
                    batch_number: '',
                    expired_date: '',
                    notes: `Ref PO #${props.prefilled_po.po_number}`,
                });
            }
        });
    }
});
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4 pb-5">
            <div class="content mt-4">
                <!-- Header Breadcrumbs & Title -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <Link :href="route('purchases.invoices.index')" class="text-decoration-none text-muted small">
                                <i class="bx bx-left-arrow-alt"></i> Kembali ke Daftar Faktur
                            </Link>
                            <span class="text-muted">/</span>
                            <span class="badge bg-primary-subtle text-primary">Faktur Pembelian Baru</span>
                        </div>
                        <h2 class="fw-bold text-dark mb-0">
                            <i class="bx bx-file-blank text-primary me-2"></i>Input Faktur Pembelian Farmasi
                        </h2>
                        <p class="text-muted small mb-0">
                            Pencatatan penerimaan fisik barang dari supplier/PBF, detail batch & tanggal kedaluwarsa, serta konversi satuan stok.
                        </p>
                    </div>

                    <div class="d-flex gap-2">
                        <button 
                            type="button" 
                            class="btn btn-outline-secondary px-3 fw-semibold" 
                            @click="saveDraft" 
                            :disabled="form.processing"
                        >
                            <i class="bx bx-save me-1"></i> Simpan Draft
                        </button>
                        <button 
                            type="button" 
                            class="btn btn-success px-4 fw-bold shadow-sm" 
                            @click="openFinalizePreview" 
                            :disabled="form.processing"
                        >
                            <i class="bx bx-check-double me-1"></i> Preview & Finalisasi
                        </button>
                    </div>
                </div>

                <!-- Alert jika prefilled dari PO -->
                <div v-if="prefilled_po" class="alert alert-info alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
                    <i class="bx bx-link-external fs-4 me-2"></i>
                    <div>
                        Faktur ini dibuat berdasarkan <strong>Purchase Order (PO): {{ prefilled_po.po_number }}</strong>. Seluruh obat dan kuantitas outstanding telah dimuat secara otomatis.
                    </div>
                </div>

                <form @submit.prevent="saveDraft">
                    <div class="row g-4">
                        <!-- Left Column: Header Information -->
                        <div class="col-12 col-xl-8">
                            <!-- Header Form Card -->
                            <div class="card bg-white border-0 shadow-sm rounded-3 mb-4">
                                <div class="card-header bg-light bg-opacity-75 py-3 border-bottom d-flex align-items-center">
                                    <i class="bx bx-detail text-primary me-2 fs-5"></i>
                                    <h6 class="fw-bold mb-0 text-dark">Informasi Header Faktur Supplier & Penerimaan</h6>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <!-- Nomor Faktur Supplier -->
                                        <div class="col-12 col-md-6">
                                            <label class="form-label fw-semibold small text-dark">
                                                Nomor Faktur Supplier / PBF <span class="text-danger">*</span>
                                            </label>
                                            <input 
                                                type="text" 
                                                class="form-control" 
                                                v-model="form.invoice_number" 
                                                placeholder="Contoh: INV-PBF/2026/10/089"
                                                required
                                            >
                                            <small class="text-muted" style="font-size: 11px;">Nomor faktur cetak resmi yang tertera pada lembar dari supplier.</small>
                                        </div>

                                        <!-- Nomor Penerimaan Internal -->
                                        <div class="col-12 col-md-6">
                                            <label class="form-label fw-semibold small text-dark">
                                                Nomor Penerimaan Internal (Sistem)
                                            </label>
                                            <input 
                                                type="text" 
                                                class="form-control bg-light font-monospace" 
                                                v-model="form.receipt_number" 
                                                readonly
                                            >
                                            <small class="text-muted" style="font-size: 11px;">Nomor referensi otomatis internal apotek.</small>
                                        </div>

                                        <!-- Supplier / PBF -->
                                        <div class="col-12 col-md-6">
                                            <label class="form-label fw-semibold small text-dark">
                                                Supplier / Pedagang Besar Farmasi (PBF) <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" v-model="form.supplier_id" required>
                                                <option value="" disabled>-- Pilih Supplier / PBF --</option>
                                                <option v-for="s in suppliers" :key="s.id" :value="s.id">
                                                    {{ s.name }} ({{ s.code || 'PBF' }})
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Outlet Tujuan -->
                                        <div class="col-12 col-md-6">
                                            <label class="form-label fw-semibold small text-dark">
                                                Outlet / Cabang Tujuan Masuk Stok <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" v-model="form.outlet_id" required>
                                                <option v-for="o in outlets" :key="o.id" :value="o.id">
                                                    {{ o.name }} ({{ o.code || 'Cabang' }})
                                                </option>
                                            </select>
                                            <small class="text-muted" style="font-size: 11px;">Lokasi fisik persediaan obat yang akan bertambah stoknya.</small>
                                        </div>

                                        <!-- Tanggal Faktur & Tanggal Penerimaan -->
                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">
                                                Tanggal Faktur <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" class="form-control" v-model="form.purchase_date" required>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">
                                                Tanggal Penerimaan Barang <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" class="form-control" v-model="form.received_date" required>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">
                                                Tanggal Jatuh Tempo
                                            </label>
                                            <input type="date" class="form-control" v-model="form.due_date">
                                        </div>

                                        <!-- Surat Jalan & PO Reference -->
                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">
                                                Nomor Surat Jalan (DO)
                                            </label>
                                            <input 
                                                type="text" 
                                                class="form-control" 
                                                v-model="form.delivery_order_number" 
                                                placeholder="Contoh: SJ-2026/8991"
                                            >
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">
                                                Jenis Transaksi Pembayaran <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" v-model="form.payment_type">
                                                <option value="credit">Kredit (Hutang Dagang PBF)</option>
                                                <option value="cash">Tunai / Cash</option>
                                                <option value="termin">Termin Bertahap</option>
                                            </select>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">
                                                Jumlah Dibayar Dimuka (DP/Cash)
                                            </label>
                                            <input 
                                                type="number" 
                                                step="any" 
                                                class="form-control" 
                                                v-model.number="form.paid_amount" 
                                                min="0"
                                            >
                                        </div>

                                        <!-- Catatan Faktur -->
                                        <div class="col-12 col-md-8">
                                            <label class="form-label fw-semibold small text-dark">
                                                Catatan / Keterangan Faktur
                                            </label>
                                            <input 
                                                type="text" 
                                                class="form-control" 
                                                v-model="form.notes" 
                                                placeholder="Contoh: Barang diterima lengkap dalam kondisi baik sesuai PO."
                                            >
                                        </div>

                                        <!-- Upload Dokumen Scan Faktur -->
                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">
                                                Upload Scan / Foto Faktur
                                            </label>
                                            <input 
                                                type="file" 
                                                class="form-control form-control-sm" 
                                                @change="onFileChange" 
                                                accept="image/*,.pdf"
                                            >
                                            <div v-if="documentPreview && documentPreview !== 'pdf'" class="mt-2">
                                                <img :src="documentPreview" class="img-thumbnail" style="max-height: 80px;" alt="Scan Faktur">
                                            </div>
                                            <div v-else-if="documentPreview === 'pdf'" class="mt-1 text-primary small">
                                                <i class="bx bxs-file-pdf fs-5 me-1"></i> File PDF terlampir
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Detail Items Table Card -->
                            <div class="card bg-white border-0 shadow-sm rounded-3">
                                <div class="card-header bg-light bg-opacity-75 py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="d-flex align-items-center">
                                        <i class="bx bx-capsule text-primary me-2 fs-5"></i>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark">Daftar Obat & Konversi Satuan Masuk Stok</h6>
                                            <small class="text-muted">Setiap baris mencatat batch, expired date, satuan beli, dan konversi ke satuan dasar.</small>
                                        </div>
                                    </div>
                                    <span class="badge bg-primary px-3 py-2">
                                        {{ form.items.length }} Item Obat
                                    </span>
                                </div>

                                <div class="card-body p-3">
                                    <!-- Search / Autocomplete Box for Adding Medicines -->
                                    <div class="position-relative mb-3">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0">
                                                <i class="bx bx-search text-primary fs-5"></i>
                                            </span>
                                            <input 
                                                type="text" 
                                                class="form-control border-start-0 py-2" 
                                                v-model="medicineSearchQuery" 
                                                @focus="showSearchDropdown = true" 
                                                placeholder="Ketik Nama Obat, Kode Obat, atau Scan Barcode untuk menambahkan obat..."
                                            >
                                        </div>

                                        <!-- Autocomplete Results Dropdown -->
                                        <div 
                                            v-if="showSearchDropdown && filteredMedicines.length > 0" 
                                            class="position-absolute w-100 bg-white border rounded-3 shadow-lg mt-1 p-0 overflow-hidden" 
                                            style="z-index: 1050; max-height: 280px; overflow-y: auto;"
                                        >
                                            <div 
                                                v-for="med in filteredMedicines" 
                                                :key="med.kode" 
                                                class="p-2 border-bottom hover-bg-light cursor-pointer d-flex justify-content-between align-items-center"
                                                @click="addItem(med)"
                                                style="cursor: pointer;"
                                            >
                                                <div>
                                                    <div class="fw-bold text-dark">{{ med.nama }}</div>
                                                    <div class="small text-muted">
                                                        Kode: <span class="badge bg-light text-dark border">{{ med.kode }}</span> | 
                                                        Satuan Dasar: <strong>{{ med.satuan_dasar_name }}</strong> | 
                                                        Stok Saat Ini: {{ med.stok }}
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <div class="fw-semibold text-primary">{{ formatCurrency(med.harga_beli) }}</div>
                                                    <span class="badge bg-primary-subtle text-primary">+ Tambah Item</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Medicines Items Dynamic Table -->
                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle mb-0" style="min-width: 1050px;">
                                            <thead class="table-light small text-secondary text-uppercase" style="font-size: 11px;">
                                                <tr>
                                                    <th class="text-center" style="width: 35px;">#</th>
                                                    <th style="min-width: 220px;">Obat & Master Data</th>
                                                    <th style="min-width: 140px;">No. Batch</th>
                                                    <th style="min-width: 130px;">Expired Date</th>
                                                    <th style="width: 90px;">Qty Beli</th>
                                                    <th style="min-width: 140px;">Satuan Pembelian</th>
                                                    <th style="min-width: 140px;" class="bg-primary-subtle text-primary">Stok Dasar Masuk</th>
                                                    <th style="min-width: 130px;">Harga Beli Satuan</th>
                                                    <th style="width: 90px;">Diskon %</th>
                                                    <th style="min-width: 120px;" class="text-end">Subtotal Bersih</th>
                                                    <th class="text-center" style="width: 45px;"><i class="bx bx-trash"></i></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(item, idx) in form.items" :key="idx">
                                                    <td class="text-center text-muted small">{{ idx + 1 }}</td>
                                                    <!-- Medicine Info -->
                                                    <td>
                                                        <div class="fw-bold text-dark">{{ item.medicine_name }}</div>
                                                        <div class="small text-muted font-monospace" style="font-size: 11px;">
                                                            {{ item.medicine_id }}
                                                            <span v-if="item.barcode && item.barcode !== '-'" class="badge bg-light text-muted border ms-1">
                                                                {{ item.barcode }}
                                                            </span>
                                                        </div>
                                                    </td>

                                                    <!-- Batch Number -->
                                                    <td>
                                                        <input 
                                                            type="text" 
                                                            class="form-control form-control-sm font-monospace" 
                                                            v-model="item.batch_number" 
                                                            placeholder="Wajib diisi"
                                                            required
                                                        >
                                                    </td>

                                                    <!-- Expired Date -->
                                                    <td>
                                                        <input 
                                                            type="date" 
                                                            class="form-control form-control-sm" 
                                                            v-model="item.expired_date" 
                                                            required
                                                        >
                                                    </td>

                                                    <!-- Quantity -->
                                                    <td>
                                                        <input 
                                                            type="number" 
                                                            step="any" 
                                                            class="form-control form-control-sm text-center fw-bold" 
                                                            v-model.number="item.quantity" 
                                                            min="0.01" 
                                                            required
                                                        >
                                                    </td>

                                                    <!-- Satuan Pembelian Dropdown -->
                                                    <td>
                                                        <select 
                                                            class="form-select form-select-sm" 
                                                            v-model="item.unit_id" 
                                                            @change="onUnitChange(item)"
                                                        >
                                                            <option 
                                                                v-for="u in item.available_units" 
                                                                :key="u.unit_id" 
                                                                :value="u.unit_id"
                                                            >
                                                                {{ u.unit_name }} (x{{ u.conversion_factor }})
                                                            </option>
                                                        </select>
                                                    </td>

                                                    <!-- Stok Dasar Masuk (Hasil Konversi Otomatis) -->
                                                    <td class="bg-primary-subtle bg-opacity-25">
                                                        <div class="fw-bold text-primary">
                                                            +{{ itemCalculations[idx]?.quantity_base }} {{ item.satuan_dasar_name }}
                                                        </div>
                                                        <small class="text-muted" style="font-size: 10px;">
                                                            {{ item.quantity }} &times; {{ item.conversion_factor }}
                                                        </small>
                                                    </td>

                                                    <!-- Harga Beli Satuan -->
                                                    <td>
                                                        <input 
                                                            type="number" 
                                                            step="any" 
                                                            class="form-control form-control-sm text-end" 
                                                            v-model.number="item.unit_price" 
                                                            min="0" 
                                                            required
                                                        >
                                                    </td>

                                                    <!-- Diskon Item (%) -->
                                                    <td>
                                                        <input 
                                                            type="number" 
                                                            step="any" 
                                                            class="form-control form-control-sm text-center" 
                                                            v-model.number="item.discount_percent" 
                                                            min="0" 
                                                            max="100"
                                                        >
                                                    </td>

                                                    <!-- Subtotal Bersih Item -->
                                                    <td class="text-end fw-bold text-dark">
                                                        {{ formatCurrency(itemCalculations[idx]?.subtotal_net) }}
                                                    </td>

                                                    <!-- Delete Row -->
                                                    <td class="text-center">
                                                        <button 
                                                            type="button" 
                                                            class="btn btn-sm btn-outline-danger p-1" 
                                                            @click="removeItem(idx)" 
                                                            title="Hapus baris obat"
                                                        >
                                                            <i class="bx bx-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>

                                                <tr v-if="form.items.length === 0">
                                                    <td colspan="11" class="text-center py-4 text-muted">
                                                        <i class="bx bx-cart-download fs-2 text-secondary opacity-50 mb-1 d-block"></i>
                                                        Belum ada obat yang dimasukkan ke faktur.<br/>
                                                        Gunakan kotak pencarian di atas untuk menambahkan obat dari master data.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Totals & Tax / Discount Calculation Sidebar -->
                        <div class="col-12 col-xl-4">
                            <div class="card bg-white border-0 shadow-sm rounded-3 sticky-top" style="top: 20px;">
                                <div class="card-header bg-light bg-opacity-75 py-3 border-bottom">
                                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                                        <i class="bx bx-calculator text-primary me-2 fs-5"></i>
                                        Ringkasan & Kalkulasi Faktur
                                    </h6>
                                </div>
                                <div class="card-body p-3">
                                    <!-- Total Kuantitas Dasar Masuk Stok -->
                                    <div class="alert alert-primary bg-primary-subtle text-primary border-primary-subtle py-2 px-3 rounded-2 mb-3 small d-flex justify-content-between align-items-center">
                                        <span><i class="bx bx-box me-1"></i> Total Stok Dasar Masuk:</span>
                                        <strong class="fs-6">{{ invoiceTotals.totalQtyBase }} Item</strong>
                                    </div>

                                    <!-- Subtotal Bruto Items -->
                                    <div class="d-flex justify-content-between py-1 border-bottom small">
                                        <span class="text-muted">Subtotal Bruto Item:</span>
                                        <span class="fw-semibold">{{ formatCurrency(invoiceTotals.subtotalItems) }}</span>
                                    </div>

                                    <!-- Total Bersih Items -->
                                    <div class="d-flex justify-content-between py-1 border-bottom small">
                                        <span class="text-muted">Total Bersih Item:</span>
                                        <span class="fw-semibold">{{ formatCurrency(invoiceTotals.totalItemsNet) }}</span>
                                    </div>

                                    <!-- Diskon Faktur -->
                                    <div class="py-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0 small text-muted fw-semibold">Diskon Faktur (Global):</label>
                                            <span class="text-danger small fw-semibold">- {{ formatCurrency(invoiceTotals.discountAmount) }}</span>
                                        </div>
                                        <div class="input-group input-group-sm">
                                            <select class="form-select" style="max-width: 90px;" v-model="form.discount_type">
                                                <option value="nominal">Rp</option>
                                                <option value="percentage">%</option>
                                            </select>
                                            <input 
                                                v-if="form.discount_type === 'percentage'" 
                                                type="number" 
                                                step="any" 
                                                class="form-control" 
                                                v-model.number="form.discount_rate" 
                                                placeholder="0%" 
                                                min="0" 
                                                max="100"
                                            >
                                            <input 
                                                v-else 
                                                type="number" 
                                                step="any" 
                                                class="form-control" 
                                                v-model.number="form.discount_amount" 
                                                placeholder="Rp 0" 
                                                min="0"
                                            >
                                        </div>
                                    </div>

                                    <!-- Pajak Faktur (PPN) -->
                                    <div class="py-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0 small text-muted fw-semibold">Pajak Faktur (PPN):</label>
                                            <span class="text-primary small fw-semibold">+ {{ formatCurrency(invoiceTotals.taxAmount) }}</span>
                                        </div>
                                        <select class="form-select form-select-sm mb-1" v-model="form.tax_type">
                                            <option value="none">Tanpa Pajak (Non PPN)</option>
                                            <option value="ppn_11">PPN 11% (Standar Faktur)</option>
                                            <option value="ppn_12">PPN 12%</option>
                                            <option value="manual">Nominal Pajak Manual</option>
                                        </select>
                                        <input 
                                            v-if="form.tax_type === 'manual'" 
                                            type="number" 
                                            step="any" 
                                            class="form-control form-control-sm" 
                                            v-model.number="form.tax_amount" 
                                            placeholder="Nominal PPN (Rp)"
                                        >
                                    </div>

                                    <!-- Biaya Tambahan / Ongkir -->
                                    <div class="py-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0 small text-muted fw-semibold">Biaya Tambahan / Ongkir:</label>
                                            <span class="small fw-semibold">+ {{ formatCurrency(invoiceTotals.shippingCost) }}</span>
                                        </div>
                                        <input 
                                            type="number" 
                                            step="any" 
                                            class="form-control form-control-sm" 
                                            v-model.number="form.shipping_cost" 
                                            placeholder="Rp 0" 
                                            min="0"
                                        >
                                    </div>

                                    <!-- Grand Total Akhir Faktur -->
                                    <div class="py-3 border-bottom bg-light bg-opacity-50 px-2 rounded-2 my-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark">TOTAL AKHIR FAKTUR:</span>
                                            <h4 class="fw-bold text-primary mb-0">{{ formatCurrency(invoiceTotals.grandTotal) }}</h4>
                                        </div>
                                    </div>

                                    <!-- Status & Sisa Hutang -->
                                    <div class="d-flex justify-content-between py-1 small">
                                        <span class="text-muted">Jumlah Dibayar:</span>
                                        <span class="text-success fw-semibold">{{ formatCurrency(invoiceTotals.paidAmount) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 small border-bottom mb-3">
                                        <span class="text-muted">Sisa Hutang ke PBF:</span>
                                        <span class="text-danger fw-bold">{{ formatCurrency(invoiceTotals.remainingAmount) }}</span>
                                    </div>

                                    <!-- Submit Action Buttons -->
                                    <div class="d-grid gap-2">
                                        <button 
                                            type="button" 
                                            class="btn btn-success py-2 fw-bold shadow-sm" 
                                            @click="openFinalizePreview" 
                                            :disabled="form.processing"
                                        >
                                            <i class="bx bx-check-double me-1"></i> Preview & Finalisasi Faktur
                                        </button>
                                        <button 
                                            type="button" 
                                            class="btn btn-outline-secondary py-2 fw-semibold" 
                                            @click="saveDraft" 
                                            :disabled="form.processing"
                                        >
                                            <i class="bx bx-save me-1"></i> Simpan Sebagai Draft
                                        </button>
                                    </div>

                                    <div class="alert alert-warning py-2 px-3 mt-3 mb-0 small" style="font-size: 11px;">
                                        <i class="bx bx-shield-quarter me-1"></i>
                                        <strong>Catatan Penting:</strong> Faktur draft <strong>tidak akan</strong> mengubah stok. Stok fisik obat hanya akan bertambah ke outlet setelah Anda menekan tombol <strong>Finalisasi</strong>.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- PREVIEW & CONFIRMATION MODAL BEFORE FINALIZATION -->
                <div v-if="showPreviewModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0, 0, 0, 0.6); z-index: 1060;">
                    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content border-0 shadow-lg rounded-3">
                            <div class="modal-header bg-primary text-white py-3">
                                <h5 class="modal-title fw-bold">
                                    <i class="bx bx-check-shield me-2"></i>Konfirmasi & Preview Finalisasi Faktur Pembelian
                                </h5>
                                <button type="button" class="btn-close btn-close-white" @click="showPreviewModal = false"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="alert alert-primary d-flex align-items-center mb-3">
                                    <i class="bx bx-info-circle fs-3 me-2"></i>
                                    <div class="small">
                                        Harap periksa kembali detail obat, nomor batch, expired date, dan konversi satuan sebelum memfinalisasi. 
                                        Setelah difinalisasi, <strong>seluruh obat akan otomatis masuk ke persediaan</strong> dan kartu stok akan dicatat secara permanen.
                                    </div>
                                </div>

                                <!-- Summary Header Info -->
                                <div class="row g-3 bg-light p-3 rounded-3 mb-3 border">
                                    <div class="col-md-3">
                                        <small class="text-muted d-block">Supplier / PBF:</small>
                                        <strong class="text-dark">{{ suppliers.find(s => s.id == form.supplier_id)?.name || '-' }}</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted d-block">No. Faktur Supplier:</small>
                                        <strong class="text-primary">{{ form.invoice_number || form.receipt_number }}</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted d-block">Outlet Tujuan Masuk Stok:</small>
                                        <strong class="text-dark">{{ outlets.find(o => o.id == form.outlet_id)?.name || 'Gudang Pusat' }}</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted d-block">Tgl Terima & Faktur:</small>
                                        <strong class="text-dark">{{ formatDate(form.received_date) }}</strong>
                                    </div>
                                </div>

                                <!-- Items Preview Table -->
                                <div class="table-responsive mb-3">
                                    <table class="table table-bordered table-sm align-middle mb-0">
                                        <thead class="table-light small text-secondary">
                                            <tr>
                                                <th>#</th>
                                                <th>Nama Obat</th>
                                                <th>Batch</th>
                                                <th>Expired Date</th>
                                                <th class="text-center">Qty Pembelian</th>
                                                <th class="text-center bg-primary-subtle text-primary">Stok Dasar Masuk</th>
                                                <th class="text-end">Harga Satuan</th>
                                                <th class="text-end">Subtotal Bersih</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, idx) in form.items" :key="idx">
                                                <td>{{ idx + 1 }}</td>
                                                <td>
                                                    <strong>{{ item.medicine_name }}</strong>
                                                    <div class="small text-muted">{{ item.medicine_id }}</div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border font-monospace">
                                                        {{ item.batch_number || 'BELUM DIISI' }}
                                                    </span>
                                                </td>
                                                <td>{{ formatDate(item.expired_date) }}</td>
                                                <td class="text-center">
                                                    {{ item.quantity }} {{ item.unit_name }}
                                                </td>
                                                <td class="text-center bg-primary-subtle fw-bold text-primary">
                                                    +{{ itemCalculations[idx]?.quantity_base }} {{ item.satuan_dasar_name }}
                                                </td>
                                                <td class="text-end">{{ formatCurrency(item.unit_price) }}</td>
                                                <td class="text-end fw-bold">{{ formatCurrency(itemCalculations[idx]?.subtotal_net) }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="5" class="text-end">Total Akhir Faktur:</th>
                                                <th class="text-center text-primary fw-bold">+{{ invoiceTotals.totalQtyBase }} Satuan Dasar</th>
                                                <th colspan="2" class="text-end fs-6 text-primary fw-bold">{{ formatCurrency(invoiceTotals.grandTotal) }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="small text-muted">
                                        Status Pembayaran: <span class="badge bg-secondary">{{ form.payment_type.toUpperCase() }}</span> | 
                                        Sisa Hutang: <strong class="text-danger">{{ formatCurrency(invoiceTotals.remainingAmount) }}</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light py-2">
                                <button type="button" class="btn btn-secondary" @click="showPreviewModal = false">
                                    <i class="bx bx-edit me-1"></i> Kembali & Edit Data
                                </button>
                                <button type="button" class="btn btn-primary fw-bold px-4" @click="executeFinalize" :disabled="form.processing">
                                    <i class="bx bx-check-double me-1"></i> Ya, Finalisasi & Tambah Stok
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>

<style scoped>
/* High contrast styles for Create Invoice Form */
:global(body.dark-mode) .card {
    background-color: #1e293b !important;
    border: 1px solid #334155 !important;
}

:global(body.dark-mode) .card-header {
    background-color: #0f172a !important;
    border-bottom: 1px solid #334155 !important;
}

:global(body.dark-mode) .form-label,
:global(body.dark-mode) .text-dark,
:global(body.dark-mode) h2,
:global(body.dark-mode) h4,
:global(body.dark-mode) h5,
:global(body.dark-mode) h6 {
    color: #f8fafc !important;
}

:global(body.dark-mode) .text-muted {
    color: #cbd5e1 !important;
}

:global(body.dark-mode) .table thead th {
    background-color: #0f172a !important;
    color: #f8fafc !important;
    font-size: 0.76rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.04em !important;
    border-bottom: 2px solid #334155 !important;
}

:global(body.dark-mode) .table tbody td {
    background-color: #1e293b !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}

:global(body.dark-mode) .form-control,
:global(body.dark-mode) .form-select {
    background-color: #0f172a !important;
    color: #ffffff !important;
    border: 1px solid #475569 !important;
}

:global(body.dark-mode) .form-control:focus,
:global(body.dark-mode) .form-select:focus {
    border-color: #38bdf8 !important;
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25) !important;
}

:global(body.dark-mode) .bg-light {
    background-color: #0f172a !important;
}

:global(body.dark-mode) .modal-content {
    background-color: #1e293b !important;
    color: #f8fafc !important;
    border: 1px solid #475569 !important;
}

:global(body.dark-mode) .modal-footer {
    background-color: #0f172a !important;
    border-top: 1px solid #334155 !important;
}
</style>
