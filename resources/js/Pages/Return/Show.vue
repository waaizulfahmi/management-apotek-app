<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import axios from 'axios';

const props = defineProps({
    productReturn: Object,
    canApprove: Boolean,
});

const isSale = computed(() => props.productReturn.type === 'sale');

// Permission / Status check for printing
const canPrint = computed(() => {
    return props.productReturn.status === 'APPROVED' || 
           props.productReturn.status === 'COMPLETED' || 
           props.canApprove;
});

const approveForm = useForm({});
const cancelForm = useForm({});

const approveReturn = () => {
    if (confirm('Apakah Anda yakin ingin menyetujui retur ini? Stok dan keuangan akan diupdate sesuai dengan data retur.')) {
        approveForm.post(route('returns.approve', props.productReturn.id));
    }
};

const cancelReturn = () => {
    if (confirm('Apakah Anda yakin ingin membatalkan retur ini?')) {
        cancelForm.post(route('returns.cancel', props.productReturn.id));
    }
};

// Print Preview Modal State
const showPrintModal = ref(false);
const printFormat = ref('A4'); // 'A4', '80mm', '58mm'
const printTime = ref('');

const openPrintModal = () => {
    if (!canPrint.value) {
        alert("Bukti retur belum bisa dicetak karena status masih PENDING dan Anda tidak memiliki wewenang persetujuan.");
        return;
    }
    const now = new Date();
    printTime.value = now.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + 
                     now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    showPrintModal.value = true;
};

