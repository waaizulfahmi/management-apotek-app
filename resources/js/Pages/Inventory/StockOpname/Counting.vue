<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    opname: Object,
    items: Array,
    progress: Number,
    counted_count: Number,
    total_items: Number,
});

const barcodeInput = ref(null);
const physicalInput = ref(null);

const scanQuery = ref('');
const activeItem = ref(props.items[0] || null);
const physicalStockVal = ref(activeItem.value ? activeItem.value.physical_stock : 0);
const reasonVal = ref('');
const notesVal = ref('');
const isSaving = ref(false);
const saveStatus = ref('✓ Ready');
const showShortcuts = ref(false);

const filterOnlyDiff = ref(false);

const filteredItems = computed(() => {
    if (filterOnlyDiff.value) {
        return props.items.filter(i => i.difference !== 0);
    }
    return props.items;
});

const focusBarcode = () => {
    if (barcodeInput.value) barcodeInput.value.focus();
};

const focusPhysical = () => {
    if (physicalInput.value) physicalInput.value.focus();
};

const handleScan = async () => {
    if (!scanQuery.value) return;
    try {
        const res = await axios.get(route('inventory.opname.scan', { id: props.opname.id, barcode: scanQuery.value }));
        if (res.data.success) {
            selectItem(res.data.data);
            scanQuery.value = '';
            focusPhysical();
        }
    } catch (err) {
        alert(err.response?.data?.message || 'Barcode / Obat tidak ditemukan!');
        scanQuery.value = '';
        focusBarcode();
    }
};

const selectItem = (item) => {
    activeItem.value = item;
    physicalStockVal.value = item.physical_stock;
    reasonVal.value = item.reason || '';
    notesVal.value = item.notes || '';
    focusPhysical();
};

const saveCount = async () => {
    if (!activeItem.value) return;
    isSaving.value = true;
    saveStatus.value = 'Saving...';
    try {
        const payload = {
            item_id: activeItem.value.id,
            physical_stock: physicalStockVal.value,
            reason: reasonVal.value,
            notes: notesVal.value,
        };

        const res = await axios.post(route('inventory.opname.count', props.opname.id), payload);
        if (res.data.success) {
            saveStatus.value = '✓ Saved';
            activeItem.value.physical_stock = res.data.data.physical_stock;
            activeItem.value.difference = res.data.data.difference;
            activeItem.value.match_status = res.data.data.match_status;
            activeItem.value.count_status = 'counted';

            // Auto-next to next uncounted item
            const nextUncounted = props.items.find(i => i.count_status === 'pending');
            if (nextUncounted) {
                selectItem(nextUncounted);
            } else {
                focusBarcode();
            }
        }
    } catch (err) {
        saveStatus.value = '⚠ Error Saving';
        alert('Gagal menyimpan stok fisik');
    } finally {
        isSaving.value = false;
    }
};

// Global Keyboard Shortcuts Listener (F2, F4, F6, Enter, ESC)
const handleKeydown = (e) => {
    if (e.key === 'F2') {
        e.preventDefault();
        focusBarcode();
    } else if (e.key === 'F4') {
        e.preventDefault();
        saveCount();
    } else if (e.key === 'F6') {
        e.preventDefault();
        if (activeItem.value) selectItem(activeItem.value);
    } else if (e.key === '?') {
        showShortcuts.value = !showShortcuts.value;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    focusBarcode();
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});

