<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    product: Object,
    allUnits: Array,
});

const emit = defineEmits(['close', 'updated']);

const loading = ref(false);
const savingPrimary = ref(false);
const savingConversion = ref(false);
const savingPrices = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

// Data state
const details = ref(null);
const priceHistories = ref([]);
const unitsList = ref(props.allUnits || []);

// Primary Units Form
const primaryForm = ref({
    satuan_dasar_id: null,
    satuan_pembelian_id: null,
    satuan_penjualan_id: null,
});

// New Conversion Form
const newConversion = ref({
    parent_unit_id: '',
    child_unit_id: '',
    conversion_rate: 10,
});

// Editable Unit Prices List
const editablePrices = ref([]);

const activeTab = ref('harga'); // 'harga', 'konversi', 'histori'

const fetchDetails = async () => {
    if (!props.product) return;
    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.get(route('admin.obat.units-prices.get', props.product.kode));
        if (response.data.success) {
            const data = response.data.data;
            details.value = data.details;
            priceHistories.value = data.price_histories || [];
            if (data.all_units) unitsList.value = data.all_units;

            primaryForm.value.satuan_dasar_id = data.satuan_dasar_id || props.product.satuan_dasar_id || null;
            primaryForm.value.satuan_pembelian_id = data.satuan_pembelian_id || props.product.satuan_pembelian_id || null;
            primaryForm.value.satuan_penjualan_id = data.satuan_penjualan_id || props.product.satuan_penjualan_id || null;

            // Prepare editable prices array
            if (data.details && data.details.unit_prices) {
                editablePrices.value = data.details.unit_prices.map(u => ({
                    unit_id: u.unit_id,
                    unit_name: u.unit_name,
                    conversion_factor: u.conversion_factor,
                    is_base_unit: u.is_base_unit,
                    is_purchase_unit: u.is_purchase_unit,
                    is_selling_unit: u.is_selling_unit,
                    purchase_price: u.purchase_price,
                    selling_price: u.selling_price,
                }));
            }
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Gagal mengambil detail harga & konversi.';
    } finally {
        loading.value = false;
    }
};

watch(() => props.show, (newVal) => {
    if (newVal && props.product) {
        fetchDetails();
    }
});

const savePrimaryUnits = async () => {
    savingPrimary.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const res = await axios.post(route('admin.obat.primary-units.store', props.product.kode), primaryForm.value);
        if (res.data.success) {
            successMessage.value = res.data.message;
            await fetchDetails();
            emit('updated');
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Gagal mengupdate satuan utama.';
    } finally {
        savingPrimary.value = false;
    }
};

const addConversion = async () => {
    savingConversion.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const res = await axios.post(route('admin.obat.conversion.store', props.product.kode), newConversion.value);
        if (res.data.success) {
            successMessage.value = res.data.message;
            newConversion.value.parent_unit_id = '';
            newConversion.value.child_unit_id = '';
            newConversion.value.conversion_rate = 10;
            await fetchDetails();
            emit('updated');
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Gagal menyimpan konversi satuan.';
    } finally {
        savingConversion.value = false;
    }
};

const deleteConversion = async (convId) => {
    if (!confirm('Apakah Anda yakin ingin menghapus aturan konversi ini?')) return;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const res = await axios.delete(route('admin.obat.conversion.destroy', [props.product.kode, convId]));
        if (res.data.success) {
            successMessage.value = res.data.message;
            await fetchDetails();
            emit('updated');
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Gagal menghapus konversi.';
    }
};