const executePrint = async () => {
    // Send audit log request to backend
    try {
        await axios.post(route('returns.log-print', props.productReturn.id), {
            format: printFormat.value
        });
    } catch (e) {
        console.error('Failed to record audit log', e);
    }

    const printArea = document.getElementById('printableArea');
    if (!printArea) return;

    const printContents = printArea.innerHTML;
    const printWindow = window.open('', '_blank', 'width=900,height=800');

    let bodyWidth = '210mm';
    let fontSize = '12px';
    if (printFormat.value === '80mm') {
        bodyWidth = '76mm';
        fontSize = '11px';
    } else if (printFormat.value === '58mm') {
        bodyWidth = '54mm';
        fontSize = '10px';
    }

    printWindow.document.write('<!DOCTYPE html><html><head><title>Bukti Retur - ' + props.productReturn.return_number + '</title>');
    printWindow.document.write('<style>');
    printWindow.document.write(`
        @page { size: ${printFormat.value === 'A4' ? 'A4 portrait' : bodyWidth + ' auto'}; margin: ${printFormat.value === 'A4' ? '15mm' : '3mm'}; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            width: ${printFormat.value === 'A4' ? '100%' : bodyWidth};
            margin: 0 auto;
            padding: 5px;
            font-size: ${fontSize};
            color: #000;
            background: #fff;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .text-muted { color: #555; }
        .small { font-size: 0.85em; }
        .font-monospace { font-family: monospace; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 8px; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        th { background-color: #f2f2f2; }
        .thermal-table th, .thermal-table td { border: none; border-bottom: 1px dashed #777; padding: 4px 0; }
        .no-border th, .no-border td { border: none !important; }
        .my-2 { margin-top: 8px; margin-bottom: 8px; }
        .py-2 { padding-top: 8px; padding-bottom: 8px; }
        .border-top { border-top: 1px solid #ccc; }
        .border-bottom { border-bottom: 1px solid #ccc; }
        .d-flex { display: flex; }
        .justify-content-between { justify-content: space-between; }
        .align-items-center { align-items: center; }
        .signature-box { margin-top: 30px; display: flex; justify-content: space-between; text-align: center; }
        .signature-col { width: 45%; }
        .signature-line { margin-top: 50px; font-weight: bold; }
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

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
};

const getStatusBadgeClass = (status) => {
    switch(status) {
        case 'DRAFT': return 'bg-secondary';
        case 'PENDING': return 'bg-warning text-dark';
        case 'APPROVED': return 'bg-info text-dark';
        case 'COMPLETED': return 'bg-success';
        case 'REJECTED': 
        case 'CANCELLED': return 'bg-danger';
        default: return 'bg-secondary';
    }
};

const totalReturnedQty = computed(() => {
    if (!props.productReturn?.items) return 0;
    return props.productReturn.items.reduce((sum, item) => sum + item.quantity, 0);
});
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex align-items-center justify-content-between mb-4 print:d-none">
                    <div class="d-flex align-items-center gap-3">
                        <Link :href="route('returns.index')" class="btn btn-outline-secondary btn-sm rounded-circle p-2">
                            <i class="bx bx-arrow-back fs-5"></i>
                        </Link>
                        <h2 class="fw-bold text-dark mb-0">
                            <i class="bx bx-receipt text-primary me-2"></i>Detail Retur: {{ productReturn.return_number }}
                        </h2>
                    </div>
                    <div class="d-flex gap-2">
                        <button @click="openPrintModal" :disabled="!canPrint" class="btn btn-primary shadow-sm fw-bold">
                            <i class="bx bx-printer me-1"></i> Cetak Bukti Retur
                        </button>
                        <button v-if="productReturn.status === 'PENDING' && canApprove" @click="cancelReturn" :disabled="cancelForm.processing" class="btn btn-danger shadow-sm">
                            <i class="bx bx-x-circle me-1"></i> Batalkan
                        </button>
                        <button v-if="productReturn.status === 'PENDING' && canApprove" @click="approveReturn" :disabled="approveForm.processing" class="btn btn-success shadow-sm">
                            <i class="bx bx-check-circle me-1"></i> Setujui Retur
                        </button>
                    </div>
                </div>

                <!-- Alert for Pending -->
                <div v-if="productReturn.status === 'PENDING'" class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4 print:d-none">
                    <i class="bx bx-info-circle fs-4 me-2"></i>
                    <div>Retur ini masih berstatus <strong>PENDING</strong>. Menunggu persetujuan sebelum stok dan keuangan diperbarui.</div>
                </div>

                <div class="card border-0 shadow-sm p-4 print:shadow-none print:p-0" style="border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
                        <div>
                            <h3 class="fw-bold text-dark mb-1">{{ isSale ? 'BUKTI RETUR PENJUALAN' : 'BUKTI RETUR PEMBELIAN' }}</h3>
                            <span class="font-monospace text-muted">{{ productReturn.return_number }}</span>
                        </div>
                        <div class="text-end">
                            <span :class="['badge fs-6', getStatusBadgeClass(productReturn.status)]">
                                {{ productReturn.status }}
                            </span>
                            <small class="text-muted d-block mt-1">Tanggal: {{ formatDate(productReturn.created_at) }}</small>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted text-uppercase small mb-2">Informasi Pihak</h6>
                            <div class="bg-light p-3 rounded-3 border">
                                <div v-if="isSale">
                                    <small class="text-muted d-block">Customer</small>
                                    <strong class="text-dark d-block mb-2">{{ productReturn.customer?.name || 'Customer Umum' }}</strong>
                                    <small class="text-muted d-block">Invoice Referensi</small>
                                    <strong class="text-primary">{{ productReturn.sale?.invoice_number || '-' }}</strong>
                                </div>
                                <div v-else>
                                    <small class="text-muted d-block">Supplier</small>
                                    <strong class="text-dark d-block mb-2">{{ productReturn.supplier?.name || '-' }}</strong>
                                    <small class="text-muted d-block">PO/Invoice Referensi</small>
                                    <strong class="text-primary">{{ productReturn.purchase?.po_number || '-' }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted text-uppercase small mb-2">Informasi Retur</h6>
                            <div class="bg-light p-3 rounded-3 border">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Dibuat Oleh</small>
                                        <strong class="text-dark">{{ productReturn.user?.name || '-' }}</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Disetujui Oleh</small>
                                        <strong class="text-dark">{{ productReturn.approver?.name || '-' }}</strong>
                                    </div>
                                    <div class="col-6 mt-2">
                                        <small class="text-muted d-block">Metode Refund / Status</small>
                                        <strong class="text-dark">{{ isSale ? (productReturn.refund_method || '-') : (productReturn.status === 'APPROVED' ? 'Sudah Diproses' : 'Menunggu Credit Note') }}</strong>
                                    </div>
                                    <div class="col-6 mt-2">
                                        <small class="text-muted d-block">Total Nilai Retur</small>
                                        <strong class="text-success">{{ formatCurrency(productReturn.total_amount) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <h5 class="fw-bold text-dark mb-3"><i class="bx bx-list-ul me-2 text-primary"></i>Daftar Barang Diretur</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-hover align-middle">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th style="width: 40px;">No</th>
                                    <th>Produk</th>
                                    <th>Batch</th>
                                    <th>Expired</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Harga</th>
                                    <th>Alasan & Kondisi</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in productReturn.items" :key="item.id">
                                    <td class="text-center text-muted">{{ idx + 1 }}</td>
                                    <td class="fw-bold text-dark">{{ item.medicine?.nama || item.medicine_id }}</td>
                                    <td><span class="badge bg-secondary font-monospace">{{ item.batch?.batch_number || '-' }}</span></td>
                                    <td class="small text-muted">{{ item.batch?.expired_date || '-' }}</td>
                                    <td class="text-center fw-bold">{{ item.quantity }}</td>
                                    <td class="text-end text-muted">{{ formatCurrency(item.unit_price) }}</td>
                                    <td>
                                        <div class="text-dark fw-bold small">{{ item.reason }}</div>
                                        <small v-if="item.condition" class="text-muted">Kondisi: {{ item.condition }}</small>
                                    </td>
                                    <td class="text-end fw-bold text-dark">{{ formatCurrency(item.subtotal) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="7" class="text-end fw-bold text-dark fs-6">Total Retur</td>
                                    <td class="text-end fw-bold text-primary fs-5">{{ formatCurrency(productReturn.total_amount) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div v-if="productReturn.notes" class="bg-light p-3 rounded-3 border">
                        <strong class="text-dark small d-block mb-1">Catatan:</strong>
                        <p class="text-muted mb-0 small">{{ productReturn.notes }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- PRINT PREVIEW MODAL -->
        <div v-if="showPrintModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">
                            <i class="bx bx-printer me-2"></i>Preview Cetak Bukti Retur
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="showPrintModal = false"></button>
                    </div>
                    
                    <div class="modal-body bg-light">
                        <!-- Format Selection Controls -->
                        <div class="card border-0 shadow-xs p-3 mb-4">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <label class="fw-bold me-3 text-dark">Pilih Format Printers:</label>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm" :class="printFormat === 'A4' ? 'btn-primary' : 'btn-outline-primary'" @click="printFormat = 'A4'">
                                            <i class="bx bx-file me-1"></i> Document A4 Standard
                                        </button>
                                        <button type="button" class="btn btn-sm" :class="printFormat === '80mm' ? 'btn-primary' : 'btn-outline-primary'" @click="printFormat = '80mm'">
                                            <i class="bx bx-receipt me-1"></i> Thermal 80mm
                                        </button>
                                        <button type="button" class="btn btn-sm" :class="printFormat === '58mm' ? 'btn-primary' : 'btn-outline-primary'" @click="printFormat = '58mm'">
                                            <i class="bx bx-receipt me-1"></i> Thermal 58mm (Mini)
                                        </button>
                                    </div>
                                </div>
                                
                                <span class="badge bg-info text-dark">
                                    Format Terpilih: {{ printFormat }}
                                </span>
                            </div>
                        </div>

                        <!-- LIVE PRINT PREVIEW CONTAINER -->
                        <div class="d-flex justify-content-center">
                            <div id="printableArea" class="bg-white p-4 text-dark shadow-sm border" :style="{
                                width: printFormat === 'A4' ? '100%' : (printFormat === '80mm' ? '360px' : '280px'),
                                fontSize: printFormat === 'A4' ? '13px' : '11px',
                                fontFamily: printFormat === 'A4' ? 'Arial, sans-serif' : '\'Courier New\', Courier, monospace'
                            }">
                                <!-- HEADER -->
                                <div class="text-center mb-3 pb-2 border-bottom">
                                    <img v-if="$page.props.app_settings?.pharmacy_logo" :src="$page.props.app_settings.pharmacy_logo" alt="Logo" style="max-height: 48px; object-fit: contain;" class="mb-1 d-block mx-auto">
                                    <img v-else src="/Assets/img/LOGO.svg" alt="Logo" style="max-height: 40px;" class="mb-1" @error="(e) => e.target.style.display='none'">
                                    <h4 class="fw-bold mb-0" style="font-size: 1.2em;">{{ $page.props.app_settings?.pharmacy_name || 'APOTEK MEDIKA SORA' }}</h4>
                                    <div class="small">{{ $page.props.app_settings?.pharmacy_address || 'Jl. Raya Farmasi No. 10' }} | Telp: {{ $page.props.app_settings?.pharmacy_phone || '021-5551234' }}</div>
                                    <div v-if="$page.props.app_settings?.pharmacist_name" class="small text-muted" style="font-size: 0.85em;">
                                        Apoteker: {{ $page.props.app_settings.pharmacist_name }} | SIPA: {{ $page.props.app_settings.pharmacist_license }}
                                    </div>
                                    <hr class="my-2" :style="{ borderStyle: printFormat === 'A4' ? 'solid' : 'dashed' }">
                                    <h5 class="fw-bold text-uppercase mb-0" style="font-size: 1.1em; letter-spacing: 0.5px;">
                                        {{ isSale ? 'BUKTI RETUR PENJUALAN' : 'BUKTI RETUR PEMBELIAN' }}
                                    </h5>
                                </div>

                                <!-- TRANSACTION INFO -->
                                <div class="mb-3 small">
                                    <div class="d-flex justify-content-between">
                                        <span><strong>No. Retur:</strong> {{ productReturn.return_number }}</span>
                                        <span><strong>Tgl:</strong> {{ formatDate(productReturn.created_at) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <span><strong>Ref {{ isSale ? 'Inv' : 'PO' }}:</strong> {{ isSale ? (productReturn.sale?.invoice_number || '-') : (productReturn.purchase?.po_number || '-') }}</span>
                                        <span><strong>Petugas:</strong> {{ productReturn.user?.name || '-' }}</span>
                                    </div>
                                    <div class="mt-1">
                                        <span><strong>{{ isSale ? 'Customer' : 'Supplier/PBF' }}:</strong> {{ isSale ? (productReturn.customer?.name || 'Customer Umum') : (productReturn.supplier?.name || '-') }}</span>
                                    </div>
                                </div>

                                <!-- ITEMS TABLE -->
                                <table :class="printFormat === 'A4' ? 'table table-bordered align-middle' : 'thermal-table'" style="margin-bottom: 10px;">
                                    <thead>
                                        <tr>
                                            <th v-if="printFormat === 'A4'" style="width: 30px;">No</th>
                                            <th>Produk</th>
                                            <th v-if="printFormat === 'A4'">Batch</th>
                                            <th v-if="printFormat === 'A4'">Exp</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Harga</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, idx) in productReturn.items" :key="item.id">
                                            <td v-if="printFormat === 'A4'" class="text-center">{{ idx + 1 }}</td>
                                            <td>
                                                <div class="fw-bold">{{ item.medicine?.nama || item.medicine_id }}</div>
                                                <small v-if="printFormat !== 'A4'" class="text-muted">Batch: {{ item.batch?.batch_number || '-' }} | Reason: {{ item.reason }}</small>
                                            </td>
                                            <td v-if="printFormat === 'A4'">{{ item.batch?.batch_number || '-' }}</td>
                                            <td v-if="printFormat === 'A4'">{{ item.batch?.expired_date || '-' }}</td>
                                            <td class="text-center fw-bold">{{ item.quantity }}</td>
                                            <td class="text-end">{{ formatCurrency(item.unit_price) }}</td>
                                            <td class="text-end fw-bold">{{ formatCurrency(item.subtotal) }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- SUMMARY & RETURN DETAILS -->
                                <div class="py-2 border-top border-bottom mb-3 small">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Total Barang Diretur:</span>
                                        <strong>{{ totalReturnedQty }} item</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1" v-if="isSale">
                                        <span>Metode Refund:</span>
                                        <strong>{{ productReturn.refund_method || 'Cash' }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1" v-else>
                                        <span>Status Credit Note:</span>
                                        <strong>{{ productReturn.status === 'APPROVED' ? 'Sudah Diproses' : 'Menunggu Credit Note' }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between fs-6 fw-bold mt-2 pt-2 border-top">
                                        <span>{{ isSale ? 'TOTAL REFUND:' : 'TOTAL NILAI RETUR:' }}</span>
                                        <span class="text-primary">{{ formatCurrency(productReturn.total_amount) }}</span>
                                    </div>
                                </div>

                                <!-- SIGNATURE SECTION -->
                                <div class="signature-box my-3 pt-2">
                                    <div class="signature-col">
                                        <div>{{ isSale ? 'Customer' : 'Supplier / PBF' }}</div>
                                        <div class="signature-line">( ________________ )</div>
                                    </div>
                                    <div class="signature-col">
                                        <div>Petugas Apotek</div>
                                        <div class="signature-line">( {{ productReturn.user?.name || '________________' }} )</div>
                                    </div>
                                </div>

                                <!-- FOOTER & REFERENSI -->
                                <div class="text-center mt-3 pt-2 border-top small text-muted">
                                    <!-- Simple SVG Barcode representation for Return Number -->
                                    <div class="font-monospace fw-bold tracking-widest text-dark" style="letter-spacing: 2px;">
                                        ||| |||| | ||||| || ||| ||||
                                    </div>
                                    <div class="font-monospace small text-dark mb-1">{{ productReturn.return_number }}</div>
                                    <div>Dicetak pada: {{ printTime }}</div>
                                    <small class="d-block mt-1">Dokumen resmi retur barang Apotek Medika Sore.</small>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <div class="modal-footer bg-white">
                        <button type="button" class="btn btn-secondary" @click="showPrintModal = false">Batal</button>
                        <button type="button" class="btn btn-primary fw-bold px-4" @click="executePrint">
                            <i class="bx bx-printer me-1"></i> Cetak Sekarang ({{ printFormat }})
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </LegacyLayout>
</template>
