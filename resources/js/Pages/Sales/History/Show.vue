<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    sale: Object,
});

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
};

// Print Preview Modal State
const showPrintModal = ref(false);
const printFormat = ref('A4'); // 'A4', '80mm', '58mm'
const printTime = ref('');

const openPrintModal = () => {
    const now = new Date();
    printTime.value = now.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + 
                     now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    showPrintModal.value = true;
};

const executePrint = () => {
    const printArea = document.getElementById('printableInvoiceArea');
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

    printWindow.document.write('<!DOCTYPE html><html><head><title>Struk / Invoice - ' + props.sale.invoice_number + '</title>');
    printWindow.document.write('<style>');
    printWindow.document.write(`
        @page { size: ${printFormat.value === 'A4' ? 'A4 portrait' : bodyWidth + ' auto'}; margin: ${printFormat.value === 'A4' ? '15mm' : '3mm'}; }
        body {
            font-family: ${printFormat.value === 'A4' ? '\'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif' : '\'Courier New\', Courier, monospace'};
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
        .my-2 { margin-top: 8px; margin-bottom: 8px; }
        .py-2 { padding-top: 8px; padding-bottom: 8px; }
        .border-top { border-top: 1px solid #ccc; }
        .border-bottom { border-bottom: 1px solid #ccc; }
        .d-flex { display: flex; }
        .justify-content-between { justify-content: space-between; }
        .signature-box { margin-top: 30px; display: flex; justify-content: space-between; text-align: center; }
        .signature-col { width: 45%; }
        .signature-line { margin-top: 45px; font-weight: bold; }
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

const totalItemQty = computed(() => {
    if (!props.sale?.items) return 0;
    return props.sale.items.reduce((sum, item) => sum + item.quantity, 0);
});
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex align-items-center justify-content-between mb-4 print:d-none">
                    <div class="d-flex align-items-center gap-3">
                        <Link :href="route('sales.history.index')" class="btn btn-outline-secondary btn-sm rounded-circle p-2">
                            <i class="bx bx-arrow-back fs-5"></i>
                        </Link>
                        <h2 class="fw-bold text-dark mb-0">
                            <i class="bx bx-receipt text-primary me-2"></i>Detail Transaksi: {{ sale.invoice_number }}
                        </h2>
                    </div>
                    <div class="d-flex gap-2">
                        <button @click="openPrintModal" class="btn btn-primary fw-bold shadow-sm">
                            <i class="bx bx-printer me-1"></i> Cetak Struk / Invoice
                        </button>
                        <Link :href="route('returns.create', { type: 'sale', reference_id: sale.id })" class="btn btn-danger shadow-sm">
                            <i class="bx bx-repost me-1"></i> Retur Penjualan Ini
                        </Link>
                    </div>
                </div>

                <div class="card border-0 shadow-sm p-4 print:shadow-none print:p-0" style="border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
                        <div>
                            <h3 class="fw-bold text-dark mb-1">STRUK PENJUALAN APOTEK</h3>
                            <span class="font-monospace text-muted">{{ sale.invoice_number }}</span>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success fs-6 text-uppercase">{{ sale.status || 'COMPLETED' }}</span>
                            <small class="text-muted d-block mt-1">Tanggal: {{ formatDate(sale.sale_date) }}</small>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted text-uppercase small mb-2">Informasi Transaksi</h6>
                            <div class="bg-light p-3 rounded-3 border">
                                <small class="text-muted d-block">Kasir / Petugas</small>
                                <strong class="text-dark d-block mb-2">{{ sale.user?.name || '-' }}</strong>
                                <small class="text-muted d-block">Pelanggan / Member</small>
                                <strong class="text-primary">{{ sale.customer?.name || 'Umum / Non-Member' }}</strong>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted text-uppercase small mb-2">Pembayaran</h6>
                            <div class="bg-light p-3 rounded-3 border">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Metode Bayar</small>
                                        <strong class="text-dark text-uppercase">{{ sale.payment_method }}</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Jumlah Bayar</small>
                                        <strong class="text-dark">{{ formatCurrency(sale.paid_amount) }}</strong>
                                    </div>
                                    <div class="col-6 mt-2">
                                        <small class="text-muted d-block">Kembalian</small>
                                        <strong class="text-success">{{ formatCurrency(sale.change_amount) }}</strong>
                                    </div>
                                    <div class="col-6 mt-2">
                                        <small class="text-muted d-block">Total Belanja</small>
                                        <strong class="text-primary">{{ formatCurrency(sale.grand_total) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <h5 class="fw-bold text-dark mb-3"><i class="bx bx-capsule me-2 text-primary"></i>Rincian Obat yang Dibeli</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-hover align-middle">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>Kode / Nama Obat</th>
                                    <th class="text-center">Jumlah (Qty)</th>
                                    <th class="text-end">Harga Unit</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in sale.items" :key="item.id">
                                    <td>
                                        <div class="fw-bold text-dark">{{ item.medicine?.nama || item.medicine_id }}</div>
                                        <small class="text-muted">Kode: {{ item.medicine_id }}</small>
                                    </td>
                                    <td class="text-center fw-bold">{{ item.quantity }}</td>
                                    <td class="text-end text-muted">{{ formatCurrency(item.unit_price) }}</td>
                                    <td class="text-end fw-bold text-dark">{{ formatCurrency(item.unit_price * item.quantity) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end fw-bold text-muted">Subtotal</td>
                                    <td class="text-end fw-bold text-dark">{{ formatCurrency(sale.subtotal) }}</td>
                                </tr>
                                <tr v-if="sale.discount > 0">
                                    <td colspan="3" class="text-end fw-bold text-danger">Diskon</td>
                                    <td class="text-end fw-bold text-danger">-{{ formatCurrency(sale.discount) }}</td>
                                </tr>
                                <tr v-if="sale.tax > 0">
                                    <td colspan="3" class="text-end fw-bold text-muted">PPN</td>
                                    <td class="text-end fw-bold text-dark">+{{ formatCurrency(sale.tax) }}</td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="3" class="text-end fw-bold text-dark fs-6">Grand Total</td>
                                    <td class="text-end fw-bold text-primary fs-5">{{ formatCurrency(sale.grand_total) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div v-if="sale.product_returns && sale.product_returns.length > 0" class="alert alert-info border-0 shadow-sm">
                        <strong class="d-block mb-1"><i class="bx bx-repost me-1"></i>Riwayat Retur Terkait Transaksi Ini:</strong>
                        <ul class="mb-0 small">
                            <li v-for="ret in sale.product_returns" :key="ret.id">
                                Retur No: <strong>{{ ret.return_number }}</strong> (Status: {{ ret.status }}) — Total: {{ formatCurrency(ret.total_amount) }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- PRINT PREVIEW MODAL -->
        <div v-if="showPrintModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.65);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">
                            <i class="bx bx-printer me-2"></i>Preview Cetak Struk / Invoice Penjualan
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="showPrintModal = false"></button>
                    </div>
                    
                    <div class="modal-body bg-light">
                        <!-- Format Selection Controls -->
                        <div class="card border-0 shadow-xs p-3 mb-4">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <label class="fw-bold me-3 text-dark">Pilih Format Printer:</label>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm" :class="printFormat === 'A4' ? 'btn-primary' : 'btn-outline-primary'" @click="printFormat = 'A4'">
                                            <i class="bx bx-file me-1"></i> Document Invoice A4 Standard
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
                            <div id="printableInvoiceArea" class="bg-white p-4 text-dark shadow-sm border" :style="{
                                width: printFormat === 'A4' ? '100%' : (printFormat === '80mm' ? '360px' : '280px'),
                                fontSize: printFormat === 'A4' ? '13px' : '11px',
                                fontFamily: printFormat === 'A4' ? 'Arial, sans-serif' : '\'Courier New\', Courier, monospace'
                            }">
                                <!-- HEADER APOTEK -->
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
                                        {{ printFormat === 'A4' ? 'FAKTUR INVOICE PENJUALAN' : 'NOTA STROK PENJUALAN' }}
                                    </h5>
                                </div>

                                <!-- TRANSACTION INFO -->
                                <div class="mb-3 small">
                                    <div class="d-flex justify-content-between">
                                        <span><strong>No Invoice:</strong> {{ sale.invoice_number }}</span>
                                        <span><strong>Tgl:</strong> {{ formatDate(sale.sale_date) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <span><strong>Kasir:</strong> {{ sale.user?.name || '-' }}</span>
                                        <span><strong>Metode:</strong> {{ sale.payment_method?.toUpperCase() }}</span>
                                    </div>
                                    <div class="mt-1">
                                        <span><strong>Pelanggan:</strong> {{ sale.customer?.name || 'Umum / Non-Member' }}</span>
                                    </div>
                                </div>

                                <!-- ITEMS TABLE -->
                                <table :class="printFormat === 'A4' ? 'table table-bordered align-middle' : 'thermal-table'" style="margin-bottom: 10px;">
                                    <thead>
                                        <tr>
                                            <th v-if="printFormat === 'A4'" style="width: 30px;">No</th>
                                            <th>Nama Obat</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Harga</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, idx) in sale.items" :key="item.id">
                                            <td v-if="printFormat === 'A4'" class="text-center">{{ idx + 1 }}</td>
                                            <td>
                                                <div class="fw-bold">{{ item.medicine?.nama || item.medicine_id }}</div>
                                                <small v-if="printFormat !== 'A4'" class="text-muted">Kode: {{ item.medicine_id }}</small>
                                            </td>
                                            <td class="text-center fw-bold">{{ item.quantity }}</td>
                                            <td class="text-end">{{ formatCurrency(item.unit_price) }}</td>
                                            <td class="text-end fw-bold">{{ formatCurrency(item.unit_price * item.quantity) }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- SUMMARY & CALCULATIONS -->
                                <div class="py-2 border-top border-bottom mb-3 small">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Subtotal Belanja:</span>
                                        <strong>{{ formatCurrency(sale.subtotal) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1 text-danger" v-if="sale.discount > 0">
                                        <span>Diskon:</span>
                                        <strong>-{{ formatCurrency(sale.discount) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1 text-dark" v-if="sale.tax > 0">
                                        <span>PPN:</span>
                                        <strong>+{{ formatCurrency(sale.tax) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between fs-6 fw-bold mt-2 pt-2 border-top">
                                        <span>TOTAL BELANJA:</span>
                                        <span class="text-primary">{{ formatCurrency(sale.grand_total) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <span>Jumlah Uang Dibayar:</span>
                                        <span>{{ formatCurrency(sale.paid_amount) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1 fw-bold">
                                        <span>Uang Kembalian:</span>
                                        <span class="text-success">{{ formatCurrency(sale.change_amount) }}</span>
                                    </div>
                                </div>

                                <!-- SIGNATURE SECTION FOR A4 INVOICE -->
                                <div class="signature-box my-3 pt-2" v-if="printFormat === 'A4'">
                                    <div class="signature-col">
                                        <div>Penerima / Pembeli</div>
                                        <div class="signature-line">( {{ sale.customer?.name || '________________' }} )</div>
                                    </div>
                                    <div class="signature-col">
                                        <div>Kasir / Petugas Apotek</div>
                                        <div class="signature-line">( {{ sale.user?.name || '________________' }} )</div>
                                    </div>
                                </div>

                                <!-- FOOTER & REFERENSI -->
                                <div class="text-center mt-3 pt-2 border-top small text-muted">
                                    <div class="font-monospace fw-bold tracking-widest text-dark" style="letter-spacing: 2px;">
                                        ||| |||| | ||||| || ||| ||||
                                    </div>
                                    <div class="font-monospace small text-dark mb-1">{{ sale.invoice_number }}</div>
                                    <div>Dicetak pada: {{ printTime }}</div>
                                    <small class="d-block mt-1">Terima Kasih Atas Kunjungan Anda — Semoga Lekas Sembuh</small>
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
