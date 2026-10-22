<script setup>
import { onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    invoice: Object,
    print_type: {
        type: String,
        default: 'invoice'
    }
});

let wasDarkMode = false;

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' +
           d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};

const triggerPrint = () => {
    window.print();
};

const closeWindow = () => {
    if (window.opener) {
        window.close();
    } else {
        window.history.back();
    }
};

const handleKeydown = (e) => {
    if (e.key === 'Escape') {
        closeWindow();
    }
};

onMounted(() => {
    // Pastikan jendela print selalu bersih putih tanpa terpengaruh dark-mode
    if (document.documentElement.classList.contains('dark-mode') || document.body.classList.contains('dark-mode')) {
        wasDarkMode = true;
        document.documentElement.classList.remove('dark-mode');
        document.body.classList.remove('dark-mode');
    }

    window.addEventListener('keydown', handleKeydown);

    // Buka print dialog otomatis saat jendela popup selesai dimuat
    if (window.opener) {
        setTimeout(() => {
            window.print();
        }, 500);
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    if (wasDarkMode) {
        document.documentElement.classList.add('dark-mode');
        document.body.classList.add('dark-mode');
    }
});
</script>

<template>
    <Head :title="print_type === 'receiving_slip' ? 'Bukti Penerimaan Barang - ' + invoice.receipt_number : 'Faktur Pembelian - ' + (invoice.invoice_number || invoice.receipt_number)" />

    <div class="print-wrapper p-4 bg-white text-dark">
        <!-- Floating Navigation Bar (Hidden during print) -->
        <div class="no-print d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded border shadow-sm">
            <div class="d-flex align-items-center gap-2">
                <Link :href="route('purchases.invoices.show', invoice.id)" class="btn btn-outline-secondary btn-sm">
                    <i class="bx bx-arrow-back"></i> Kembali ke Faktur
                </Link>
                <button type="button" class="btn btn-outline-dark btn-sm" @click="closeWindow">
                    <i class="bx bx-x"></i> Tutup
                </button>
                <span class="text-muted">|</span>
                <span class="fw-semibold">
                    {{ print_type === 'receiving_slip' ? 'Bukti Penerimaan Barang (GRN)' : 'Faktur Pembelian Farmasi' }}
                </span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-sm px-4 fw-bold" @click="triggerPrint">
                    <i class="bx bx-printer me-1"></i> Cetak Dokumen
                </button>
            </div>
        </div>

        <!-- Document Container -->
        <div class="document-sheet mx-auto p-4 border" style="max-width: 900px; font-size: 13px;">
            <!-- Kop Surat Resmi Apotek (Disesuaikan dengan Pengaturan Profil Apotek) -->
            <div class="kop-surat pb-3 mb-3 border-bottom-double">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-center gap-3">
                        <img 
                            v-if="$page.props.app_settings?.pharmacy_logo" 
                            :src="$page.props.app_settings.pharmacy_logo" 
                            alt="Logo Apotek" 
                            class="kop-logo"
                            style="max-height: 58px; max-width: 120px; object-fit: contain;"
                        >
                        <div>
                            <h4 class="fw-bold mb-1 text-primary text-uppercase" style="letter-spacing: 0.5px;">
                                {{ $page.props.app_settings?.pharmacy_name || 'APOTEK' }}
                            </h4>
                            <p class="mb-0 text-dark small">
                                {{ $page.props.app_settings?.pharmacy_address || '-' }}
                            </p>
                            <p class="mb-0 text-muted small">
                                <span v-if="$page.props.app_settings?.pharmacy_phone">
                                    Telp/WA: <strong>{{ $page.props.app_settings.pharmacy_phone }}</strong>
                                </span>
                                <span v-if="$page.props.app_settings?.pharmacist_name" class="ms-2">
                                    | Apoteker: <strong>{{ $page.props.app_settings.pharmacist_name }}</strong>
                                </span>
                                <span v-if="$page.props.app_settings?.pharmacist_license" class="ms-2">
                                    | SIPA: <strong>{{ $page.props.app_settings.pharmacist_license }}</strong>
                                </span>
                            </p>
                            <p class="mb-0 text-muted small" v-if="invoice.outlet?.name">
                                Unit / Outlet Cabang: <strong>{{ invoice.outlet.name }}</strong>
                                <span v-if="invoice.outlet.address && invoice.outlet.address !== $page.props.app_settings?.pharmacy_address" class="ms-1">
                                    ({{ invoice.outlet.address }})
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="text-end">
                        <h5 class="fw-bold text-dark mb-1 text-uppercase">
                            {{ print_type === 'receiving_slip' ? 'BUKTI PENERIMAAN BARANG' : 'FAKTUR PEMBELIAN FARMASI' }}
                        </h5>
                        <div class="font-monospace fw-bold text-primary">{{ invoice.receipt_number }}</div>
                        <div class="small text-muted" v-if="invoice.invoice_number">
                            No. Faktur PBF: <strong class="text-dark">{{ invoice.invoice_number }}</strong>
                        </div>
                        <div class="small text-muted" v-if="invoice.purchase_order">
                            No. SP / PO: <strong class="text-dark">{{ invoice.purchase_order.po_number }}</strong>
                        </div>
                        <div class="small text-muted">
                            Tanggal: <strong class="text-dark">{{ formatDate(invoice.purchase_date) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Information Meta -->
            <div class="row g-3 mb-3 pb-2 border-bottom">
                <div class="col-6">
                    <table class="table table-sm table-borderless mb-0 small">
                        <tr>
                            <td class="text-muted p-0" style="width: 140px;">Supplier / PBF</td>
                            <td class="p-0 fw-bold">: {{ invoice.supplier?.name || '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted p-0">Kode PBF</td>
                            <td class="p-0">: {{ invoice.supplier?.code || '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted p-0">Alamat / Telp</td>
                            <td class="p-0">: {{ invoice.supplier?.phone || invoice.supplier?.address || '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted p-0">No. Surat Jalan</td>
                            <td class="p-0">: {{ invoice.delivery_order_number || '-' }}</td>
                        </tr>
                    </table>
                </div>

                <div class="col-6">
                    <table class="table table-sm table-borderless mb-0 small">
                        <tr>
                            <td class="text-muted p-0" style="width: 140px;">Tanggal Faktur</td>
                            <td class="p-0">: {{ formatDate(invoice.purchase_date) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted p-0">Tanggal Terima</td>
                            <td class="p-0 fw-bold">: {{ formatDate(invoice.received_date) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted p-0">Jatuh Tempo</td>
                            <td class="p-0">: {{ formatDate(invoice.due_date) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted p-0">Jenis Pembayaran</td>
                            <td class="p-0 text-uppercase">: {{ invoice.payment_type || 'Kredit' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Items Table -->
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm align-middle mb-0" style="font-size: 12px;">
                    <thead class="table-light text-secondary text-uppercase" style="font-size: 11px;">
                        <tr>
                            <th class="text-center" style="width: 30px;">#</th>
                            <th>Nama Obat & Kode</th>
                            <th>No. Batch</th>
                            <th>Expired Date</th>
                            <th class="text-center">Jumlah Beli</th>
                            <th class="text-center bg-light">Stok Masuk</th>
                            <th v-if="print_type !== 'receiving_slip'" class="text-end">Harga Satuan</th>
                            <th v-if="print_type !== 'receiving_slip'" class="text-center">Diskon</th>
                            <th v-if="print_type !== 'receiving_slip'" class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in invoice.items" :key="item.id">
                            <td class="text-center">{{ idx + 1 }}</td>
                            <td>
                                <strong>{{ item.medicine?.nama || item.medicine_id }}</strong>
                                <div class="text-muted font-monospace" style="font-size: 10px;">{{ item.medicine_id }}</div>
                            </td>
                            <td class="font-monospace">{{ item.batch_number }}</td>
                            <td>{{ formatDate(item.expired_date) }}</td>
                            <td class="text-center">
                                {{ item.quantity }} {{ item.unit_name }}
                            </td>
                            <td class="text-center fw-bold bg-light">
                                +{{ item.quantity_base }} {{ item.medicine?.satuanDasar?.name || 'Unit' }}
                            </td>
                            <td v-if="print_type !== 'receiving_slip'" class="text-end">
                                {{ formatCurrency(item.unit_price) }}
                            </td>
                            <td v-if="print_type !== 'receiving_slip'" class="text-center">
                                <span v-if="item.discount_percent > 0">{{ item.discount_percent }}%</span>
                                <span v-else-if="item.discount_amount > 0">-{{ formatCurrency(item.discount_amount) }}</span>
                                <span v-else>-</span>
                            </td>
                            <td v-if="print_type !== 'receiving_slip'" class="text-end fw-bold">
                                {{ formatCurrency(item.subtotal_net || item.subtotal) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Financial Totals (Only on Invoice View) -->
            <div v-if="print_type !== 'receiving_slip'" class="row g-2 mb-4">
                <div class="col-7">
                    <div class="p-2 border rounded bg-light small" v-if="invoice.notes">
                        <strong>Catatan:</strong> {{ invoice.notes }}
                    </div>
                </div>
                <div class="col-5">
                    <table class="table table-sm table-bordered mb-0 small">
                        <tr>
                            <td>Subtotal Item:</td>
                            <td class="text-end fw-semibold">{{ formatCurrency(invoice.subtotal) }}</td>
                        </tr>
                        <tr v-if="invoice.discount_amount > 0">
                            <td>Diskon Faktur:</td>
                            <td class="text-end text-danger">- {{ formatCurrency(invoice.discount_amount) }}</td>
                        </tr>
                        <tr v-if="invoice.tax_amount > 0">
                            <td>Pajak (PPN):</td>
                            <td class="text-end">+ {{ formatCurrency(invoice.tax_amount) }}</td>
                        </tr>
                        <tr v-if="invoice.shipping_cost > 0">
                            <td>Ongkir:</td>
                            <td class="text-end">+ {{ formatCurrency(invoice.shipping_cost) }}</td>
                        </tr>
                        <tr class="table-light">
                            <td class="fw-bold">Total Faktur:</td>
                            <td class="text-end fw-bold text-primary fs-6">{{ formatCurrency(invoice.grand_total) }}</td>
                        </tr>
                        <tr v-if="invoice.remaining_amount > 0">
                            <td class="text-danger">Sisa Hutang:</td>
                            <td class="text-end text-danger fw-bold">{{ formatCurrency(invoice.remaining_amount) }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Signatures Section -->
            <div class="row text-center mt-5 pt-3" style="font-size: 12px;">
                <div class="col-4">
                    <p class="mb-5">Pengirim / Driver PBF,</p>
                    <p class="fw-bold mb-0 border-bottom d-inline-block px-4">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</p>
                    <small class="text-muted d-block mt-1">Nama & Tanda Tangan</small>
                </div>
                <div class="col-4">
                    <p class="mb-5">Petugas Penerima Fisik,</p>
                    <p class="fw-bold mb-0 border-bottom d-inline-block px-4">
                        {{ invoice.finalized_by?.name || 'Petugas Farmasi' }}
                    </p>
                    <small class="text-muted d-block mt-1">Staf Logistik / Gudang</small>
                </div>
                <div class="col-4">
                    <p class="mb-5">Apoteker Penanggung Jawab,</p>
                    <p class="fw-bold mb-0 border-bottom d-inline-block px-3">
                        ( {{ $page.props.app_settings?.pharmacist_name || 'Apoteker Penanggung Jawab' }} )
                    </p>
                    <small class="text-muted d-block mt-1">
                        SIPA: {{ $page.props.app_settings?.pharmacist_license || '-' }}
                    </small>
                </div>
            </div>

            <!-- Footer Stamp -->
            <div class="border-top pt-2 mt-4 text-center text-muted small" style="font-size: 11px;">
                Dokumen dicetak pada {{ formatDateTime(new Date()) }} melalui Sistem Informasi Manajemen Apotek - {{ $page.props.app_settings?.pharmacy_name }}.
            </div>
        </div>
    </div>
</template>

<style>
.border-bottom-double {
    border-bottom: 3px double #0f172a !important;
}

html, body, #app, .print-wrapper {
    background: #f1f5f9 !important;
    background-color: #f1f5f9 !important;
    color: #0f172a !important;
}

.document-sheet {
    background: #ffffff !important;
    color: #0f172a !important;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
}

.table th, .table td {
    color: #0f172a !important;
    border-color: #cbd5e1 !important;
}

.table-light th {
    background-color: #f1f5f9 !important;
    color: #0f172a !important;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 10mm 12mm;
    }
    html, body, #app, .print-wrapper {
        background: #ffffff !important;
        background-color: #ffffff !important;
        color: #000000 !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .no-print {
        display: none !important;
    }
    .document-sheet {
        border: none !important;
        box-shadow: none !important;
        max-width: 100% !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
