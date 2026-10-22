<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    po: Object,
    items: Array,
    receipts: Array,
    invoices: Array,
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
        text: `Dokumen ${props.po.po_number} akan disetujui (Approved) sebagai Surat Pesanan resmi. Catatan: Persetujuan PO ini TIDAK mengubah stok fisik obat.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Setujui PO',
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
        <section class="mt-4 me-4 ms-4 po-detail-module">
            <div class="content mt-4">

                <!-- Header Actions Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <Link :href="route('purchases.po.index')" class="btn btn-outline-secondary btn-sm shadow-xs text-slate-200">
                            <i class="bx bx-arrow-back me-1"></i> Kembali ke Daftar PO
                        </Link>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h3 class="fw-bold page-po-number mb-0 font-monospace">{{ po.po_number }}</h3>
                                <span :class="['badge fs-6 px-3 py-1', getStatusBadgeClass(po.status)]">
                                    {{ po.status }}
                                </span>
                            </div>
                            <small class="text-slate-400">Dibuat tanggal {{ formatDate(po.order_date) }} oleh <strong>{{ po.created_by_name }}</strong></small>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button @click="showPrintModal = true" class="btn btn-outline-primary fw-semibold shadow-xs">
                            <i class="bx bx-printer me-1"></i> Cetak Surat Pesanan (PO)
                        </button>

                        <button v-if="po.status === 'WAITING_APPROVAL'" @click="approvePO" class="btn btn-success fw-bold shadow-sm">
                            <i class="bx bx-check-shield me-1"></i> Setujui PO
                        </button>

                        <!-- Action: Proses ke Faktur Pembelian -->
                        <Link v-if="po.status === 'APPROVED' || po.status === 'SENT' || po.status === 'PARTIAL_RECEIVED' || po.status === 'RECEIVED'"
                            :href="route('purchases.invoices.create', { po_id: po.id })" class="btn btn-emerald fw-bold shadow-sm">
                            <i class="bx bx-receipt me-1"></i> Buat Faktur Pembelian (Input Batch & Stok)
                        </Link>

                        <button v-if="po.status === 'DRAFT' || po.status === 'WAITING_APPROVAL'" @click="cancelPO" class="btn btn-outline-danger fw-semibold">
                            <i class="bx bx-x-circle me-1"></i> Batalkan PO
                        </button>
                    </div>
                </div>

                <!-- ALERT NOTICE: PO TIDAK MEMPENGARUHI STOK -->
                <div class="alert-po-notice mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="notice-icon">
                            <i class="bx bx-shield-quarter"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                <h6 class="fw-bold text-white mb-0">Catatan Khusus: PO Ini Berfungsi Sebagai Surat Pesanan (SP)</h6>
                                <span class="badge bg-warning text-dark fw-bold">TIDAK MENAMBAH STOK</span>
                            </div>
                            <p class="text-slate-200 small mb-2">
                                Dokumen Purchase Order ini murni untuk pendataan pesanan resmi ke supplier PBF dan <strong>TIDAK menambah persediaan stok fisik obat</strong> di apotek saat dibuat maupun disetujui (final).
                                Penambahan stok fisik obat, penerimaan nomor batch, tanggal expired (ED), dan mutasi kartu stok <strong>hanya terjadi saat pengisian & finalisasi di modul Faktur Pembelian</strong>.
                            </p>
                            <div class="d-flex gap-2 flex-wrap">
                                <Link :href="route('purchases.invoices.create', { po_id: po.id })" class="btn btn-xs btn-success text-white fw-bold">
                                    <i class="bx bx-plus-circle me-1"></i> Buat Faktur Pembelian dari PO Ini (Masuk Stok)
                                </Link>
                                <Link :href="route('purchases.invoices.index')" class="btn btn-xs btn-outline-info text-white">
                                    <i class="bx bx-receipt me-1"></i> Buka Daftar Faktur Pembelian
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Grid (4 Cards) -->
                <div class="row g-3 mb-4">
                    <!-- Supplier Info -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100 p-3 info-card" style="border-left: 4px solid #3b6bff !important;">
                            <small class="text-uppercase fw-bold d-block mb-2 text-label">PBF / Supplier</small>
                            <h5 class="fw-bold text-white-bold mb-1">{{ po.supplier_name }}</h5>
                            <div class="small text-slate-300 font-monospace mb-1">Kode: {{ po.supplier_code || '-' }}</div>
                            <div class="small text-slate-300"><i class="bx bx-phone me-1"></i>{{ po.supplier_phone || '-' }}</div>
                            <div class="small text-slate-300"><i class="bx bx-map me-1"></i>{{ po.supplier_address || '-' }}</div>
                        </div>
                    </div>

                    <!-- Date & Terms -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100 p-3 info-card" style="border-left: 4px solid #f59e0b !important;">
                            <small class="text-uppercase fw-bold d-block mb-2 text-label">Term & Tanggal</small>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-slate-400 small">Tgl Order:</span>
                                <strong class="text-white-bold small">{{ formatDate(po.order_date) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-slate-400 small">Estimasi Tiba:</span>
                                <strong class="text-white-bold small">{{ formatDate(po.expected_delivery_date) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-slate-400 small">Jatuh Tempo:</span>
                                <strong class="text-warning small">{{ formatDate(po.due_date) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-slate-400 small">Syarat Bayar:</span>
                                <span class="badge bg-secondary">{{ po.payment_term_days || 30 }} Hari</span>
                            </div>
                        </div>
                    </div>

                    <!-- Authorization & Warehouse -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100 p-3 info-card" style="border-left: 4px solid #10b981 !important;">
                            <small class="text-uppercase fw-bold d-block mb-2 text-label">Lokasi & Otorisasi</small>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-slate-400 small">Gudang:</span>
                                <strong class="text-white-bold small">{{ po.warehouse_name || 'Gudang Utama' }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-slate-400 small">Dibuat Oleh:</span>
                                <strong class="text-white-bold small">{{ po.created_by_name || '-' }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-slate-400 small">Disetujui Oleh:</span>
                                <strong class="text-emerald small">{{ po.approved_by_name || '-' }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Total Quantity Summary -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100 p-3 info-card" style="border-left: 4px solid #6366f1 !important;">
                            <small class="text-uppercase fw-bold d-block mb-1 text-label">Total Kuantitas Pesanan</small>
                            <div class="fw-extrabold text-cyan mb-1 font-monospace" style="font-size: 1.6rem;">
                                {{ formatNumber(totalOrderedQty) }} <span class="fs-6 fw-normal text-slate-300">Unit</span>
                            </div>
                            <div class="small text-slate-400 d-flex justify-content-between">
                                <span>Jumlah Item:</span> <strong class="text-slate-200">{{ items.length }} Obat</strong>
                            </div>
                            <div class="small text-slate-400 d-flex justify-content-between">
                                <span>Harga:</span> <span class="badge bg-secondary-subtle text-slate-300 border border-secondary border-opacity-50">Diinput di Faktur</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAKTUR PEMBELIAN TERKAIT (MASUK STOK) SECTION -->
                <div class="card border-0 shadow-sm p-4 mb-4 detail-card">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h5 class="fw-bold text-white-bold mb-1">
                                <i class="bx bx-receipt text-success me-2"></i>Faktur Pembelian Terkait (Penerimaan Fisik & Penambahan Stok)
                            </h5>
                            <p class="text-slate-400 small mb-0">
                                Penerimaan obat secara fisik dan mutasi stok kartu stok dicatat melalui Faktur Pembelian berikut:
                            </p>
                        </div>

                        <Link :href="route('purchases.invoices.create', { po_id: po.id })" class="btn btn-sm btn-emerald fw-bold">
                            <i class="bx bx-plus-circle me-1"></i> Buat Faktur Pembelian Baru
                        </Link>
                    </div>

                    <div v-if="invoices && invoices.length > 0" class="table-responsive">
                        <table class="table po-items-table align-middle">
                            <thead>
                                <tr>
                                    <th>No. Faktur PBF</th>
                                    <th>No. Penerimaan (GRN)</th>
                                    <th>Tanggal Faktur</th>
                                    <th>Jatuh Tempo</th>
                                    <th class="text-end">Total Nilai (Rp)</th>
                                    <th class="text-center">Status Faktur</th>
                                    <th class="text-center">Status Stok</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="inv in invoices" :key="inv.id">
                                    <td class="fw-bold font-monospace text-cyan">{{ inv.invoice_number || '-' }}</td>
                                    <td class="font-monospace text-slate-300">{{ inv.receipt_number }}</td>
                                    <td class="text-slate-300">{{ formatDate(inv.invoice_date) }}</td>
                                    <td class="text-slate-300">{{ formatDate(inv.due_date) }}</td>
                                    <td class="text-end fw-bold font-monospace text-cyan">{{ formatCurrency(inv.grand_total) }}</td>
                                    <td class="text-center">
                                        <span class="badge" :class="inv.status === 'FINAL' ? 'bg-success' : (inv.status === 'CANCELLED' ? 'bg-danger' : 'bg-warning text-dark')">
                                            {{ inv.status }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span v-if="inv.status === 'FINAL'" class="badge bg-success text-white">
                                            <i class="bx bx-check-circle me-1"></i> Stok Bertambah
                                        </span>
                                        <span v-else-if="inv.status === 'CANCELLED'" class="badge bg-secondary text-white">
                                            <i class="bx bx-x-circle me-1"></i> Dibatalkan
                                        </span>
                                        <span v-else class="badge bg-warning text-dark">
                                            <i class="bx bx-time me-1"></i> Draft (Stok Belum Berubah)
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <Link :href="route('purchases.invoices.show', inv.id)" class="btn btn-sm btn-outline-info text-white">
                                            <i class="bx bx-show me-1"></i> Lihat Faktur
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="alert alert-info py-3 px-3 mb-0 small text-slate-200">
                        <i class="bx bx-info-circle me-2 text-info fs-5 align-middle"></i>
                        Belum ada Faktur Pembelian yang diterbitkan untuk PO ini. Klik tombol <strong>"Buat Faktur Pembelian Baru"</strong> di atas apabila barang fisik telah tiba disertai faktur dari PBF untuk memasukkan nomor batch dan menambahkan stok obat ke apotek.
                    </div>
                </div>

                <!-- Items Table Card -->
                <div class="card border-0 shadow-sm p-4 mb-4 detail-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-white-bold mb-0">
                            <i class="bx bx-capsule text-primary me-2"></i>Daftar Item Obat yang Dipesan
                        </h5>
                        <span class="badge bg-primary fs-6">{{ items.length }} Item Obat</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table po-items-table align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 50px;">#</th>
                                    <th>Kode Obat</th>
                                    <th>Nama Produk / Obat</th>
                                    <th class="text-center">Satuan Pesan</th>
                                    <th class="text-center">Qty Pesan (SP)</th>
                                    <th class="text-center">Qty Diterima</th>
                                    <th class="text-center">Sisa Qty</th>
                                    <th>Catatan / Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in items" :key="item.id">
                                    <td class="text-center text-slate-400">{{ idx + 1 }}</td>
                                    <td class="font-monospace fw-bold text-cyan">{{ item.medicine_id }}</td>
                                    <td>
                                        <div class="fw-bold text-white-bold">{{ item.medicine_name }}</div>
                                        <small class="text-slate-400">{{ item.medicine_category || '-' }}</small>
                                    </td>
                                    <td class="text-center"><span class="badge-unit">{{ item.medicine_unit || 'PCS' }}</span></td>
                                    <td class="text-center fw-bold fs-6 text-white-bold">{{ formatNumber(item.order_quantity) }}</td>
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
                                    <td class="text-slate-300 small">{{ item.notes || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Breakdown & Price Policy Note -->
                    <div class="mt-3 pt-3 border-top border-secondary border-opacity-25">
                        <div v-if="Number(po.grand_total) > 0" class="row justify-content-end">
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-slate-400">Subtotal Item:</span>
                                    <span class="fw-bold text-white-bold font-monospace">{{ formatCurrency(po.subtotal) }}</span>
                                </div>
                                <div class="d-flex justify-content-between py-1" v-if="po.discount_amount > 0">
                                    <span class="text-slate-400">Diskon PO:</span>
                                    <span class="fw-bold text-danger font-monospace">-{{ formatCurrency(po.discount_amount) }}</span>
                                </div>
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-slate-400">PPN ({{ po.tax_percent || 11 }}%):</span>
                                    <span class="fw-bold text-white-bold font-monospace">+{{ formatCurrency(po.tax_amount) }}</span>
                                </div>
                                <div class="d-flex justify-content-between py-2 border-top border-secondary border-opacity-50 mt-2">
                                    <strong class="fs-5 text-white-bold">GRAND TOTAL:</strong>
                                    <strong class="fs-4 text-cyan font-monospace">{{ formatCurrency(po.grand_total) }}</strong>
                                </div>
                            </div>
                        </div>
                        <div v-else class="alert alert-secondary py-2 px-3 small text-slate-300 d-flex align-items-center gap-2 mb-0" style="border-radius: 8px;">
                            <i class="bx bx-info-circle text-info fs-5"></i>
                            <span><strong>Catatan Harga:</strong> Surat Pesanan ini tidak mencantumkan harga karena harga PBF dapat berubah sewaktu-waktu. Penetapan harga beli, diskon, dan PPN (11%) akan dicatat secara detail pada modul <strong>Faktur Pembelian</strong> ketika barang fisik dan faktur tiba.</span>
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
                        <h5 class="modal-title fw-bold"><i class="bx bx-printer me-2"></i>Cetak Surat Pesanan (PO)</h5>
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
                                    <h4 class="fw-bold font-monospace mb-0 text-dark">SURAT PESANAN (PO)</h4>
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

                            <!-- Printable Table Surat Pesanan Standar Farmasi -->
                            <table class="table table-bordered table-sm small mb-4">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width: 40px;">No</th>
                                        <th>Kode</th>
                                        <th>Nama Obat / Produk Farmasi</th>
                                        <th class="text-center">Satuan</th>
                                        <th class="text-center">Jumlah Dipesan (Qty)</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(it, i) in items" :key="it.id">
                                        <td class="text-center">{{ i + 1 }}</td>
                                        <td class="font-monospace">{{ it.medicine_id }}</td>
                                        <td class="fw-bold">{{ it.medicine_name }}</td>
                                        <td class="text-center">{{ it.medicine_unit || 'PCS' }}</td>
                                        <td class="text-center fw-bold fs-6">{{ formatNumber(it.order_quantity) }}</td>
                                        <td>{{ it.notes || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Printable Signatures -->
                            <div class="row text-center mt-5 pt-3 small">
                                <div class="col-4">
                                    <p class="mb-5">Dibuat Oleh,</p>
                                    <strong class="border-top pt-1 d-block">{{ po.created_by_name }}</strong>
                                </div>
                                <div class="col-4">
                                    <p class="mb-5">Disetujui Oleh (Apoteker),</p>
                                    <strong class="border-top pt-1 d-block">{{ po.approved_by_name || 'Apoteker Penanggung Jawab' }}</strong>
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

<style scoped>
/* ============================================================ */
/* ULTRA HIGH CONTRAST DARK MODE THEME FOR PO SHOW              */
/* ============================================================ */
.po-detail-module {
    color: #f8fafc;
}

.page-po-number {
    color: #ffffff !important;
}

:global(body:not(.dark-mode)) .page-po-number {
    color: #0f172a !important;
}

.text-white-bold {
    color: #ffffff !important;
}

:global(body:not(.dark-mode)) .text-white-bold {
    color: #0f172a !important;
}

.text-label {
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    color: #94a3b8 !important;
}

:global(body:not(.dark-mode)) .text-label {
    color: #64748b !important;
}

.text-cyan {
    color: #38bdf8 !important;
}

:global(body:not(.dark-mode)) .text-cyan {
    color: #0284c7 !important;
}

.text-emerald {
    color: #34d399 !important;
}

:global(body:not(.dark-mode)) .text-emerald {
    color: #059669 !important;
}

.btn-emerald {
    background: #10b981;
    color: #ffffff !important;
    border: 1px solid #059669;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
}

.btn-emerald:hover {
    background: #059669;
    color: #ffffff !important;
}

/* ============================================================ */
/* NOTICE BANNER: PO TIDAK MEMPENGARUHI STOK                    */
/* ============================================================ */
.alert-po-notice {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.98));
    border: 1px solid #f59e0b;
    border-left: 6px solid #f59e0b;
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
}

:global(body:not(.dark-mode)) .alert-po-notice {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-left: 6px solid #f59e0b;
}

:global(body:not(.dark-mode)) .alert-po-notice h6 {
    color: #92400e !important;
}

:global(body:not(.dark-mode)) .alert-po-notice p {
    color: #78350f !important;
}

.notice-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: rgba(245, 158, 11, 0.2);
    border: 1px solid rgba(245, 158, 11, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fbbf24;
    font-size: 1.6rem;
    flex-shrink: 0;
}

.btn-xs {
    padding: 4px 10px;
    font-size: 0.78rem;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

/* ============================================================ */
/* CARDS & TABLES                                               */
/* ============================================================ */
.info-card, .detail-card {
    background: #1e293b !important;
    border: 1px solid #334155 !important;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}

:global(body:not(.dark-mode)) .info-card,
:global(body:not(.dark-mode)) .detail-card {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.po-items-table thead th {
    background-color: #0f172a !important;
    color: #f8fafc !important;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px solid #475569 !important;
    padding: 12px 10px !important;
    white-space: nowrap;
}

:global(body:not(.dark-mode)) .po-items-table thead th {
    background-color: #f1f5f9 !important;
    color: #334155 !important;
    border-bottom-color: #cbd5e1 !important;
}

.po-items-table tbody td {
    background-color: #1e293b !important;
    border-bottom: 1px solid #334155 !important;
    padding: 12px 10px !important;
}

:global(body:not(.dark-mode)) .po-items-table tbody td {
    background-color: #ffffff !important;
    border-bottom-color: #f1f5f9 !important;
}

.badge-unit {
    background: rgba(148, 163, 184, 0.2);
    color: #f1f5f9 !important;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 0.75rem;
    font-weight: 600;
}
</style>