const submitToReview = () => {
    if (confirm("Apakah Anda yakin ingin menyelesaikan counting dan mengajukan SO untuk Review/Approval?")) {
        router.post(route('inventory.opname.submit', props.opname.id));
    }
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <!-- Counting Mode Top Bar -->
                <div class="card border-0 shadow-sm p-3 mb-3 bg-white rounded-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="badge bg-primary me-2">COUNTING MODE SO CEPAT</span>
                            <h4 class="fw-bold d-inline align-middle mb-0">{{ opname.opname_number }}</h4>
                            <span class="text-muted ms-2 small">| Gudang: <strong>{{ opname.warehouse_name }}</strong></span>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <span class="small font-monospace fw-bold" :class="saveStatus.includes('Error') ? 'text-danger' : 'text-success'">
                                {{ saveStatus }}
                            </span>
                            <button class="btn btn-outline-secondary btn-sm" @click="showShortcuts = !showShortcuts">
                                <i class="bx bx-key me-1"></i> Shortcuts (?)
                            </button>
                            <button class="btn btn-success btn-sm fw-bold px-3" @click="submitToReview">
                                Selesai Counting & Submit Review →
                            </button>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-bold">Progress Penghitungan Fisik</span>
                            <span>{{ counted_count }} / {{ total_items }} Item ({{ progress }}%)</span>
                        </div>
                        <div class="progress" style="height: 10px; border-radius: 6px;">
                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" :style="{ width: progress + '%' }"></div>
                        </div>
                    </div>
                </div>

                <!-- Main Counting Layout -->
                <div class="row">
                    <!-- Left Column: Scan & Active Item Card -->
                    <div class="col-md-5">
                        <div class="card border-0 shadow-sm p-4 mb-3 bg-white text-center rounded-3">
                            <label class="form-label fw-bold text-start"><i class="bx bx-barcode-reader text-primary me-1"></i>Scan Barcode / Kode Obat (F2 Focus)</label>
                            <div class="input-group mb-4">
                                <input type="text" ref="barcodeInput" class="form-control form-control-lg fw-bold text-primary" v-model="scanQuery" @keyup.enter="handleScan" placeholder="Scan Barcode Di Sini...">
                                <button class="btn btn-primary" @click="handleScan">SCAN</button>
                            </div>

                            <template v-if="activeItem">
                                <div class="bg-light p-3 rounded-3 text-start border">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h5 class="fw-bold mb-0 text-dark">{{ activeItem.medicine_name }}</h5>
                                            <small class="text-muted">Barcode: {{ activeItem.barcode }} | Batch: <strong>{{ activeItem.batch_number || '-' }}</strong></small>
                                        </div>
                                        <span class="badge" :class="{
                                            'bg-success': activeItem.match_status === 'MATCH',
                                            'bg-danger': activeItem.match_status === 'SHORTAGE',
                                            'bg-warning text-dark': activeItem.match_status === 'SURPLUS'
                                        }">
                                            {{ activeItem.match_status }}
                                        </span>
                                    </div>

                                    <div class="row g-2 text-center my-3">
                                        <div class="col-6">
                                            <div class="p-2 border rounded bg-white">
                                                <small class="text-muted d-block">Stok Sistem (Snapshot)</small>
                                                <h4 class="fw-bold mb-0 text-dark">{{ activeItem.system_stock }}</h4>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 border rounded bg-white">
                                                <small class="text-muted d-block">Selisih Hitung</small>
                                                <h4 class="fw-bold mb-0" :class="activeItem.difference !== 0 ? 'text-danger' : 'text-success'">
                                                    {{ activeItem.difference > 0 ? '+' : '' }}{{ activeItem.difference }}
                                                </h4>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Physical Input Form -->
                                    <form @submit.prevent="saveCount">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold text-primary">Stok Fisik Nyata (Input & Press Enter / F4)</label>
                                            <input type="number" ref="physicalInput" class="form-control form-control-lg text-center fw-bold fs-3 text-primary border-primary" v-model.number="physicalStockVal" required>
                                        </div>

                                        <div class="mb-3" v-if="physicalStockVal !== activeItem.system_stock">
                                            <label class="form-label small text-muted">Alasan Selisih</label>
                                            <select class="form-select form-select-sm" v-model="reasonVal">
                                                <option value="Pemeriksaan Rutin">Pemeriksaan Rutin</option>
                                                <option value="Salah Input">Salah Input</option>
                                                <option value="Rusak">Rusak</option>
                                                <option value="Hilang">Hilang</option>
                                                <option value="Expired">Expired</option>
                                            </select>
                                        </div>

                                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm" :disabled="isSaving">
                                            <i class="bx bx-check me-1"></i> SIMPAN HASIL (ENTER)
                                        </button>
                                    </form>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Right Column: SO Items List Table -->
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm p-3 bg-white rounded-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0"><i class="bx bx-list-check me-1"></i>Daftar Obat dalam SO Scope</h6>
                                <div class="form-check form-switch small">
                                    <input class="form-check-input" type="checkbox" id="filterDiff" v-model="filterOnlyDiff">
                                    <label class="form-check-label fw-bold" for="filterDiff">Hanya Tampilkan Selisih</label>
                                </div>
                            </div>

                            <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                                <table class="table table-hover table-sm align-middle small">
                                    <thead class="bg-light sticky-top">
                                        <tr>
                                            <th>Nama Obat</th>
                                            <th>Batch</th>
                                            <th class="text-center">Sistem</th>
                                            <th class="text-center">Fisik</th>
                                            <th class="text-center">Selisih</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in filteredItems" :key="item.id" @click="selectItem(item)" class="cursor-pointer" :class="{ 'table-active border-start border-4 border-primary': activeItem?.id === item.id }">
                                            <td class="fw-bold">{{ item.medicine_name }}</td>
                                            <td><small class="text-muted">{{ item.batch_number || '-' }}</small></td>
                                            <td class="text-center">{{ item.system_stock }}</td>
                                            <td class="text-center fw-bold">{{ item.physical_stock }}</td>
                                            <td class="text-center fw-bold" :class="item.difference !== 0 ? 'text-danger' : 'text-success'">
                                                {{ item.difference > 0 ? '+' : '' }}{{ item.difference }}
                                            </td>
                                            <td class="text-center">
                                                <span class="badge" :class="{
                                                    'bg-success': item.match_status === 'MATCH',
                                                    'bg-danger': item.match_status === 'SHORTAGE',
                                                    'bg-warning text-dark': item.match_status === 'SURPLUS'
                                                }">
                                                    {{ item.match_status }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Keyboard Shortcuts Help Modal -->
        <div class="modal fade" id="shortcutHelpModal" :class="{ 'show d-block': showShortcuts }" tabindex="-1" style="background: rgba(0,0,0,0.5);" v-if="showShortcuts">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="bx bx-key me-2"></i>Keyboard Shortcuts Helper</h5>
                        <button type="button" class="btn-close btn-close-white" @click="showShortcuts = false"></button>
                    </div>
                    <div class="modal-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between"><span>Focus Input Barcode</span><kbd>F2</kbd></li>
                            <li class="list-group-item d-flex justify-content-between"><span>Simpan Hasil Physical Stock</span><kbd>F4 / ENTER</kbd></li>
                            <li class="list-group-item d-flex justify-content-between"><span>Recount / Refresh Item</span><kbd>F6</kbd></li>
                            <li class="list-group-item d-flex justify-content-between"><span>Tampilkan Shortcuts Ini</span><kbd>?</kbd></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