const savePrices = async () => {
    savingPrices.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const res = await axios.post(route('admin.obat.prices.store', props.product.kode), {
            prices: editablePrices.value
        });
        if (res.data.success) {
            successMessage.value = res.data.message;
            await fetchDetails();
            emit('updated');
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Gagal menyimpan harga per satuan.';
    } finally {
        savingPrices.value = false;
    }
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const calculateMargin = (sell, buy) => {
    const s = parseFloat(sell) || 0;
    const b = parseFloat(buy) || 0;
    return s - b;
};

const calculateMarginPercent = (sell, buy) => {
    const s = parseFloat(sell) || 0;
    const b = parseFloat(buy) || 0;
    if (b <= 0) return 0;
    return (((s - b) / b) * 100).toFixed(2);
};
</script>

<template>
    <div class="modal fade show d-block" tabindex="-1" v-if="show" style="background: rgba(0,0,0,0.6); z-index: 1060;">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <!-- Modal Header -->
                <div class="modal-header bg-primary text-white border-0 py-3 px-4 rounded-top-4">
                    <div>
                        <h5 class="modal-title fw-bold mb-0">
                            <i class="bx bx-purchase-tag-alt me-2"></i>Pengaturan Satuan & Harga Produk
                        </h5>
                        <p class="small text-white-50 mb-0" v-if="product">
                            {{ product.nama }} ({{ product.kode }})
                        </p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" @click="$emit('close')"></button>
                </div>

                <!-- Alert Messages -->
                <div class="px-4 pt-3" v-if="errorMessage || successMessage">
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 py-2 px-3 small" v-if="errorMessage">
                        <i class="bx bx-error-circle me-1"></i> {{ errorMessage }}
                        <button type="button" class="btn-close py-2" @click="errorMessage = ''"></button>
                    </div>
                    <div class="alert alert-success alert-dismissible fade show rounded-3 py-2 px-3 small" v-if="successMessage">
                        <i class="bx bx-check-circle me-1"></i> {{ successMessage }}
                        <button type="button" class="btn-close py-2" @click="successMessage = ''"></button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="modal-body p-4" v-if="loading">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-muted small mt-2">Memuat data konversi & harga...</p>
                    </div>
                </div>

                <div class="modal-body p-4" v-else>
                    <!-- SECTION 1: SET SATUAN UTAMA -->
                    <div class="card border-0 bg-light rounded-3 p-3 mb-4">
                        <h6 class="fw-bold text-dark mb-3">
                            <i class="bx bx-cog me-1 text-primary"></i> Satuan Utama Produk
                        </h6>
                        <form @submit.prevent="savePrimaryUnits">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-muted">Satuan Dasar (Stok)</label>
                                    <select class="form-select form-select-sm rounded-3" v-model="primaryForm.satuan_dasar_id" required>
                                        <option value="" disabled>Pilih Satuan Dasar</option>
                                        <option v-for="u in unitsList" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                    <span class="form-text small text-muted">Acuan penyimpanan stok fisik</span>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-muted">Satuan Pembelian (PO)</label>
                                    <select class="form-select form-select-sm rounded-3" v-model="primaryForm.satuan_pembelian_id" required>
                                        <option value="" disabled>Pilih Satuan PO</option>
                                        <option v-for="u in unitsList" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                    <span class="form-text small text-muted">Default PO ke Supplier/PBF</span>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-muted">Satuan Penjualan (POS)</label>
                                    <select class="form-select form-select-sm rounded-3" v-model="primaryForm.satuan_penjualan_id" required>
                                        <option value="" disabled>Pilih Satuan POS</option>
                                        <option v-for="u in unitsList" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                    <span class="form-text small text-muted">Default penjualan di kasir</span>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-3 px-3" :disabled="savingPrimary">
                                    <i class="bx bx-save me-1"></i> Simpan Satuan Utama
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- TABS NAVIGATION -->
                    <ul class="nav nav-tabs mb-4">
                        <li class="nav-item">
                            <button class="nav-link fw-semibold" :class="{ active: activeTab === 'harga' }" @click="activeTab = 'harga'">
                                <i class="bx bx-money me-1"></i> Penetapan Harga & Margin
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-semibold" :class="{ active: activeTab === 'konversi' }" @click="activeTab = 'konversi'">
                                <i class="bx bx-transfer me-1"></i> Konversi Satuan
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-semibold" :class="{ active: activeTab === 'histori' }" @click="activeTab = 'histori'">
                                <i class="bx bx-history me-1"></i> Histori Perubahan Harga
                            </button>
                        </li>
                    </ul>

                    <!-- TAB 1: HARGA & MARGIN -->
                    <div v-if="activeTab === 'harga'">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Daftar Harga Beli & Harga Jual Per Satuan</h6>
                                <p class="text-muted small mb-0">Tentukan harga beli dan harga jual masing-masing satuan kemasan.</p>
                            </div>
                            <button class="btn btn-sm btn-primary rounded-3 px-3" @click="savePrices" :disabled="savingPrices">
                                <i class="bx bx-check me-1"></i> Simpan Perubahan Harga
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light small text-uppercase">
                                    <tr>
                                        <th>Satuan</th>
                                        <th class="text-center">Faktor Konversi (Base)</th>
                                        <th style="width: 200px;">Harga Beli (Rp)</th>
                                        <th style="width: 200px;">Harga Jual (Rp)</th>
                                        <th class="text-end">Margin (Rp)</th>
                                        <th class="text-end">Margin (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in editablePrices" :key="item.unit_id">
                                        <td>
                                            <span class="fw-bold text-dark">{{ item.unit_name }}</span>
                                            <div class="d-flex gap-1 mt-1">
                                                <span class="badge bg-primary" v-if="item.is_base_unit">Satuan Dasar</span>
                                                <span class="badge bg-info" v-if="item.is_purchase_unit">PO Beli</span>
                                                <span class="badge bg-success" v-if="item.is_selling_unit">POS Jual</span>
                                            </div>
                                        </td>
                                        <td class="text-center font-monospace">
                                            1 {{ item.unit_name }} = {{ item.conversion_factor }} {{ details?.product?.satuan_dasar || 'Base' }}
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Rp</span>
                                                <input 
                                                    type="number" 
                                                    step="0.01" 
                                                    class="form-control text-end fw-semibold" 
                                                    v-model="item.purchase_price" 
                                                    placeholder="0"
                                                >
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Rp</span>
                                                <input 
                                                    type="number" 
                                                    step="0.01" 
                                                    class="form-control text-end fw-bold text-success" 
                                                    v-model="item.selling_price" 
                                                    placeholder="0"
                                                >
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold" :class="calculateMargin(item.selling_price, item.purchase_price) >= 0 ? 'text-success' : 'text-danger'">
                                            {{ formatRupiah(calculateMargin(item.selling_price, item.purchase_price)) }}
                                        </td>
                                        <td class="text-end fw-semibold">
                                            <span class="badge bg-light text-dark border">
                                                {{ calculateMarginPercent(item.selling_price, item.purchase_price) }}%
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!editablePrices || editablePrices.length === 0">
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            Belum ada satuan terdaftar. Silakan atur Satuan Utama terlebih dahulu.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 2: KONVERSI SATUAN -->
                    <div v-if="activeTab === 'konversi'">
                        <div class="card border p-3 rounded-3 mb-4">
                            <h6 class="fw-bold text-dark mb-3"><i class="bx bx-plus-circle me-1 text-success"></i>Tambah Aturan Konversi Satuan Baru</h6>
                            <form @submit.prevent="addConversion">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted">1 Satuan Induk (Parent)</label>
                                        <select class="form-select form-select-sm rounded-3" v-model="newConversion.parent_unit_id" required>
                                            <option value="" disabled>Pilih Parent Unit</option>
                                            <option v-for="u in unitsList" :key="u.id" :value="u.id">{{ u.name }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1 text-center mt-4">
                                        <span class="fw-bold fs-5">=</span>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted">Jumlah Isi (Rate)</label>
                                        <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm rounded-3" v-model="newConversion.conversion_rate" placeholder="Contoh: 10" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted">Satuan Anak (Child)</label>
                                        <select class="form-select form-select-sm rounded-3" v-model="newConversion.child_unit_id" required>
                                            <option value="" disabled>Pilih Child Unit</option>
                                            <option v-for="u in unitsList" :key="u.id" :value="u.id">{{ u.name }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mt-4 text-end">
                                        <button type="submit" class="btn btn-sm btn-success rounded-3 w-100" :disabled="savingConversion">
                                            + Tambah
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <h6 class="fw-bold mb-2 text-dark">Aturan Konversi Saat Ini:</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Perataan Konversi Produk</th>
                                        <th class="text-end" style="width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in details?.conversions" :key="c.id">
                                        <td>
                                            <span class="badge bg-primary me-2">1 {{ c.parent_unit_name }}</span>
                                            <i class="bx bx-right-arrow-alt align-middle me-2"></i>
                                            <span class="fw-bold text-dark">{{ c.conversion_rate }} {{ c.child_unit_name }}</span>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-light text-danger rounded-circle p-2" @click="deleteConversion(c.id)">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!details?.conversions || details?.conversions.length === 0">
                                        <td colspan="2" class="text-center py-4 text-muted small">
                                            Belum ada konversi bertingkat. (Misal: 1 Box = 10 Strip, 1 Strip = 10 Tablet).
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 3: HISTORI HARGA -->
                    <div v-if="activeTab === 'histori'">
                        <h6 class="fw-bold mb-3 text-dark">Catatan Audit Perubahan Harga</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tanggal & Waktu</th>
                                        <th>Satuan</th>
                                        <th>Jenis Harga</th>
                                        <th class="text-end">Harga Lama</th>
                                        <th class="text-end">Harga Baru</th>
                                        <th>User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="h in priceHistories" :key="h.id">
                                        <td class="text-muted">{{ new Date(h.created_at).toLocaleString('id-ID') }}</td>
                                        <td class="fw-bold text-dark">{{ h.unit ? h.unit.name : '-' }}</td>
                                        <td>
                                            <span class="badge" :class="h.price_type === 'PURCHASE' ? 'bg-info' : 'bg-success'">
                                                {{ h.price_type === 'PURCHASE' ? 'Harga Beli' : 'Harga Jual' }}
                                            </span>
                                        </td>
                                        <td class="text-end text-muted">{{ formatRupiah(h.old_price) }}</td>
                                        <td class="text-end fw-bold text-primary">{{ formatRupiah(h.new_price) }}</td>
                                        <td>{{ h.user ? h.user.name : 'System' }}</td>
                                    </tr>
                                    <tr v-if="!priceHistories || priceHistories.length === 0">
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            Belum ada histori perubahan harga.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer border-0 bg-light rounded-bottom-4 px-4">
                    <button type="button" class="btn btn-secondary rounded-3 px-4" @click="$emit('close')">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</template>
