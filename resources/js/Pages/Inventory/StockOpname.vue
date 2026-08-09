<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    opnames: Object,
    medicines: Array,
});

const createForm = useForm({
    opname_date: new Date().toISOString().slice(0, 10),
    notes: '',
    items: [
        { medicine_id: '', physical_stock: 0, reason: 'Pemeriksaan Rutin' }
    ]
});

const addItem = () => {
    createForm.items.push({ medicine_id: '', physical_stock: 0, reason: 'Pemeriksaan Rutin' });
};

const submitOpname = () => {
    createForm.post(route('inventory.opname.store'), {
        onSuccess: () => {
            createForm.reset();
            const modalEl = document.getElementById('tambahOpnameModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark mb-0"><i class="bx bx-task text-primary me-2"></i>Inventory & Stock Opname Fisik</h2>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tambahOpnameModal">
                        <i class="bx bx-plus"></i> Catat Stock Opname Baru
                    </button>
                </div>

                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>No. Opname</th>
                                <th>Tanggal</th>
                                <th>Petugas Auditing</th>
                                <th>Status</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="o in opnames.data" :key="o.id">
                                <td class="fw-bold text-primary">{{ o.opname_number }}</td>
                                <td>{{ o.opname_date }}</td>
                                <td>{{ o.user_name }}</td>
                                <td><span class="badge bg-success">{{ o.status.toUpperCase() }}</span></td>
                                <td>{{ o.notes || '-' }}</td>
                            </tr>
                            <tr v-if="!opnames.data || opnames.data.length === 0">
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada riwayat stock opname.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Modal Tambah Opname -->
        <div class="modal fade" id="tambahOpnameModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Input Stock Opname Fisik & Penyesuaian Stok</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitOpname">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Tanggal Pelaksanaan Opname</label>
                                <input type="date" class="form-control" v-model="createForm.opname_date" required>
                            </div>

                            <h6 class="fw-bold border-bottom pb-2 mt-4 mb-3">Penyesuaian Jumlah Stok Fisik Gula / Obat</h6>

                            <div v-for="(item, idx) in createForm.items" :key="idx" class="row g-2 align-items-center mb-3 bg-light p-2 rounded">
                                <div class="col-md-5">
                                    <label class="small text-muted">Pilih Obat</label>
                                    <select class="form-select form-select-sm" v-model="item.medicine_id" required>
                                        <option value="">-- Pilih Obat --</option>
                                        <option v-for="m in medicines" :key="m.kode" :value="m.kode">{{ m.nama }} (Stok Sistem: {{ m.stok }})</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">Stok Fisik Nyata</label>
                                    <input type="number" class="form-control form-control-sm" v-model.number="item.physical_stock" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="small text-muted">Alasan Selisih</label>
                                    <input type="text" class="form-control form-control-sm" v-model="item.reason" placeholder="Kecelakaan/Rusak/Expired">
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" @click="addItem">+ Tambah Obat Opname</button>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="createForm.processing">Disetujui & Adjust Stok</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
