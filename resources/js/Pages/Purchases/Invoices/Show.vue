<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    invoice: Object,
});

const isProcessing = ref(false);

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' +
           d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};

// Finalisasi Faktur
const confirmFinalize = () => {
    Swal.fire({
        title: 'Finalisasi Faktur Pembelian?',
        html: `
            <div class="text-start small">
                <p class="mb-1"><strong>No. Faktur:</strong> ${props.invoice.invoice_number || props.invoice.receipt_number}</p>
                <p class="mb-1"><strong>Supplier:</strong> ${props.invoice.supplier?.name || '-'}</p>
                <p class="mb-1"><strong>Outlet Tujuan:</strong> ${props.invoice.outlet?.name || 'Gudang Pusat'}</p>
                <p class="mb-1"><strong>Total Transaksi:</strong> ${formatCurrency(props.invoice.grand_total)}</p>
                <hr class="my-2"/>
                <div class="alert alert-info py-2 px-3 mb-0 small">
                    <i class="bx bx-check-shield me-1"></i> Setelah difinalisasi, <strong>seluruh obat akan otomatis menambah stok persediaan</strong> di outlet tujuan, mutasi kartu stok dicatat, dan hutang supplier diperbarui.
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bx bx-check-double me-1"></i> Ya, Finalisasi Sekarang',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            isProcessing.value = true;
            router.post(route('purchases.invoices.finalize', props.invoice.id), {}, {
                onFinish: () => { isProcessing.value = false; }
            });
        }
    });
};

// Pembatalan Faktur Final (Reversal Mutasi Stok)
const openCancelModal = () => {
    Swal.fire({
        title: 'Batalkan Faktur Pembelian?',
        input: 'textarea',
        inputLabel: 'Alasan Pembatalan (Wajib Diisi):',
        inputPlaceholder: 'Tuliskan alasan pembatalan faktur secara lengkap...',
        inputAttributes: {
            'aria-label': 'Tuliskan alasan pembatalan faktur'
        },
        html: `
            <div class="text-start small text-danger mb-2">
                <i class="bx bx-error-circle me-1"></i> <strong>Perhatian:</strong> Pembatalan faktur yang sudah FINAL akan <strong>mengurangi kembali stok</strong> yang telah masuk ke outlet melalui transaksi mutasi pembalik (reversal) dan membatalkan catatan hutang.
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Batalkan Faktur',
        cancelButtonText: 'Kembali',
        preConfirm: (reason) => {
            if (!reason || reason.trim().length < 5) {
                Swal.showValidationMessage('Alasan pembatalan minimal harus 5 karakter.');
                return false;
            }
            return reason;
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            isProcessing.value = true;
            router.post(route('purchases.invoices.cancel', props.invoice.id), {
                cancellation_reason: result.value
            }, {
                onFinish: () => { isProcessing.value = false; }
            });
        }
    });
};

