<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref, computed, watch } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    invoice: Object,
    suppliers: Array,
    outlets: Array,
    medicines: Array,
});

// Mapping initial items
const initialItems = (props.invoice.items || []).map(item => {
    const med = props.medicines.find(m => m.kode === item.medicine_id);
    const availableUnits = med?.units || [
        {
            unit_id: item.unit_id,
            unit_name: item.unit_name || 'Unit',
            conversion_factor: item.conversion_factor || 1,
            purchase_price: item.unit_price,
        }
    ];

    return {
        medicine_id: item.medicine_id,
        medicine_name: item.medicine?.nama || med?.nama || item.medicine_id,
        barcode: item.medicine?.barcode || med?.barcode || '-',
        satuan_dasar_name: med?.satuan_dasar_name || 'Unit',
        available_units: availableUnits,
        unit_id: item.unit_id,
        unit_name: item.unit_name || 'Unit',
        conversion_factor: item.conversion_factor || 1,
        quantity: item.quantity,
        unit_price: item.unit_price,
        discount_percent: item.discount_percent || 0,
        discount_amount: item.discount_amount || 0,
        tax_percent: item.tax_percent || 0,
        tax_amount: item.tax_amount || 0,
        batch_number: item.batch_number || '',
        expired_date: item.expired_date ? item.expired_date.slice(0, 10) : '',
        notes: item.notes || '',
    };
});

const form = useForm({
    _method: 'PUT',
    invoice_number: props.invoice.invoice_number || '',
    receipt_number: props.invoice.receipt_number || '',
    delivery_order_number: props.invoice.delivery_order_number || '',
    purchase_order_id: props.invoice.purchase_order_id || null,
    purchase_date: props.invoice.purchase_date ? props.invoice.purchase_date.slice(0, 10) : new Date().toISOString().slice(0, 10),
    received_date: props.invoice.received_date ? props.invoice.received_date.slice(0, 10) : new Date().toISOString().slice(0, 10),
    due_date: props.invoice.due_date ? props.invoice.due_date.slice(0, 10) : '',
    supplier_id: props.invoice.supplier_id,
    outlet_id: props.invoice.outlet_id,
    payment_type: props.invoice.payment_type || 'credit',
    paid_amount: props.invoice.paid_amount || 0,
    discount_type: props.invoice.discount_type || 'nominal',
    discount_rate: props.invoice.discount_rate || 0,
    discount_amount: props.invoice.discount_amount || 0,
    tax_type: props.invoice.tax_type || 'none',
    tax_rate: props.invoice.tax_rate || 0,
    tax_amount: props.invoice.tax_amount || 0,
    shipping_cost: props.invoice.shipping_cost || 0,
    notes: props.invoice.notes || '',
    document: null,
    finalize_immediately: false,
    items: initialItems,
});

const documentPreview = ref(props.invoice.document_path ? `/storage/${props.invoice.document_path}` : null);
const showPreviewModal = ref(false);
const medicineSearchQuery = ref('');
const showSearchDropdown = ref(false);

const filteredMedicines = computed(() => {
    const q = medicineSearchQuery.value.trim().toLowerCase();
    if (!q) return [];
    return props.medicines.filter(m =>
        m.nama.toLowerCase().includes(q) ||
        m.kode.toLowerCase().includes(q) ||
        (m.barcode && m.barcode.toLowerCase().includes(q))
    ).slice(0, 10);
});

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

const removeItem = (index) => {
    form.items.splice(index, 1);
};

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
    }
};

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

