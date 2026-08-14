<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    po: Object,
    items: Array,
    receipts: Array,
});

const showPrintModal = ref(false);

const hasReceivedItems = computed(() => {
    return (props.receipts && props.receipts.length > 0) || (props.items && props.items.some(i => Number(i.received_quantity) > 0));
});

const totalOrderedQty = computed(() => {
    return (props.items || []).reduce((sum, item) => sum + (Number(item.order_quantity) || 0), 0);
});

const totalReceivedQty = computed(() => {
    return (props.items || []).reduce((sum, item) => sum + (Number(item.received_quantity) || 0), 0);
});

const overallReceiveProgress = computed(() => {
    if (totalOrderedQty.value === 0) return 0;
    return Math.min(100, Math.round((totalReceivedQty.value / totalOrderedQty.value) * 100));
});

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const formatNumber = (val) => {
    return new Intl.NumberFormat('id-ID').format(val || 0);
};

const getStatusBadgeClass = (status) => {
    const st = (status || '').toUpperCase();
    switch(st) {
        case 'DRAFT': return 'bg-secondary text-white';
        case 'WAITING_APPROVAL': return 'bg-warning text-dark';
        case 'APPROVED':
        case 'SENT':
        case 'CONFIRMED': return 'bg-info text-dark';
        case 'PARTIAL_RECEIVED': return 'bg-warning text-dark';
        case 'RECEIVED': return 'bg-success text-white';
        case 'CANCELLED':
        case 'REJECTED': return 'bg-danger text-white';
        default: return 'bg-secondary text-white';
    }
};

const approvePO = () => {
    Swal.fire({
        title: 'Setujui Purchase Order ini?',
        text: `Dokumen ${props.po.po_number} akan disetujui dan siap dikirimkan ke supplier PBF.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Setujui!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b'
    }).then((res) => {
        if (res.isConfirmed) {
            router.post(route('purchases.po.approve', props.po.id));
        }
    });
};

const cancelPO = () => {
    Swal.fire({
        title: 'Batalkan Purchase Order ini?',
        text: `Status PO ${props.po.po_number} akan diubah menjadi CANCELLED.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Batalkan PO!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b'
    }).then((res) => {
        if (res.isConfirmed) {
            router.post(route('purchases.po.cancel', props.po.id));
        }
    });
};

