<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    prescriptions: Object,
    customers: Array,
    doctors: Array,
    medicines: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

const handleFilter = () => {
    router.get(route('prescriptions.index'), {
        search: search.value,
        status: statusFilter.value,
    }, { preserveState: true, replace: true });
};

// Create Form
const createForm = useForm({
    customer_id: '',
    doctor_id: '',
    prescription_date: new Date().toISOString().slice(0, 10),
    notes: '',
    status: 'verified',
    items: [
        { medicine_id: '', dosage: '500mg', frequency: '3x1 Sehari', duration: '5 Hari', quantity: 1, instructions: 'Diminum sesudah makan' }
    ]
});

const addCreateItem = () => {
    createForm.items.push({ medicine_id: '', dosage: '500mg', frequency: '3x1 Sehari', duration: '5 Hari', quantity: 1, instructions: 'Diminum sesudah makan' });
};

const removeCreateItem = (idx) => {
    if (createForm.items.length > 1) {
        createForm.items.splice(idx, 1);
    }
};

const submitPrescription = () => {
    createForm.post(route('prescriptions.store'), {
        onSuccess: () => {
            createForm.reset();
            const modalEl = document.getElementById('tambahRxModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    });
};

// Detail Modal State
const selectedRx = ref(null);
const rxItems = ref([]);
const isLoadingDetail = ref(false);

const openDetailModal = async (rx) => {
    selectedRx.value = rx;
    rxItems.value = rx.items || [];
    isLoadingDetail.value = true;

    try {
        const res = await axios.get(route('prescriptions.show', rx.id));
        if (res.data.success) {
            selectedRx.value = res.data.data.prescription;
            rxItems.value = res.data.data.items;
        }
    } catch (e) {
        console.error('Failed to fetch prescription details', e);
    } finally {
        isLoadingDetail.value = false;
    }

    const modal = new bootstrap.Modal(document.getElementById('detailRxModal'));
    modal.show();
};

// Edit Form & Modal State
const editForm = useForm({
    id: null,
    customer_id: '',
    doctor_id: '',
    prescription_date: '',
    notes: '',
    status: 'verified',
    items: []
});

const openEditModal = (rx) => {
    editForm.id = rx.id;
    editForm.customer_id = rx.customer_id || '';
    editForm.doctor_id = rx.doctor_id || '';
    editForm.prescription_date = rx.prescription_date;
    editForm.notes = rx.notes || '';
    editForm.status = rx.status || 'verified';
    editForm.items = (rx.items || []).map(i => ({
        medicine_id: i.medicine_id,
        dosage: i.dosage || '500mg',
        frequency: i.frequency || '3x1 Sehari',
        duration: i.duration || '5 Hari',
        quantity: i.quantity || 1,
        instructions: i.instructions || 'Diminum sesudah makan'
    }));

    if (editForm.items.length === 0) {
        editForm.items.push({ medicine_id: '', dosage: '500mg', frequency: '3x1 Sehari', duration: '5 Hari', quantity: 1, instructions: 'Diminum sesudah makan' });
    }

    const modal = new bootstrap.Modal(document.getElementById('editRxModal'));
    modal.show();
};

const addEditItem = () => {
    editForm.items.push({ medicine_id: '', dosage: '500mg', frequency: '3x1 Sehari', duration: '5 Hari', quantity: 1, instructions: 'Diminum sesudah makan' });
};

const removeEditItem = (idx) => {
    if (editForm.items.length > 1) {
        editForm.items.splice(idx, 1);
    }
};

const submitUpdatePrescription = () => {
    editForm.put(route('prescriptions.update', editForm.id), {
        onSuccess: () => {
            const modalEl = document.getElementById('editRxModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    });
};

// Delete Action
const deletePrescription = (rx) => {
    Swal.fire({
        title: 'Hapus Resep Dokter?',
        text: `Resep ${rx.prescription_number} akan dihapus secara permanen.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('prescriptions.destroy', rx.id));
        }
    });
};

// Redirect to POS with Prescription pre-loaded
const processInPOS = (rxId) => {
    router.get(route('pos.index'), { rx_id: rxId });
};

const getStatusBadge = (status) => {
    const st = (status || '').toLowerCase();
    switch(st) {
        case 'created': return 'bg-warning text-dark';
        case 'verified': return 'bg-success text-white';
        case 'dispensed': return 'bg-info text-dark';
        case 'completed': return 'bg-primary text-white';
        default: return 'bg-secondary text-white';
    }
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">

                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-notepad text-primary me-2"></i>Resep Dokter & Validasi Apoteker</h2>
                        <p class="text-muted small mb-0">Manajemen Resep Dokter, Penyiapan Obat, dan Integrasi Transaksi Kasir POS</p>
                    </div>

                    <button class="btn btn-primary shadow-sm fw-bold px-4" data-bs-toggle="modal" data-bs-target="#tambahRxModal">
                        <i class="bx bx-plus me-1"></i> + Input Resep Baru
                    </button>
                </div>

                <!-- Table & Filter Bar -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden shadow-xs">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div class="d-flex gap-2 align-items-center">
                            <div class="input-group input-group-sm" style="max-width: 260px;">
                                <span class="input-group-text bg-light"><i class="bx bx-search"></i></span>
                                <input type="text" class="form-control" v-model="search" @keyup.enter="handleFilter" placeholder="Cari No. Resep / Pasien / Dokter...">
                            </div>

                            <select class="form-select form-select-sm" style="width: 170px;" v-model="statusFilter" @change="handleFilter">
                                <option value="">Semua Status</option>
                                <option value="created">CREATED (Draf)</option>
                                <option value="verified">VERIFIED (Terverifikasi)</option>
                                <option value="dispensed">DISPENSED (Diambil)</option>
                                <option value="completed">COMPLETED (Selesai)</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>No. Resep</th>
                                    <th>Pasien / Pelanggan</th>
                                    <th>Dokter Penanggung Jawab</th>
                                    <th>Apoteker Verifikator</th>
                                    <th>Tanggal Resep</th>
                                    <th class="text-center">Total Item</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="rx in prescriptions.data" :key="rx.id">
                                    <td class="fw-bold text-primary font-monospace">{{ rx.prescription_number }}</td>
                                    <td class="fw-bold text-dark">{{ rx.patient_name || 'Pasien Umum / Non-Member' }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ rx.doctor_name || 'Dokter Spesialis' }}</div>
                                        <small class="text-muted">{{ rx.doctor_specialty || 'Dokter Umum' }}</small>
                                    </td>
                                    <td>{{ rx.pharmacist_name || 'Apoteker System' }}</td>
                                    <td>{{ rx.prescription_date }}</td>
                                    <td class="text-center fw-bold">{{ rx.items ? rx.items.length : 0 }} Item</td>
                                    <td>
                                        <span :class="['badge px-2 py-1', getStatusBadge(rx.status)]">
                                            {{ (rx.status || 'VERIFIED').toUpperCase() }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <button @click="openDetailModal(rx)" class="btn btn-sm btn-outline-info fw-semibold" title="Lihat Detail Resep">
                                                <i class="bx bx-show"></i> Detail
                                            </button>
                                            <button @click="openEditModal(rx)" class="btn btn-sm btn-outline-warning fw-semibold" title="Edit Resep">
                                                <i class="bx bx-edit"></i> Edit
                                            </button>
                                            <button @click="processInPOS(rx.id)" class="btn btn-sm btn-success fw-bold" title="Proses Penjualan di Kasir">
                                                <i class="bx bx-cart"></i> POS
                                            </button>
                                            <button @click="deletePrescription(rx)" class="btn btn-sm btn-outline-danger" title="Hapus Resep">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!prescriptions.data || prescriptions.data.length === 0">
                                    <td colspan="8" class="text-center py-4 text-muted">Belum ada resep terdaftar dalam sistem.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links -->
                    <Pagination :links="prescriptions.links" />
                </div>
            </div>
        </section>

        <!-- ===== MODAL INPUT RESEP BARU ===== -->
        <div class="modal fade" id="tambahRxModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bx bx-notepad me-2"></i>Input & Validasi Resep Dokter</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitPrescription">
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Pasien / Pelanggan</label>
                                    <select class="form-select" v-model="createForm.customer_id">
                                        <option value="">-- Non-Member / Pasien Umum --</option>
                                        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.phone || 'No HP -' }})</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Dokter Penulis Resep</label>
                                    <select class="form-select" v-model="createForm.doctor_id">
                                        <option value="">-- Dokter Mandiri / Umum --</option>
                                        <option v-for="d in doctors" :key="d.id" :value="d.id">{{ d.name }} ({{ d.specialty || 'Dokter' }})</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Tanggal Resep</label>
                                    <input type="date" class="form-control" v-model="createForm.prescription_date" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Catatan / Keterangan Khusus</label>
                                    <input type="text" class="form-control" v-model="createForm.notes" placeholder="Catatan alergi / riwayat penyakit...">
                                </div>
                            </div>

                            <h6 class="fw-bold border-bottom pb-2 mt-4 mb-3 text-primary">
                                <i class="bx bx-capsule me-1"></i>Rincian Obat Resep & Aturan Pakai
                            </h6>

                            <div v-for="(item, idx) in createForm.items" :key="idx" class="border rounded-3 p-3 mb-3 bg-light position-relative">
                                <button type="button" v-if="createForm.items.length > 1" @click="removeCreateItem(idx)" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 px-2 py-0">
                                    &times;
                                </button>
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-4">
                                        <label class="small text-muted fw-bold">Obat / Produk</label>
                                        <select class="form-select form-select-sm" v-model="item.medicine_id" required>
                                            <option value="">-- Pilih Obat --</option>
                                            <option v-for="m in medicines" :key="m.kode" :value="m.kode">{{ m.nama }} (Stok: {{ m.stok }})</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-muted fw-bold">Frekuensi</label>
                                        <input type="text" class="form-control form-control-sm" v-model="item.frequency" placeholder="3x1 Sehari">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-muted fw-bold">Dosis / Durasi</label>
                                        <input type="text" class="form-control form-control-sm" v-model="item.duration" placeholder="5 Hari">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-muted fw-bold">Jumlah Qty</label>
                                        <input type="number" class="form-control form-control-sm" v-model.number="item.quantity" required min="1">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-muted fw-bold">Instruksi</label>
                                        <input type="text" class="form-control form-control-sm" v-model="item.instructions" placeholder="Sesudah makan">
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline-primary btn-sm mb-2 fw-semibold" @click="addCreateItem">
                                <i class="bx bx-plus me-1"></i>+ Tambah Obat Resep
                            </button>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary fw-bold" :disabled="createForm.processing">
                                <i class="bx bx-check-shield me-1"></i>Verifikasi & Simpan Resep
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===== MODAL DETAIL RESEP ===== -->
        <div class="modal fade" id="detailRxModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" v-if="selectedRx">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">
                            <i class="bx bx-notepad me-2"></i>Detail Resep {{ selectedRx.prescription_number }}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 bg-light">
                                    <small class="text-muted d-block text-uppercase fw-bold mb-1">Informasi Pasien</small>
                                    <h6 class="fw-bold text-dark mb-1">{{ selectedRx.patient_name || 'Pasien Umum / Non-Member' }}</h6>
                                    <div class="small text-muted"><i class="bx bx-phone me-1"></i>{{ selectedRx.patient_phone || '-' }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 bg-light">
                                    <small class="text-muted d-block text-uppercase fw-bold mb-1">Dokter & Verifikator</small>
                                    <h6 class="fw-bold text-dark mb-1">{{ selectedRx.doctor_name || 'Dokter Penanggung Jawab' }}</h6>
                                    <div class="small text-muted"><i class="bx bx-user-check me-1"></i>Apoteker: {{ selectedRx.pharmacist_name || 'System' }}</div>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedRx.sale_invoice_number" class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center gap-2">
                            <i class="bx bx-check-circle fs-4 text-info"></i>
                            <div>
                                <strong>Telah Diproses di Kasir POS!</strong> Invoice Pembayaran: <strong class="font-monospace text-primary">{{ selectedRx.sale_invoice_number }}</strong>
                            </div>
                        </div>

                        <h6 class="fw-bold text-dark mb-3"><i class="bx bx-capsule text-primary me-2"></i>Daftar Obat & Dosis Resep</h6>

                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama Obat</th>
                                    <th class="text-center">Frekuensi</th>
                                    <th class="text-center">Durasi</th>
                                    <th class="text-center">Jumlah Qty</th>
                                    <th>Instruksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="it in rxItems" :key="it.id">
                                    <td>
                                        <div class="fw-bold text-dark">{{ it.medicine_name }}</div>
                                        <small class="text-muted font-monospace">{{ it.medicine_id }}</small>
                                    </td>
                                    <td class="text-center"><span class="badge bg-light text-dark border">{{ it.frequency || '3x1 Sehari' }}</span></td>
                                    <td class="text-center">{{ it.duration || '-' }}</td>
                                    <td class="text-center fw-bold text-primary fs-6">{{ it.quantity }} {{ it.medicine_unit || 'PCS' }}</td>
                                    <td>{{ it.instructions || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button @click="processInPOS(selectedRx.id)" class="btn btn-success fw-bold" data-bs-dismiss="modal">
                            <i class="bx bx-cart me-1"></i> Proses Transaksi Penjualan di POS
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== MODAL EDIT RESEP ===== -->
        <div class="modal fade" id="editRxModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title fw-bold"><i class="bx bx-edit me-2"></i>Edit Resep Dokter</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitUpdatePrescription">
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Pasien / Pelanggan</label>
                                    <select class="form-select" v-model="editForm.customer_id">
                                        <option value="">-- Non-Member / Pasien Umum --</option>
                                        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Dokter Penulis Resep</label>
                                    <select class="form-select" v-model="editForm.doctor_id">
                                        <option value="">-- Dokter Mandiri / Umum --</option>
                                        <option v-for="d in doctors" :key="d.id" :value="d.id">{{ d.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Tanggal Resep</label>
                                    <input type="date" class="form-control" v-model="editForm.prescription_date" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Status Resep</label>
                                    <select class="form-select" v-model="editForm.status">
                                        <option value="created">CREATED (Draf)</option>
                                        <option value="verified">VERIFIED (Terverifikasi)</option>
                                        <option value="dispensed">DISPENSED (Telah Diambil)</option>
                                        <option value="completed">COMPLETED (Selesai)</option>
                                    </select>
                                </div>
                            </div>

                            <h6 class="fw-bold border-bottom pb-2 mt-4 mb-3 text-warning">
                                <i class="bx bx-capsule me-1"></i>Rincian Obat Resep & Aturan Pakai
                            </h6>

                            <div v-for="(item, idx) in editForm.items" :key="idx" class="border rounded-3 p-3 mb-3 bg-light position-relative">
                                <button type="button" v-if="editForm.items.length > 1" @click="removeEditItem(idx)" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 px-2 py-0">
                                    &times;
                                </button>
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-4">
                                        <label class="small text-muted fw-bold">Obat</label>
                                        <select class="form-select form-select-sm" v-model="item.medicine_id" required>
                                            <option value="">-- Pilih Obat --</option>
                                            <option v-for="m in medicines" :key="m.kode" :value="m.kode">{{ m.nama }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-muted fw-bold">Frekuensi</label>
                                        <input type="text" class="form-control form-control-sm" v-model="item.frequency">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-muted fw-bold">Durasi</label>
                                        <input type="text" class="form-control form-control-sm" v-model="item.duration">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-muted fw-bold">Jumlah Qty</label>
                                        <input type="number" class="form-control form-control-sm" v-model.number="item.quantity" required min="1">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-muted fw-bold">Instruksi</label>
                                        <input type="text" class="form-control form-control-sm" v-model="item.instructions">
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline-warning btn-sm mb-2 fw-semibold" @click="addEditItem">
                                <i class="bx bx-plus me-1"></i>+ Tambah Item
                            </button>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-warning fw-bold" :disabled="editForm.processing">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </LegacyLayout>
</template>