const invoiceTotals = computed(() => {
    let subtotalItems = 0;
    let totalItemsNet = 0;
    let totalQtyBase = 0;

    itemCalculations.value.forEach(calc => {
        subtotalItems += calc.subtotal;
        totalItemsNet += calc.subtotal_net;
        totalQtyBase += calc.quantity_base;
    });

    let invDisc = Math.max(0, parseFloat(form.discount_amount) || 0);
    const invDiscRate = Math.max(0, parseFloat(form.discount_rate) || 0);
    if (form.discount_type === 'percentage' && invDiscRate > 0) {
        invDisc = Math.round(totalItemsNet * (invDiscRate / 100) * 100) / 100;
    }

    const afterDiscount = Math.max(0, totalItemsNet - invDisc);

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

watch(invoiceTotals, (val) => {
    form.discount_amount = val.discountAmount;
    form.tax_amount = val.taxAmount;
}, { deep: true });

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

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

const updateDraft = () => {
    if (!validateForm()) return;
    form.finalize_immediately = false;
    form.post(route('purchases.invoices.update', props.invoice.id), {
        preserveScroll: true,
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            Swal.fire('Gagal Menyimpan', firstErr || 'Periksa kembali data formulir.', 'error');
        }
    });
};

const openFinalizePreview = () => {
    if (!validateForm()) return;

    const missingBatches = form.items.filter(i => !i.batch_number || !i.expired_date);
    if (missingBatches.length > 0) {
        Swal.fire({
            title: 'Batch & Expired Date Belum Lengkap',
            text: `Terdapat ${missingBatches.length} item obat yang belum memiliki nomor batch atau expired date. Harap lengkapi sebelum finalisasi.`,
            icon: 'warning',
        });
        return;
    }

    showPreviewModal.value = true;
};

const executeFinalize = () => {
    form.finalize_immediately = true;
    showPreviewModal.value = false;
    form.post(route('purchases.invoices.update', props.invoice.id), {
        preserveScroll: true,
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            Swal.fire('Finalisasi Gagal', firstErr || 'Gagal memproses finalisasi faktur.', 'error');
        }
    });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4 pb-5">
            <div class="content mt-4">
                <!-- Breadcrumbs & Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <Link :href="route('purchases.invoices.show', invoice.id)" class="text-decoration-none text-muted small">
                                <i class="bx bx-left-arrow-alt"></i> Kembali ke Detail Faktur
                            </Link>
                            <span class="text-muted">/</span>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                Mode Edit Draft Faktur
                            </span>
                        </div>
                        <h2 class="fw-bold text-dark mb-0">
                            <i class="bx bx-edit text-primary me-2"></i>Edit Draft Faktur: {{ invoice.invoice_number || invoice.receipt_number }}
                        </h2>
                        <p class="text-muted small mb-0">
                            Perubahan data hanya dapat dilakukan pada faktur yang masih berstatus DRAFT (belum mempengaruhi stok fisik).
                        </p>
                    </div>

                    <div class="d-flex gap-2">
                        <button 
                            type="button" 
                            class="btn btn-outline-secondary px-3 fw-semibold" 
                            @click="updateDraft" 
                            :disabled="form.processing"
                        >
                            <i class="bx bx-save me-1"></i> Simpan Perubahan Draft
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

                <form @submit.prevent="updateDraft">
                    <div class="row g-4">
                        <!-- Left Column: Header Information -->
                        <div class="col-12 col-xl-8">
                            <div class="card bg-white border-0 shadow-sm rounded-3 mb-4">
                                <div class="card-header bg-light bg-opacity-75 py-3 border-bottom d-flex align-items-center">
                                    <i class="bx bx-detail text-primary me-2 fs-5"></i>
                                    <h6 class="fw-bold mb-0 text-dark">Informasi Header Faktur Supplier</h6>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label fw-semibold small text-dark">
                                                Nomor Faktur Supplier / PBF <span class="text-danger">*</span>
                                            </label>
                                            <input 
                                                type="text" 
                                                class="form-control" 
                                                v-model="form.invoice_number" 
                                                required
                                            >
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label fw-semibold small text-dark">
                                                Nomor Penerimaan Internal
                                            </label>
                                            <input 
                                                type="text" 
                                                class="form-control bg-light font-monospace" 
                                                v-model="form.receipt_number" 
                                                readonly
                                            >
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label fw-semibold small text-dark">
                                                Supplier / PBF <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" v-model="form.supplier_id" required>
                                                <option v-for="s in suppliers" :key="s.id" :value="s.id">
                                                    {{ s.name }} ({{ s.code || 'PBF' }})
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label fw-semibold small text-dark">
                                                Outlet Tujuan Masuk Stok <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" v-model="form.outlet_id" required>
                                                <option v-for="o in outlets" :key="o.id" :value="o.id">
                                                    {{ o.name }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">Tanggal Faktur</label>
                                            <input type="date" class="form-control" v-model="form.purchase_date" required>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">Tanggal Terima</label>
                                            <input type="date" class="form-control" v-model="form.received_date" required>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">Jatuh Tempo</label>
                                            <input type="date" class="form-control" v-model="form.due_date">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">No. Surat Jalan (DO)</label>
                                            <input type="text" class="form-control" v-model="form.delivery_order_number">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">Jenis Pembayaran</label>
                                            <select class="form-select" v-model="form.payment_type">
                                                <option value="credit">Kredit (Hutang Dagang PBF)</option>
                                                <option value="cash">Tunai / Cash</option>
                                                <option value="termin">Termin Bertahap</option>
                                            </select>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">Jumlah Dibayar Dimuka</label>
                                            <input type="number" step="any" class="form-control" v-model.number="form.paid_amount">
                                        </div>

                                        <div class="col-12 col-md-8">
                                            <label class="form-label fw-semibold small text-dark">Catatan Faktur</label>
                                            <input type="text" class="form-control" v-model="form.notes">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold small text-dark">Upload Scan / Dokumen</label>
                                            <input type="file" class="form-control form-control-sm" @change="onFileChange" accept="image/*,.pdf">
                                            <div v-if="documentPreview && documentPreview !== 'pdf'" class="mt-2">
                                                <img :src="documentPreview" class="img-thumbnail" style="max-height: 80px;" alt="Scan Faktur">
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
                                        <h6 class="fw-bold mb-0 text-dark">Daftar Obat & Konversi Satuan</h6>
                                    </div>
                                    <span class="badge bg-primary px-3 py-2">{{ form.items.length }} Item Obat</span>
                                </div>

                                <div class="card-body p-3">
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
                                                placeholder="Tambah obat lain: ketik nama obat, kode, atau barcode..."
                                            >
                                        </div>

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
                                                    <div class="small text-muted">{{ med.kode }} | Satuan Dasar: {{ med.satuan_dasar_name }}</div>
                                                </div>
                                                <span class="badge bg-primary-subtle text-primary">+ Tambah Item</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle mb-0" style="min-width: 1050px;">
                                            <thead class="table-light small text-secondary text-uppercase" style="font-size: 11px;">
                                                <tr>
                                                    <th class="text-center" style="width: 35px;">#</th>
                                                    <th style="min-width: 220px;">Obat</th>
                                                    <th style="min-width: 140px;">No. Batch</th>
                                                    <th style="min-width: 130px;">Expired Date</th>
                                                    <th style="width: 90px;">Qty Beli</th>
                                                    <th style="min-width: 140px;">Satuan Pembelian</th>
                                                    <th style="min-width: 140px;" class="bg-primary-subtle text-primary">Stok Dasar Masuk</th>
                                                    <th style="min-width: 130px;">Harga Satuan</th>
                                                    <th style="width: 90px;">Diskon %</th>
                                                    <th style="min-width: 120px;" class="text-end">Subtotal Bersih</th>
                                                    <th class="text-center" style="width: 45px;"><i class="bx bx-trash"></i></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(item, idx) in form.items" :key="idx">
                                                    <td class="text-center text-muted small">{{ idx + 1 }}</td>
                                                    <td>
                                                        <div class="fw-bold text-dark">{{ item.medicine_name }}</div>
                                                        <div class="small text-muted font-monospace" style="font-size: 11px;">{{ item.medicine_id }}</div>
                                                    </td>

                                                    <td>
                                                        <input 
                                                            type="text" 
                                                            class="form-control form-control-sm font-monospace" 
                                                            v-model="item.batch_number" 
                                                            placeholder="Wajib diisi"
                                                            required
                                                        >
                                                    </td>

                                                    <td>
                                                        <input 
                                                            type="date" 
                                                            class="form-control form-control-sm" 
                                                            v-model="item.expired_date" 
                                                            required
                                                        >
                                                    </td>

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

                                                    <td class="bg-primary-subtle bg-opacity-25">
                                                        <div class="fw-bold text-primary">
                                                            +{{ itemCalculations[idx]?.quantity_base }} {{ item.satuan_dasar_name }}
                                                        </div>
                                                        <small class="text-muted" style="font-size: 10px;">
                                                            {{ item.quantity }} &times; {{ item.conversion_factor }}
                                                        </small>
                                                    </td>

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

                                                    <td class="text-end fw-bold text-dark">
                                                        {{ formatCurrency(itemCalculations[idx]?.subtotal_net) }}
                                                    </td>

                                                    <td class="text-center">
                                                        <button 
                                                            type="button" 
                                                            class="btn btn-sm btn-outline-danger p-1" 
                                                            @click="removeItem(idx)"
                                                        >
                                                            <i class="bx bx-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Summary & Calculations -->
                        <div class="col-12 col-xl-4">
                            <div class="card bg-white border-0 shadow-sm rounded-3 sticky-top" style="top: 20px;">
                                <div class="card-header bg-light bg-opacity-75 py-3 border-bottom">
                                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                                        <i class="bx bx-calculator text-primary me-2 fs-5"></i>
                                        Kalkulasi Faktur
                                    </h6>
                                </div>
                                <div class="card-body p-3">
                                    <div class="alert alert-primary bg-primary-subtle text-primary border-primary-subtle py-2 px-3 rounded-2 mb-3 small d-flex justify-content-between align-items-center">
                                        <span>Total Stok Dasar Masuk:</span>
                                        <strong class="fs-6">{{ invoiceTotals.totalQtyBase }} Item</strong>
                                    </div>

                                    <div class="d-flex justify-content-between py-1 border-bottom small">
                                        <span class="text-muted">Total Bersih Item:</span>
                                        <span class="fw-semibold">{{ formatCurrency(invoiceTotals.totalItemsNet) }}</span>
                                    </div>

                                    <div class="py-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0 small text-muted fw-semibold">Diskon Faktur:</label>
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
                                            >
                                            <input 
                                                v-else 
                                                type="number" 
                                                step="any" 
                                                class="form-control" 
                                                v-model.number="form.discount_amount"
                                            >
                                        </div>
                                    </div>

                                    <div class="py-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0 small text-muted fw-semibold">Pajak Faktur (PPN):</label>
                                            <span class="text-primary small fw-semibold">+ {{ formatCurrency(invoiceTotals.taxAmount) }}</span>
                                        </div>
                                        <select class="form-select form-select-sm mb-1" v-model="form.tax_type">
                                            <option value="none">Tanpa Pajak (Non PPN)</option>
                                            <option value="ppn_11">PPN 11%</option>
                                            <option value="ppn_12">PPN 12%</option>
                                            <option value="manual">Nominal Pajak Manual</option>
                                        </select>
                                    </div>

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
                                        >
                                    </div>

                                    <div class="py-3 border-bottom bg-light bg-opacity-50 px-2 rounded-2 my-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark">TOTAL AKHIR FAKTUR:</span>
                                            <h4 class="fw-bold text-primary mb-0">{{ formatCurrency(invoiceTotals.grandTotal) }}</h4>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between py-1 small">
                                        <span class="text-muted">Jumlah Dibayar:</span>
                                        <span class="text-success fw-semibold">{{ formatCurrency(invoiceTotals.paidAmount) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 small border-bottom mb-3">
                                        <span class="text-muted">Sisa Hutang ke PBF:</span>
                                        <span class="text-danger fw-bold">{{ formatCurrency(invoiceTotals.remainingAmount) }}</span>
                                    </div>

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
                                            @click="updateDraft" 
                                            :disabled="form.processing"
                                        >
                                            <i class="bx bx-save me-1"></i> Simpan Perubahan Draft
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- PREVIEW MODAL -->
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
                                        Setelah difinalisasi, <strong>stok obat akan otomatis masuk ke outlet</strong> dan kartu stok akan dicatat secara permanen.
                                    </div>
                                </div>

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
                                                <td><span class="badge bg-light text-dark border font-monospace">{{ item.batch_number }}</span></td>
                                                <td>{{ formatDate(item.expired_date) }}</td>
                                                <td class="text-center">{{ item.quantity }} {{ item.unit_name }}</td>
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
                            </div>
                            <div class="modal-footer bg-light py-2">
                                <button type="button" class="btn btn-secondary" @click="showPreviewModal = false">
                                    <i class="bx bx-edit me-1"></i> Batal & Edit Lagi
                                </button>
                                <button type="button" class="btn btn-primary fw-bold px-4" @click="executeFinalize" :disabled="form.processing">
                                    <i class="bx bx-check-double me-1"></i> Ya, Finalisasi Sekarang
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
/* High contrast styles for Edit Invoice Form */
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