const printInvoice = () => {
    window.print();
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">

                <!-- Header Actions Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <Link :href="route('purchases.po.index')" class="btn btn-outline-secondary btn-sm shadow-xs">
                            <i class="bx bx-arrow-back me-1"></i> Kembali
                        </Link>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h3 class="fw-bold text-dark mb-0 font-monospace">{{ po.po_number }}</h3>
                                <span :class="['badge fs-6 px-3 py-1', getStatusBadgeClass(po.status)]">
                                    {{ po.status }}
                                </span>
                            </div>
                            <small class="text-muted">Dibuat tanggal {{ formatDate(po.order_date) }} oleh <strong>{{ po.created_by_name }}</strong></small>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button @click="showPrintModal = true" class="btn btn-outline-primary fw-semibold shadow-xs">
                            <i class="bx bx-printer me-1"></i> Cetak PO / Invoice
                        </button>

                        <button v-if="po.status === 'WAITING_APPROVAL'" @click="approvePO" class="btn btn-success fw-bold shadow-sm">
                            <i class="bx bx-check-shield me-1"></i> Setujui PO
                        </button>

                        <Link v-if="po.status === 'APPROVED' || po.status === 'SENT' || po.status === 'PARTIAL_RECEIVED'"
                            :href="route('purchases.po.receive', po.id)" class="btn btn-success fw-bold shadow-sm">
                            <i class="bx bx-package me-1"></i> Terima Barang Fisik
                        </Link>

                        <button v-if="po.status === 'DRAFT' || po.status === 'WAITING_APPROVAL'" @click="cancelPO" class="btn btn-outline-danger fw-semibold">
                            <i class="bx bx-x-circle me-1"></i> Batalkan PO
                        </button>
                    </div>
                </div>

                <!-- Info Grid (4 Cards) -->
                <div class="row g-3 mb-4">
                    <!-- Supplier Info -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100 p-3" style="border-radius: 12px; border-left: 4px solid #3b6bff !important;">
                            <small class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size: 0.75rem; letter-spacing: 0.05em;">PBF / Supplier</small>
                            <h5 class="fw-bold text-dark mb-1">{{ po.supplier_name }}</h5>
                            <div class="small text-muted font-monospace mb-1">Kode: {{ po.supplier_code || '-' }}</div>
                            <div class="small text-muted"><i class="bx bx-phone me-1"></i>{{ po.supplier_phone || '-' }}</div>
                            <div class="small text-muted"><i class="bx bx-map me-1"></i>{{ po.supplier_address || '-' }}</div>
                        </div>
                    </div>

                    <!-- Date & Terms -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100 p-3" style="border-radius: 12px; border-left: 4px solid #f59e0b !important;">
                            <small class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size: 0.75rem; letter-spacing: 0.05em;">Term & Tanggal</small>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Tgl Order:</span>
                                <strong class="text-dark small">{{ formatDate(po.order_date) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Estimasi Tiba:</span>
                                <strong class="text-dark small">{{ formatDate(po.expected_delivery_date) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Jatuh Tempo:</span>
                                <strong class="text-danger small">{{ formatDate(po.due_date) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">Syarat Bayar:</span>
                                <span class="badge bg-secondary">{{ po.payment_term_days || 30 }} Hari</span>
                            </div>
                        </div>
                    </div>

                    <!-- Authorization & Warehouse -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100 p-3" style="border-radius: 12px; border-left: 4px solid #10b981 !important;">
                            <small class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size: 0.75rem; letter-spacing: 0.05em;">Lokasi & Otorisasi</small>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Gudang:</span>
                                <strong class="text-dark small">{{ po.warehouse_name || 'Gudang Utama' }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Dibuat Oleh:</span>
                                <strong class="text-dark small">{{ po.created_by_name || '-' }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">Disetujui Oleh:</span>
                                <strong class="text-success small">{{ po.approved_by_name || '-' }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Grand Total Summary -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100 p-3" style="border-radius: 12px; border-left: 4px solid #6366f1 !important;">
                            <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.75rem; letter-spacing: 0.05em;">Total Nilai PO</small>
                            <div class="fw-extrabold text-primary mb-1 font-monospace" style="font-size: 1.6rem;">
                                {{ formatCurrency(po.grand_total) }}
                            </div>
                            <div class="small text-muted d-flex justify-content-between">
                                <span>Subtotal:</span> <strong>{{ formatCurrency(po.subtotal) }}</strong>
                            </div>
                            <div class="small text-muted d-flex justify-content-between">
                                <span>PPN ({{ po.tax_percent || 11 }}%):</span> <strong>+{{ formatCurrency(po.tax_amount) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Table Card -->
                <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bx bx-capsule text-primary me-2"></i>Daftar Item Obat yang Dipesan
                        </h5>
                        <span class="badge bg-primary fs-6">{{ items.length }} Item Obat</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th class="text-center" style="width: 50px;">#</th>
                                    <th>Kode Obat</th>
                                    <th>Nama Produk / Obat</th>
                                    <th class="text-center">Satuan</th>
                                    <th class="text-center">Qty Pesan</th>
                                    <th class="text-center">Qty Bonus</th>
                                    <th class="text-end">Harga Satuan (Rp)</th>
                                    <th class="text-end">Diskon (Rp)</th>
                                    <th class="text-end">Subtotal (Rp)</th>
                                    <th class="text-center">Diterima</th>
                                    <th class="text-center">Sisa Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in items" :key="item.id">
                                    <td class="text-center text-muted">{{ idx + 1 }}</td>
                                    <td class="font-monospace fw-bold text-secondary">{{ item.medicine_id }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ item.medicine_name }}</div>
                                        <small class="text-muted">{{ item.medicine_category || '-' }}</small>
                                    </td>
                                    <td class="text-center"><span class="badge bg-light text-dark border">{{ item.medicine_unit || 'PCS' }}</span></td>
                                    <td class="text-center fw-bold fs-6">{{ formatNumber(item.order_quantity) }}</td>
                                    <td class="text-center text-muted">{{ formatNumber(item.bonus_quantity) }}</td>
                                    <td class="text-end font-monospace">{{ formatCurrency(item.unit_price) }}</td>
                                    <td class="text-end text-muted font-monospace">{{ formatCurrency(item.discount_amount) }}</td>
                                    <td class="text-end fw-bold font-monospace text-primary">{{ formatCurrency(item.subtotal) }}</td>
                                    <td class="text-center">
                                        <span class="badge" :class="item.received_quantity >= item.order_quantity ? 'bg-success' : (item.received_quantity > 0 ? 'bg-warning text-dark' : 'bg-secondary')">
                                            {{ item.received_quantity }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge" :class="item.outstanding_quantity > 0 ? 'bg-danger' : 'bg-light text-dark border'">
                                            {{ item.outstanding_quantity }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Financial Breakdown Footer -->
                    <div class="row justify-content-end mt-4 pt-3 border-top">
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted">Subtotal Item:</span>
                                <span class="fw-bold text-dark font-monospace">{{ formatCurrency(po.subtotal) }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1" v-if="po.discount_amount > 0">
                                <span class="text-muted">Diskon PO:</span>
                                <span class="fw-bold text-danger font-monospace">-{{ formatCurrency(po.discount_amount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted">PPN ({{ po.tax_percent || 11 }}%):</span>
                                <span class="fw-bold text-dark font-monospace">+{{ formatCurrency(po.tax_amount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1" v-if="po.shipping_cost > 0">
                                <span class="text-muted">Ongkos Kirim:</span>
                                <span class="fw-bold text-dark font-monospace">+{{ formatCurrency(po.shipping_cost) }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-top border-2 mt-2">
                                <strong class="fs-5 text-dark">GRAND TOTAL:</strong>
                                <strong class="fs-4 text-primary font-monospace">{{ formatCurrency(po.grand_total) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Goods Receipt Log History & Received Items Summary -->
                <div v-if="hasReceivedItems" class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                        <div>
                            <h5 class="fw-bold text-dark mb-1">
                                <i class="bx bx-history text-success me-2"></i>Riwayat & Rekapitulasi Barang yang Diterima
                            </h5>
                            <p class="text-muted small mb-0">Rincian seluruh penerimaan barang fisik, nomor batch, dan tanggal kadaluarsa dari supplier.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 bg-light px-3 py-2 rounded-3 border">
                            <span class="small fw-semibold text-muted">Total Progress Penerimaan:</span>
                            <div class="progress" style="width: 100px; height: 8px; border-radius: 99px;">
                                <div class="progress-bar bg-success" :style="`width: ${overallReceiveProgress}%`"></div>
                            </div>
                            <strong class="text-success small font-monospace">{{ overallReceiveProgress }}% ({{ formatNumber(totalReceivedQty) }}/{{ formatNumber(totalOrderedQty) }} pcs)</strong>
                        </div>
                    </div>

                    <!-- Ringkasan Akumulasi Barang Diterima -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary mb-2 fs-6">
                            <i class="bx bx-check-double text-primary me-1"></i>Ringkasan Status Penerimaan Per Obat
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead class="bg-light text-dark">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th>Kode & Nama Obat</th>
                                        <th class="text-center">Satuan</th>
                                        <th class="text-center">Qty Dipesan</th>
                                        <th class="text-center">Qty Diterima</th>
                                        <th class="text-center">Sisa Outstanding</th>
                                        <th class="text-center">Status Penerimaan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(it, idx) in items" :key="it.id">
                                        <td class="text-center text-muted">{{ idx + 1 }}</td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ it.medicine_name }}</div>
                                            <small class="text-muted font-monospace">{{ it.medicine_id }}</small>
                                        </td>
                                        <td class="text-center"><span class="badge bg-light text-dark border">{{ it.medicine_unit || 'PCS' }}</span></td>
                                        <td class="text-center fw-bold">{{ formatNumber(it.order_quantity) }}</td>
                                        <td class="text-center">
                                            <span class="fw-bold text-success font-monospace fs-6">+{{ formatNumber(it.received_quantity) }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="font-monospace" :class="it.outstanding_quantity > 0 ? 'text-danger fw-bold' : 'text-muted'">
                                                {{ formatNumber(it.outstanding_quantity) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span v-if="it.received_quantity >= it.order_quantity" class="badge bg-success px-3 py-1">
                                                <i class="bx bx-check-circle me-1"></i>Diterima Lengkap
                                            </span>
                                            <span v-else-if="it.received_quantity > 0" class="badge bg-warning text-dark px-3 py-1">
                                                <i class="bx bx-time-five me-1"></i>Diterima Sebagian
                                            </span>
                                            <span v-else class="badge bg-secondary px-3 py-1 opacity-75">
                                                <i class="bx bx-minus-circle me-1"></i>Belum Diterima
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Sesi Goods Receipt & Detail Batch (Jika Ada) -->
                    <div v-if="receipts && receipts.length > 0">
                        <h6 class="fw-bold text-secondary mb-2 fs-6">
                            <i class="bx bx-receipt text-success me-1"></i>Rincian Sesi Penerimaan (Goods Receipt Logs & Batch)
                        </h6>
                        <div v-for="rec in receipts" :key="rec.id" class="border rounded-3 p-3 mb-3 bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div>
                                    <strong class="text-success font-monospace me-3 fs-6"><i class="bx bx-receipt me-1"></i>{{ rec.receipt_number }}</strong>
                                    <span class="text-muted me-3 small">Faktur Supplier: <strong class="text-dark">{{ rec.supplier_invoice_number }}</strong></span>
                                    <span class="text-muted small">Waktu Diterima: <strong>{{ formatDate(rec.received_date) }}</strong> oleh <strong>{{ rec.received_by_name }}</strong></span>
                                </div>
                            </div>

                            <table class="table table-sm table-bordered bg-white mb-0 mt-2 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Obat</th>
                                        <th>No. Batch</th>
                                        <th>Tanggal Expired</th>
                                        <th class="text-center">Harga Satuan</th>
                                        <th class="text-center">Qty Diterima</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="ri in rec.items" :key="ri.id">
                                        <td class="fw-semibold text-dark">{{ ri.medicine_name }}</td>
                                        <td class="font-monospace fw-bold text-primary">{{ ri.batch_number }}</td>
                                        <td>{{ formatDate(ri.expired_date) }}</td>
                                        <td class="text-center font-monospace small">{{ formatCurrency(ri.unit_cost) }}</td>
                                        <td class="text-center fw-bold text-success font-monospace fs-6">+{{ formatNumber(ri.received_quantity) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ===== PRINTABLE DOCUMENT MODAL ===== -->
        <div v-if="showPrintModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.65);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bx bx-printer me-2"></i>Cetak Purchase Order</h5>
                        <button type="button" class="btn-close btn-close-white" @click="showPrintModal = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div id="printableReceipt" class="p-4 border bg-white text-dark">
                            <!-- Printable Header -->
                            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <img v-if="$page.props.app_settings?.pharmacy_logo" :src="$page.props.app_settings.pharmacy_logo" alt="Logo Apotek" style="max-height: 48px; object-fit: contain;">
                                    <div>
                                        <h4 class="fw-bold mb-0 text-primary">{{ $page.props.app_settings?.pharmacy_name || 'APOTEK MEDIKA SORA' }}</h4>
                                        <p class="small text-muted mb-0">{{ $page.props.app_settings?.pharmacy_address || 'Jl. Raya Farmasi No. 10' }}</p>
                                        <p class="small text-muted mb-0">Telp: {{ $page.props.app_settings?.pharmacy_phone || '021-5551234' }}</p>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <h4 class="fw-bold font-monospace mb-0 text-dark">PURCHASE ORDER</h4>
                                    <strong class="text-primary font-monospace fs-5 d-block">{{ po.po_number }}</strong>
                                    <small class="text-muted">Tanggal: {{ formatDate(po.order_date) }}</small>
                                </div>
                            </div>

                            <!-- Printable Grid -->
                            <div class="row mb-3 small">
                                <div class="col-6">
                                    <strong class="d-block text-uppercase text-muted">Kepada Supplier PBF:</strong>
                                    <div class="fw-bold text-dark fs-6">{{ po.supplier_name }}</div>
                                    <div>Alamat: {{ po.supplier_address || '-' }}</div>
                                    <div>Telp: {{ po.supplier_phone || '-' }}</div>
                                </div>
                                <div class="col-6 text-end">
                                    <strong class="d-block text-uppercase text-muted">Ketentuan Pengiriman:</strong>
                                    <div>Gudang Tujuan: <strong>{{ po.warehouse_name || 'Gudang Utama' }}</strong></div>
                                    <div>Jatuh Tempo: <strong>{{ formatDate(po.due_date) }}</strong></div>
                                    <div>Term Pembayaran: <strong>{{ po.payment_term_days || 30 }} Hari</strong></div>
                                </div>
                            </div>

                            <!-- Printable Table -->
                            <table class="table table-bordered table-sm small mb-3">
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Kode</th>
                                        <th>Nama Obat / Produk</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Harga Satuan</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(it, i) in items" :key="it.id">
                                        <td class="text-center">{{ i + 1 }}</td>
                                        <td class="font-monospace">{{ it.medicine_id }}</td>
                                        <td>{{ it.medicine_name }}</td>
                                        <td class="text-center fw-bold">{{ formatNumber(it.order_quantity) }} {{ it.medicine_unit }}</td>
                                        <td class="text-end font-monospace">{{ formatCurrency(it.unit_price) }}</td>
                                        <td class="text-end font-monospace fw-bold">{{ formatCurrency(it.subtotal) }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Printable Total -->
                            <div class="row justify-content-end mb-4">
                                <div class="col-5">
                                    <div class="d-flex justify-content-between small">
                                        <span>Subtotal:</span> <strong>{{ formatCurrency(po.subtotal) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between small" v-if="po.discount_amount > 0">
                                        <span>Diskon:</span> <strong>-{{ formatCurrency(po.discount_amount) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between small">
                                        <span>PPN ({{ po.tax_percent || 11 }}%):</span> <strong>+{{ formatCurrency(po.tax_amount) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between fw-bold fs-6 border-top pt-1 mt-1">
                                        <span>Grand Total:</span> <span class="text-primary font-monospace">{{ formatCurrency(po.grand_total) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Printable Signatures -->
                            <div class="row text-center mt-5 pt-3 small">
                                <div class="col-4">
                                    <p class="mb-5">Dibuat Oleh,</p>
                                    <strong class="border-top pt-1 d-block">{{ po.created_by_name }}</strong>
                                </div>
                                <div class="col-4">
                                    <p class="mb-5">Disetujui Oleh,</p>
                                    <strong class="border-top pt-1 d-block">{{ po.approved_by_name || 'Apoteker / Manager' }}</strong>
                                </div>
                                <div class="col-4">
                                    <p class="mb-5">Konfirmasi PBF / Supplier,</p>
                                    <strong class="border-top pt-1 d-block">{{ po.supplier_name }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="showPrintModal = false">Tutup</button>
                        <button type="button" class="btn btn-primary" @click="printInvoice"><i class="bx bx-printer me-1"></i> Cetak Sekarang</button>
                    </div>
                </div>
            </div>
        </div>

    </LegacyLayout>
</template>
