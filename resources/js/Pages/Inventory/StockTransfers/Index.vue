<script setup>
import { ref } from 'vue';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { router, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    transfers: Object,
    outlets: Array,
    filters: Object,
});

const statusFilter = ref(props.filters?.status || '');
const fromOutletFilter = ref(props.filters?.from_outlet_id || '');
const toOutletFilter = ref(props.filters?.to_outlet_id || '');

const applyFilter = () => {
    router.get(route('stock-transfers.index'), {
        status: statusFilter.value,
        from_outlet_id: fromOutletFilter.value,
        to_outlet_id: toOutletFilter.value,
    }, { preserveState: true, replace: true });
};

// Detail Modal
const selectedTransfer = ref(null);
const openDetailModal = (trf) => {
    selectedTransfer.value = trf;
    const modalEl = document.getElementById('detailTransferModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
};

// Receive Modal
const receiveTransferData = ref(null);
const receiveForm = useForm({
    received_items: {},
});

const openReceiveModal = (trf) => {
    receiveTransferData.value = trf;
    const itemsMap = {};
    trf.items.forEach(item => {
        itemsMap[item.id] = item.qty_sent || item.qty_requested;
    });
    receiveForm.received_items = itemsMap;

    const modalEl = document.getElementById('receiveTransferModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
};

const submitReceive = () => {
    if (!receiveTransferData.value) return;
    receiveForm.post(route('stock-transfers.receive', receiveTransferData.value.id), {
        onSuccess: () => {
            const modalEl = document.getElementById('receiveTransferModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    });
};

const sendTransfer = (trf) => {
    if (confirm(`Kirim pengiriman transfer stok #${trf.transfer_code}? Stok di outlet asal akan dipotong.`)) {
        router.post(route('stock-transfers.send', trf.id));
    }
};

const cancelTransfer = (trf) => {
    if (confirm(`Batalkan transfer stok #${trf.transfer_code}?`)) {
        router.post(route('stock-transfers.cancel', trf.id));
    }
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">
                            <i class="bx bx-transfer-alt text-primary me-2"></i>Transfer Stok Antar Outlet
                        </h2>
                        <p class="text-muted small mb-0">Kelola distribusi dan pengiriman stok produk antar lokasi cabang apotek.</p>
                    </div>
                    <Link :href="route('stock-transfers.create')" class="btn btn-primary shadow-sm rounded-3">
                        <i class="bx bx-plus me-1"></i> Buat Transfer Stok Baru
                    </Link>
                </div>

                <!-- Filter Toolbar -->
                <div class="card border-0 shadow-xs mb-4 rounded-3">
                    <div class="card-body p-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-secondary mb-1">Status Transfer</label>
                                <select class="form-select" v-model="statusFilter" @change="applyFilter">
                                    <option value="">Semua Status</option>
                                    <option value="DRAFT">DRAFT (Konsep)</option>
                                    <option value="IN_TRANSIT">IN_TRANSIT (Dalam Pengiriman)</option>
                                    <option value="RECEIVED">RECEIVED (Sudah Diterima)</option>
                                    <option value="CANCELLED">CANCELLED (Dibatalkan)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-secondary mb-1">Outlet Asal</label>
                                <select class="form-select" v-model="fromOutletFilter" @change="applyFilter">
                                    <option value="">Semua Outlet Asal</option>
                                    <option v-for="out in outlets" :key="out.id" :value="out.id">{{ out.name }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-secondary mb-1">Outlet Tujuan</label>
                                <select class="form-select" v-model="toOutletFilter" @change="applyFilter">
                                    <option value="">Semua Outlet Tujuan</option>
                                    <option v-for="out in outlets" :key="out.id" :value="out.id">{{ out.name }}</option>
                                </select>
                            </div>
                            <div class="col-md-3 text-end pt-3">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill font-monospace">
                                    Total: {{ transfers.total || 0 }} Transfer
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transfers Table -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-4 rounded-3 overflow-hidden shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>Kode Transfer</th>
                                    <th>Outlet Asal &rarr; Tujuan</th>
                                    <th>Dibuat Oleh / Tanggal</th>
                                    <th class="text-center">Jumlah Item</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 180px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="trf in transfers.data" :key="trf.id">
                                    <td><code class="fw-bold text-primary fs-6">{{ trf.transfer_code }}</code></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-light text-dark border">{{ trf.from_outlet?.name }}</span>
                                            <i class="bx bx-right-arrow-alt text-primary fs-5"></i>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">{{ trf.to_outlet?.name }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-dark">{{ trf.creator?.name || '-' }}</div>
                                        <div class="small text-muted font-monospace">{{ trf.created_at ? new Date(trf.created_at).toLocaleDateString('id-ID') : '-' }}</div>
                                    </td>
                                    <td class="text-center fw-bold">{{ trf.items?.length || 0 }} Produk</td>
                                    <td class="text-center">
                                        <span class="badge" :class="{
                                            'bg-secondary': trf.status === 'DRAFT',
                                            'bg-warning text-dark': trf.status === 'IN_TRANSIT' || trf.status === 'SENT',
                                            'bg-success': trf.status === 'RECEIVED',
                                            'bg-danger': trf.status === 'CANCELLED',
                                        }">
                                            {{ trf.status }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" @click="openDetailModal(trf)" title="Detail Items">
                                                <i class="bx bx-show"></i>
                                            </button>
                                            <button v-if="trf.status === 'DRAFT'" class="btn btn-outline-primary" @click="sendTransfer(trf)" title="Kirim Transfer">
                                                <i class="bx bx-send"></i> Kirim
                                            </button>
                                            <button v-if="trf.status === 'IN_TRANSIT' || trf.status === 'SENT'" class="btn btn-success" @click="openReceiveModal(trf)" title="Terima Transfer">
                                                <i class="bx bx-check-circle"></i> Terima
                                            </button>
                                            <button v-if="trf.status !== 'RECEIVED' && trf.status !== 'CANCELLED'" class="btn btn-outline-danger" @click="cancelTransfer(trf)" title="Batalkan">
                                                <i class="bx bx-x"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!transfers.data || transfers.data.length === 0">
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bx bx-transfer-alt fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                        Belum ada riwayat transfer stok ditemukan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <Pagination :links="transfers.links" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Modal Detail Transfer -->
        <div class="modal fade" id="detailTransferModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" v-if="selectedTransfer">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title fw-bold"><i class="bx bx-info-circle me-1"></i> Detail Transfer Stok #{{ selectedTransfer.transfer_code }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3 border-bottom pb-3">
                            <div class="col-md-6">
                                <small class="text-muted d-block">Outlet Asal (Pengirim):</small>
                                <strong class="text-dark fs-6">{{ selectedTransfer.from_outlet?.name }}</strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">Outlet Tujuan (Penerima):</small>
                                <strong class="text-primary fs-6">{{ selectedTransfer.to_outlet?.name }}</strong>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Status Transfer:</small>
                                <span class="badge" :class="selectedTransfer.status === 'RECEIVED' ? 'bg-success' : 'bg-warning text-dark'">
                                    {{ selectedTransfer.status }}
                                </span>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Pengirim (User):</small>
                                <span class="fw-semibold">{{ selectedTransfer.creator?.name || '-' }}</span>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Penerima (User):</small>
                                <span class="fw-semibold">{{ selectedTransfer.receiver?.name || '-' }}</span>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-2">Daftar Produk Ditransfer</h6>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama Obat</th>
                                        <th class="text-end">Qty Minta</th>
                                        <th class="text-end">Qty Kirim</th>
                                        <th class="text-end">Qty Terima</th>
                                        <th>Satuan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in selectedTransfer.items" :key="item.id">
                                        <td><code>{{ item.obat_id }}</code></td>
                                        <td class="fw-bold">{{ item.obat?.nama || item.obat_id }}</td>
                                        <td class="text-end">{{ item.qty_requested }}</td>
                                        <td class="text-end fw-semibold text-primary">{{ item.qty_sent }}</td>
                                        <td class="text-end fw-bold text-success">{{ item.qty_received }}</td>
                                        <td>{{ item.unit }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Terima Transfer -->
        <div class="modal fade" id="receiveTransferModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" v-if="receiveTransferData">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title fw-bold"><i class="bx bx-check-circle me-1"></i> Konfirmasi Penerimaan Stok #{{ receiveTransferData.transfer_code }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitReceive">
                        <div class="modal-body">
                            <p class="small text-muted mb-3">
                                Masukkan jumlah kuantitas fisik produk yang benar-benar diterima di outlet <strong>{{ receiveTransferData.to_outlet?.name }}</strong>.
                            </p>
                            <div class="table-responsive border rounded">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Produk</th>
                                            <th class="text-end">Qty Dikirim</th>
                                            <th class="text-end" style="width: 180px;">Qty Diterima <span class="text-danger">*</span></th>
                                            <th>Satuan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in receiveTransferData.items" :key="item.id">
                                            <td>
                                                <div class="fw-bold">{{ item.obat?.nama || item.obat_id }}</div>
                                                <code class="small text-muted">{{ item.obat_id }}</code>
                                            </td>
                                            <td class="text-end fw-bold text-primary">{{ item.qty_sent }}</td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control form-control-sm text-end fw-bold" v-model="receiveForm.received_items[item.id]" required min="0">
                                            </td>
                                            <td>{{ item.unit }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success fw-bold" :disabled="receiveForm.processing">Konfirmasi & Tambah Stok Outlet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
