<script setup>
import { ref, computed, watch } from 'vue';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    outlets: Array,
    activeOutletId: Number,
    obats: Array,
});

const form = useForm({
    from_outlet_id: props.activeOutletId || (props.outlets && props.outlets[0]?.id),
    to_outlet_id: props.outlets && props.outlets.length > 1 ? props.outlets[1]?.id : '',
    notes: '',
    submit_type: 'SENT',
    items: [
        { obat_id: '', qty: 1, unit: 'PCS', notes: '' }
    ]
});

const addItem = () => {
    form.items.push({ obat_id: '', qty: 1, unit: 'PCS', notes: '' });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const getObatStock = (obatKode) => {
    const obat = props.obats.find(o => o.kode === obatKode);
    return obat ? (obat.current_stock ?? 0) : 0;
};

const submitTransfer = (type) => {
    form.submit_type = type;
    form.post(route('stock-transfers.store'));
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">
                            <i class="bx bx-plus-circle text-primary me-2"></i>Buat Transfer Stok Antar Outlet
                        </h2>
                        <p class="text-muted small mb-0">Pilih outlet pengirim, outlet penerima, dan kuantitas produk yang akan didistribusikan.</p>
                    </div>
                    <Link :href="route('stock-transfers.index')" class="btn btn-outline-secondary">
                        <i class="bx bx-arrow-back me-1"></i> Kembali ke Daftar Transfer
                    </Link>
                </div>

                <form @submit.prevent>
                    <!-- Outlet Picker Header Card -->
                    <div class="card border-0 shadow-xs mb-4 rounded-3">
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label fw-bold text-dark">Outlet Asal (Pengirim) <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-lg" v-model="form.from_outlet_id" required>
                                        <option v-for="out in outlets" :key="out.id" :value="out.id">
                                            {{ out.name }} {{ out.is_main ? '(Pusat)' : '' }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
                                    <div class="bg-light rounded-circle p-3 shadow-xs">
                                        <i class="bx bx-right-arrow-alt fs-2 text-primary"></i>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-bold text-dark">Outlet Tujuan (Penerima) <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-lg" v-model="form.to_outlet_id" required>
                                        <option v-for="out in outlets" :key="out.id" :value="out.id" :disabled="out.id === form.from_outlet_id">
                                            {{ out.name }} {{ out.is_main ? '(Pusat)' : '' }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-12 mt-3">
                                    <label class="form-label font-semibold">Catatan / Keterangan Transfer</label>
                                    <input type="text" class="form-control" v-model="form.notes" placeholder="Contoh: Distribusi penyeimbangan stok rutin bulanan">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transfer Items Table Card -->
                    <div class="card border-0 shadow-xs mb-4 rounded-3">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bx bx-list-check me-2 text-primary"></i>Daftar Produk yang Ditransfer</h5>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" @click="addItem">
                                <i class="bx bx-plus me-1"></i> Tambah Baris Produk
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th>Pilih Produk <span class="text-danger">*</span></th>
                                            <th class="text-end" style="width: 140px;">Stok Asal</th>
                                            <th class="text-end" style="width: 150px;">Qty Transfer <span class="text-danger">*</span></th>
                                            <th style="width: 120px;">Satuan</th>
                                            <th>Catatan Item</th>
                                            <th class="text-center" style="width: 60px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, index) in form.items" :key="index">
                                            <td class="fw-bold text-muted text-center">{{ index + 1 }}</td>
                                            <td>
                                                <select class="form-select" v-model="item.obat_id" required>
                                                    <option value="" disabled>-- Pilih Produk Obat --</option>
                                                    <option v-for="o in obats" :key="o.kode" :value="o.kode">
                                                        {{ o.nama }} ({{ o.kode }})
                                                    </option>
                                                </select>
                                            </td>
                                            <td class="text-end fw-bold" :class="getObatStock(item.obat_id) > 0 ? 'text-success' : 'text-danger'">
                                                {{ item.obat_id ? Number(getObatStock(item.obat_id)).toLocaleString('id-ID') : '-' }}
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control text-end fw-bold" v-model="item.qty" required min="0.01">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" v-model="item.unit" placeholder="PCS">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" v-model="item.notes" placeholder="Catatan item...">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle" @click="removeItem(index)" :disabled="form.items.length <= 1">
                                                    <i class="bx bx-trash fs-5"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-white p-3 d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-outline-primary btn-sm" @click="addItem">
                                <i class="bx bx-plus me-1"></i> Tambah Item Lain
                            </button>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary" @click="submitTransfer('DRAFT')" :disabled="form.processing">
                                    <i class="bx bx-save me-1"></i> Simpan Sebagai Draft
                                </button>
                                <button type="button" class="btn btn-primary fw-bold" @click="submitTransfer('SENT')" :disabled="form.processing">
                                    <i class="bx bx-send me-1"></i> Kirim Transfer Stok Now
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </LegacyLayout>
</template>