// Hapus Draft
const confirmDeleteDraft = () => {
    Swal.fire({
        title: 'Hapus Draft Faktur?',
        text: `Draft faktur ${props.invoice.receipt_number} akan dihapus secara permanen.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus Draft',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('purchases.invoices.destroy', props.invoice.id));
        }
    });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4 pb-5">
            <div class="content mt-4">
                <!-- Top Breadcrumbs & Action Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <Link :href="route('purchases.invoices.index')" class="text-decoration-none text-muted small">
                                <i class="bx bx-left-arrow-alt"></i> Kembali ke Daftar Faktur
                            </Link>
                            <span class="text-muted">/</span>
                            <span class="font-monospace text-primary fw-semibold">{{ invoice.receipt_number }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <h2 class="fw-bold text-dark mb-0">
                                Faktur: {{ invoice.invoice_number || invoice.receipt_number }}
                            </h2>
                            <!-- Status Badges -->
                            <span v-if="invoice.status === 'FINAL'" class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
                                <i class="bx bx-check-double me-1"></i> FINAL (STOK MASUK)
                            </span>
                            <span v-else-if="invoice.status === 'DRAFT'" class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 fs-6">
                                <i class="bx bx-time-five me-1"></i> DRAFT (BELUM STOK)
                            </span>
                            <span v-else-if="invoice.status === 'CANCELLED'" class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-6">
                                <i class="bx bx-x-circle me-1"></i> DIBATALKAN
                            </span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-2 flex-wrap">
                        <!-- Action for DRAFT -->
                        <template v-if="invoice.status === 'DRAFT'">
                            <Link :href="route('purchases.invoices.edit', invoice.id)" class="btn btn-outline-primary fw-semibold">
                                <i class="bx bx-edit me-1"></i> Edit Draft
                            </Link>
                            <button 
                                type="button" 
                                class="btn btn-success fw-bold shadow-sm px-3" 
                                @click="confirmFinalize" 
                                :disabled="isProcessing"
                            >
                                <i class="bx bx-check-double me-1"></i> Finalisasi Faktur
                            </button>
                            <button 
                                type="button" 
                                class="btn btn-outline-danger" 
                                @click="confirmDeleteDraft"
                            >
                                <i class="bx bx-trash me-1"></i> Hapus Draft
                            </button>
                        </template>

                        <!-- Action for FINAL -->
                        <template v-if="invoice.status === 'FINAL'">
                            <a 
                                :href="route('purchases.invoices.print', invoice.id) + '?type=invoice'" 
                                target="_blank" 
                                class="btn btn-outline-dark fw-semibold"
                            >
                                <i class="bx bx-printer me-1"></i> Cetak Faktur
                            </a>
                            <a 
                                :href="route('purchases.invoices.print', invoice.id) + '?type=receiving_slip'" 
                                target="_blank" 
                                class="btn btn-outline-primary fw-semibold"
                            >
                                <i class="bx bx-package me-1"></i> Cetak Bukti Terima Barang
                            </a>
                            <button 
                                type="button" 
                                class="btn btn-outline-danger fw-semibold" 
                                @click="openCancelModal" 
                                :disabled="isProcessing"
                            >
                                <i class="bx bx-x me-1"></i> Batalkan Faktur
                            </button>
                        </template>

                        <!-- Print for CANCELLED -->
                        <template v-if="invoice.status === 'CANCELLED'">
                            <a 
                                :href="route('purchases.invoices.print', invoice.id) + '?type=receiving_slip'" 
                                target="_blank" 
                                class="btn btn-outline-secondary"
                            >
                                <i class="bx bx-printer me-1"></i> Cetak Bukti Transaksi
                            </a>
                        </template>
                    </div>
                </div>

                <!-- Alert Batal jika status CANCELLED -->
                <div v-if="invoice.status === 'CANCELLED'" class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 p-3 d-flex align-items-center">
                    <i class="bx bx-error fs-2 me-3 text-danger"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Faktur Ini Telah Dibatalkan</h6>
                        <p class="mb-0 small">
                            <strong>Alasan:</strong> {{ invoice.cancellation_reason || 'Tidak ada alasan' }} | 
                            <strong>Dibatalkan pada:</strong> {{ formatDateTime(invoice.cancelled_at) }} 
                            <span v-if="invoice.cancelled_by">oleh <strong>{{ invoice.cancelled_by?.name || 'Petugas' }}</strong></span>. 
                            Transaksi mutasi stok pembalik (reversal) telah dieksekusi secara otomatis.
                        </p>
                    </div>
                </div>

                <!-- Main Details Layout -->
                <div class="row g-4">
                    <!-- Left Column: Details -->
                    <div class="col-12 col-xl-8">
                        <!-- Invoice Header Info Card -->
                        <div class="card bg-white border-0 shadow-sm rounded-3 mb-4">
                            <div class="card-header bg-light bg-opacity-75 py-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                                    <i class="bx bx-info-circle text-primary me-2 fs-5"></i>
                                    Informasi Transaksi & PBF
                                </h6>
                                <span class="badge bg-light text-dark border font-monospace">
                                    {{ invoice.receipt_number }}
                                </span>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <!-- Supplier Info -->
                                    <div class="col-12 col-md-6 border-end">
                                        <small class="text-muted fw-semibold text-uppercase" style="font-size: 11px;">Identitas Supplier (PBF)</small>
                                        <h5 class="fw-bold text-dark mt-1 mb-1">{{ invoice.supplier?.name || '-' }}</h5>
                                        <div class="small text-muted mb-1">
                                            <i class="bx bx-barcode me-1"></i>Kode: <strong>{{ invoice.supplier?.code || '-' }}</strong>
                                        </div>
                                        <div class="small text-muted mb-1">
                                            <i class="bx bx-phone me-1"></i>Telepon: {{ invoice.supplier?.phone || '-' }}
                                        </div>
                                        <div class="small text-muted">
                                            <i class="bx bx-map me-1"></i>Alamat: {{ invoice.supplier?.address || '-' }}
                                        </div>
                                    </div>

                                    <!-- Outlet & Destination Info -->
                                    <div class="col-12 col-md-6 ps-md-4">
                                        <small class="text-muted fw-semibold text-uppercase" style="font-size: 11px;">Lokasi & Outlet Masuk Stok</small>
                                        <h5 class="fw-bold text-primary mt-1 mb-1">
                                            <i class="bx bx-store-alt me-1"></i>{{ invoice.outlet?.name || 'Gudang Pusat' }}
                                        </h5>
                                        <div class="small text-muted mb-1">
                                            Kode Outlet: <strong>{{ invoice.outlet?.code || 'GUDANG' }}</strong>
                                        </div>
                                        <div class="small text-muted mb-1" v-if="invoice.delivery_order_number">
                                            <i class="bx bx-car me-1"></i>No. Surat Jalan: <strong>{{ invoice.delivery_order_number }}</strong>
                                        </div>
                                        <div class="small text-muted" v-if="invoice.purchase_order">
                                            <i class="bx bx-cart-download me-1"></i>Ref. PO: 
                                            <Link :href="route('purchases.po.show', invoice.purchase_order_id)" class="text-primary fw-semibold">
                                                {{ invoice.purchase_order.po_number }}
                                            </Link>
                                        </div>
                                    </div>

                                    <div class="col-12"><hr class="my-1"/></div>

                                    <!-- Dates & Payments Row -->
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Tanggal Faktur</small>
                                        <strong class="text-dark">{{ formatDate(invoice.purchase_date) }}</strong>
                                    </div>

                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Tanggal Terima Barang</small>
                                        <strong class="text-primary">{{ formatDate(invoice.received_date) }}</strong>
                                    </div>

                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Jatuh Tempo Pembayaran</small>
                                        <strong class="text-dark">{{ formatDate(invoice.due_date) }}</strong>
                                    </div>

                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Jenis Transaksi</small>
                                        <span class="badge bg-secondary text-uppercase">{{ invoice.payment_type || 'KREDIT' }}</span>
                                    </div>

                                    <div class="col-12" v-if="invoice.notes">
                                        <div class="alert alert-light border py-2 px-3 mb-0 small">
                                            <strong>Catatan:</strong> {{ invoice.notes }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Medicines Items Detail Table Card -->
                        <div class="card bg-white border-0 shadow-sm rounded-3">
                            <div class="card-header bg-light bg-opacity-75 py-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                                    <i class="bx bx-capsule text-primary me-2 fs-5"></i>
                                    Rincian Obat & Konversi Satuan Masuk Stok
                                </h6>
                                <span class="badge bg-primary px-3 py-2">
                                    {{ invoice.items?.length || 0 }} Item Obat
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light small text-secondary text-uppercase" style="font-size: 11px;">
                                            <tr>
                                                <th class="ps-3" style="width: 35px;">#</th>
                                                <th>Nama & Kode Obat</th>
                                                <th>No. Batch</th>
                                                <th>Expired Date</th>
                                                <th class="text-center">Kuantitas Beli</th>
                                                <th class="text-center bg-primary-subtle text-primary">Stok Dasar Masuk</th>
                                                <th class="text-end">Harga Satuan</th>
                                                <th class="text-center">Diskon</th>
                                                <th class="text-end pe-3">Subtotal Bersih</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, idx) in invoice.items" :key="item.id">
                                                <td class="ps-3 text-muted small">{{ idx + 1 }}</td>
                                                <td>
                                                    <div class="fw-bold text-dark">{{ item.medicine?.nama || item.medicine_id }}</div>
                                                    <div class="small text-muted font-monospace" style="font-size: 11px;">
                                                        {{ item.medicine_id }}
                                                        <span v-if="item.medicine?.barcode" class="badge bg-light text-muted border ms-1">
                                                            {{ item.medicine.barcode }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                                        {{ item.batch_number }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="small fw-semibold text-dark">{{ formatDate(item.expired_date) }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="fw-bold text-dark">{{ item.quantity }}</span> 
                                                    <span class="small text-muted ms-1">{{ item.unit_name || 'Unit' }}</span>
                                                </td>
                                                <td class="text-center bg-primary-subtle bg-opacity-25">
                                                    <span class="badge bg-primary text-white px-2 py-1">
                                                        +{{ item.quantity_base }} {{ item.medicine?.satuanDasar?.name || 'Stok' }}
                                                    </span>
                                                    <div class="text-muted" style="font-size: 10px;">
                                                        Konversi: 1 {{ item.unit_name }} = {{ item.conversion_factor }}
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    {{ formatCurrency(item.unit_price) }}
                                                </td>
                                                <td class="text-center">
                                                    <span v-if="item.discount_percent > 0" class="badge bg-warning-subtle text-warning">
                                                        {{ item.discount_percent }}%
                                                    </span>
                                                    <span v-else-if="item.discount_amount > 0" class="text-danger small">
                                                        -{{ formatCurrency(item.discount_amount) }}
                                                    </span>
                                                    <span v-else class="text-muted">-</span>
                                                </td>
                                                <td class="text-end pe-3 fw-bold text-dark">
                                                    {{ formatCurrency(item.subtotal_net || item.subtotal) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Financial & Audit Log Summary -->
                    <div class="col-12 col-xl-4">
                        <!-- Financial Summary Card -->
                        <div class="card bg-white border-0 shadow-sm rounded-3 mb-4">
                            <div class="card-header bg-light bg-opacity-75 py-3 border-bottom">
                                <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                                    <i class="bx bx-receipt text-primary me-2 fs-5"></i>
                                    Perhitungan Nilai Faktur
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between py-1 border-bottom small">
                                    <span class="text-muted">Subtotal Bruto Item:</span>
                                    <span class="fw-semibold">{{ formatCurrency(invoice.subtotal) }}</span>
                                </div>

                                <div class="d-flex justify-content-between py-1 border-bottom small" v-if="invoice.discount_amount > 0">
                                    <span class="text-muted">
                                        Diskon Faktur 
                                        <span v-if="invoice.discount_rate > 0">({{ invoice.discount_rate }}%)</span>:
                                    </span>
                                    <span class="text-danger fw-semibold">- {{ formatCurrency(invoice.discount_amount) }}</span>
                                </div>

                                <div class="d-flex justify-content-between py-1 border-bottom small" v-if="invoice.tax_amount > 0">
                                    <span class="text-muted">Pajak Faktur (PPN):</span>
                                    <span class="text-primary fw-semibold">+ {{ formatCurrency(invoice.tax_amount) }}</span>
                                </div>

                                <div class="d-flex justify-content-between py-1 border-bottom small" v-if="invoice.shipping_cost > 0">
                                    <span class="text-muted">Biaya Tambahan / Ongkir:</span>
                                    <span class="fw-semibold">+ {{ formatCurrency(invoice.shipping_cost) }}</span>
                                </div>

                                <div class="py-3 border-bottom bg-light bg-opacity-50 px-2 rounded-2 my-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark">GRAND TOTAL FAKTUR:</span>
                                        <h4 class="fw-bold text-primary mb-0">{{ formatCurrency(invoice.grand_total) }}</h4>
                                    </div>
                                </div>

                                <!-- Payment Status Breakdown -->
                                <div class="p-2 border rounded-2 bg-light mb-3">
                                    <div class="d-flex justify-content-between py-1 small">
                                        <span class="text-muted">Status Pembayaran:</span>
                                        <span v-if="invoice.payment_status === 'paid'" class="badge bg-success">Lunas</span>
                                        <span v-else-if="invoice.payment_status === 'partial'" class="badge bg-warning">Dibayar Sebagian</span>
                                        <span v-else class="badge bg-danger">Belum Dibayar</span>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 small">
                                        <span class="text-muted">Jumlah Dibayar:</span>
                                        <span class="text-success fw-semibold">{{ formatCurrency(invoice.paid_amount) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 small border-top pt-1">
                                        <span class="text-muted fw-bold">Sisa Hutang ke PBF:</span>
                                        <span class="text-danger fw-bold fs-6">{{ formatCurrency(invoice.remaining_amount) }}</span>
                                    </div>
                                </div>

                                <!-- Document Scan / Foto Faktur -->
                                <div v-if="invoice.document_path" class="border rounded-2 p-2 text-center bg-light">
                                    <small class="text-muted d-block mb-2 fw-semibold">Dokumen Scan Faktur Supplier:</small>
                                    <a :href="`/storage/${invoice.document_path}`" target="_blank" class="btn btn-sm btn-outline-primary w-100">
                                        <i class="bx bx-paperclip me-1"></i> Buka Lampiran Dokumen Scan
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Audit Trail Card -->
                        <div class="card bg-white border-0 shadow-sm rounded-3">
                            <div class="card-header bg-light bg-opacity-75 py-3 border-bottom">
                                <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                                    <i class="bx bx-history text-secondary me-2 fs-5"></i>
                                    Riwayat & Audit Transaksi
                                </h6>
                            </div>
                            <div class="card-body p-3 small">
                                <div class="mb-2">
                                    <span class="text-muted d-block">Dibuat Pada:</span>
                                    <strong>{{ formatDateTime(invoice.created_at) }}</strong>
                                </div>
                                <div class="mb-2" v-if="invoice.finalized_at">
                                    <span class="text-muted d-block">Difinalisasi Pada:</span>
                                    <strong class="text-success">{{ formatDateTime(invoice.finalized_at) }}</strong>
                                    <div class="text-muted" v-if="invoice.finalized_by">
                                        Oleh: <strong>{{ invoice.finalized_by?.name || 'Petugas' }}</strong>
                                    </div>
                                </div>
                                <div class="mb-0" v-if="invoice.cancelled_at">
                                    <span class="text-muted d-block">Dibatalkan Pada:</span>
                                    <strong class="text-danger">{{ formatDateTime(invoice.cancelled_at) }}</strong>
                                    <div class="text-muted" v-if="invoice.cancelled_by">
                                        Oleh: <strong>{{ invoice.cancelled_by?.name || 'Petugas' }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>

<style scoped>
/* High contrast styles for Invoice Show Detail Page */
:global(body.dark-mode) .card {
    background-color: #1e293b !important;
    border: 1px solid #334155 !important;
}

:global(body.dark-mode) .card-header {
    background-color: #0f172a !important;
    border-bottom: 1px solid #334155 !important;
}

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

:global(body.dark-mode) .bg-light {
    background-color: #0f172a !important;
}

:global(body.dark-mode) .alert-light {
    background-color: #0f172a !important;
    border-color: #334155 !important;
    color: #cbd5e1 !important;
}
</style>
