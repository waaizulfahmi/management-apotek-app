<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    opname: Object,
    items: Object,
    categories: Array,
    summary: Object,
    filters: Object,
});

const isDraft = computed(() => {
    const status = (props.opname?.status || '').toUpperCase();
    return status === 'DRAFT' || status === 'COUNTING';
});

// Filters
const searchQuery = ref(props.filters?.search || '');
const categoryFilter = ref(props.filters?.category || '');
const countStatusFilter = ref(props.filters?.count_status || '');
const diffStatusFilter = ref(props.filters?.diff_status || '');

let debounceTimer = null;
const debouncedSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(
            route('opname.show', props.opname.id),
            {
                search: searchQuery.value,
                category: categoryFilter.value,
                count_status: countStatusFilter.value,
                diff_status: diffStatusFilter.value,
            },
            { preserveState: true, replace: true }
        );
    }, 300);
};

watch([searchQuery, categoryFilter, countStatusFilter, diffStatusFilter], () => {
    debouncedSearch();
});

// Real-time summary computed from page items
const currentSummary = computed(() => {
    const list = props.items?.data || [];

    let totalItems = props.summary?.total_items || list.length;
    let countedItems = 0;
    let itemsMatched = 0;
    let itemsSurplus = 0;
    let itemsDeficit = 0;
    let totalQtySystem = 0;
    let totalQtyPhysical = 0;
    let systemTotalValue = 0;
    let physicalTotalValue = 0;
    let surplusQtyTotal = 0;
    let deficitQtyTotal = 0;

    list.forEach(item => {
        const sysStock = Number(item.system_stock) || 0;
        const physStock = item.physical_stock !== null && item.physical_stock !== undefined ? Number(item.physical_stock) : 0;
        const price = Number(item.unit_price) || 0;
        const diff = physStock - sysStock;

        totalQtySystem += sysStock;
        systemTotalValue += sysStock * price;

        if (item.is_counted) {
            countedItems++;
            totalQtyPhysical += physStock;
            physicalTotalValue += physStock * price;
            if (diff === 0) itemsMatched++;
            else if (diff > 0) { itemsSurplus++; surplusQtyTotal += diff; }
            else { itemsDeficit++; deficitQtyTotal += Math.abs(diff); }
        }
    });

    return {
        total_items: totalItems,
        counted_items: countedItems,
        items_matched: itemsMatched,
        items_surplus: itemsSurplus,
        items_deficit: itemsDeficit,
        total_qty_system: totalQtySystem,
        total_qty_physical: totalQtyPhysical,
        system_total_value: systemTotalValue,
        physical_total_value: physicalTotalValue,
        difference_value: physicalTotalValue - systemTotalValue,
        surplus_qty_total: surplusQtyTotal,
        deficit_qty_total: deficitQtyTotal,
    };
});

const savingItemId = ref(null);

const handleStockChange = async (item) => {
    if (!isDraft.value) return;
    item.is_counted = true;
    item.physical_stock = (item.physical_stock === '' || item.physical_stock === null || item.physical_stock === undefined) ? 0 : Number(item.physical_stock);
    if (item.physical_stock < 0) item.physical_stock = 0;
    item.difference = item.physical_stock - item.system_stock;
    item.difference_value = item.difference * item.unit_price;

    savingItemId.value = item.id;
    try {
        const res = await axios.post(route('opname.update_item', props.opname.id), {
            item_id: item.id,
            physical_stock: item.physical_stock,
            reason: item.reason || '',
            notes: item.notes || '',
        });
        if (res.data.success) {
            item.physical_stock = res.data.data.physical_stock;
            item.difference = res.data.data.difference;
            item.difference_value = res.data.data.difference_value;
            item.is_counted = true;
        }
    } catch (err) {
        console.error('Failed to update stock', err);
    } finally {
        savingItemId.value = null;
    }
};

// Mark All Counted
const markAllForm = useForm({});
const submitMarkAllCounted = () => {
    Swal.fire({
        title: 'Tandai Semua Sudah Dihitung?',
        text: 'Stok fisik seluruh barang yang belum diinput akan diset sama dengan stok sistem.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Tandai Semua!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3b6bff',
        cancelButtonColor: '#64748b',
        customClass: { popup: 'rounded-4 shadow-lg border-0' }
    }).then((res) => {
        if (res.isConfirmed) markAllForm.post(route('opname.mark_all_counted', props.opname.id));
    });
};

// Finalize SO
const showFinalModal = ref(false);
const finalizeForm = useForm({});
const submitFinalizeSO = () => {
    finalizeForm.post(route('opname.finalize', props.opname.id), {
        onSuccess: () => {
            showFinalModal.value = false;
            Swal.fire({
                title: 'Stok Opname Berhasil Difinalisasi!',
                text: `Dokumen ${props.opname.opname_number} berhasil difinalisasi. Stok sistem telah diperbarui.`,
                icon: 'success',
                confirmButtonText: 'MANTAP, OK!',
                confirmButtonColor: '#10b981',
                customClass: { popup: 'rounded-4 shadow-lg border-0' }
            });
        },
        onError: (err) => {
            Swal.fire({
                title: 'Gagal Memproses Final SO!',
                text: err?.message || 'Terjadi kesalahan saat memproses finalisasi.',
                icon: 'error',
                confirmButtonText: 'Tutup',
                confirmButtonColor: '#ef4444',
                customClass: { popup: 'rounded-4 shadow-lg border-0' }
            });
        }
    });
};

// Cancel SO
const cancelForm = useForm({});
const submitCancelSO = () => {
    Swal.fire({
        title: 'Batalkan Dokumen Stok Opname?',
        text: 'Sesi Stok Opname ini akan diubah statusnya menjadi CANCELLED.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Batalkan!',
        cancelButtonText: 'Kembali',
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#64748b',
        customClass: { popup: 'rounded-4 shadow-lg border-0' }
    }).then((res) => {
        if (res.isConfirmed) cancelForm.post(route('opname.cancel', props.opname.id));
    });
};

// Delete SO
const submitDeleteSO = () => {
    Swal.fire({
        title: 'Hapus Dokumen Stok Opname?',
        text: `Dokumen ${props.opname.opname_number} dan seluruh item akan dihapus!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus Sekarang!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        customClass: { popup: 'rounded-4 shadow-lg border-0' }
    }).then((res) => {
        if (res.isConfirmed) router.delete(route('opname.destroy', props.opname.id));
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};

const formatCurrency = (amount) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);

const reasonsList = ['Barang rusak','Barang hilang','Salah input','Salah pencatatan','Barang expired','Kesalahan penerimaan','Lainnya'];
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4 pb-5">
            <div class="content mt-4">

                <!-- ===== HEADER CARD ===== -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px; overflow: hidden;">
                    <div style="height: 4px; background: linear-gradient(90deg, #3b6bff 0%, #10b981 100%);"></div>
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <!-- Left -->
                            <div class="d-flex align-items-center gap-3">
                                <Link :href="route('opname.index')"
                                    class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center"
                                    style="width: 42px; height: 42px; flex-shrink: 0;">
                                    <i class="bx bx-arrow-back fs-5"></i>
                                </Link>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        <span class="badge bg-primary fw-bold font-monospace px-3 py-2" style="font-size: 1rem;">
                                            {{ opname.opname_number }}
                                        </span>
                                        <span v-if="isDraft" class="badge bg-warning text-dark fw-bold px-3 py-2">
                                            <i class="bx bx-pencil me-1"></i> DRAFT – Sedang Input
                                        </span>
                                        <span v-else-if="(opname.status||'').toUpperCase() === 'COMPLETED'" class="badge bg-success fw-bold px-3 py-2">
                                            <i class="bx bx-check-circle me-1"></i> COMPLETED – Sudah Final
                                        </span>
                                        <span v-else class="badge bg-secondary fw-bold px-3 py-2">{{ opname.status }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-4 text-muted" style="font-size: 0.88rem;">
                                        <span><i class="bx bx-calendar me-1 text-primary"></i>{{ formatDate(opname.opname_date) }}</span>
                                        <span><i class="bx bx-user me-1 text-primary"></i>{{ opname.user?.name || '-' }}</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Right: Actions -->
                            <div class="d-flex gap-2 flex-wrap">
                                <button v-if="isDraft" @click="submitMarkAllCounted" :disabled="markAllForm.processing"
                                    class="btn btn-outline-primary fw-semibold">
                                    <i class="bx bx-check-double me-1"></i> Tandai Semua Dihitung
                                </button>
                                <button v-if="isDraft" @click="submitCancelSO" :disabled="cancelForm.processing"
                                    class="btn btn-outline-warning fw-semibold">
                                    <i class="bx bx-x-circle me-1"></i> Batalkan SO
                                </button>
                                <button @click="submitDeleteSO" class="btn btn-outline-danger fw-semibold">
                                    <i class="bx bx-trash me-1"></i> Hapus SO
                                </button>
                                <button v-if="isDraft" @click="showFinalModal = true"
                                    class="btn btn-success fw-bold px-4 shadow-sm">
                                    <i class="bx bx-check-shield me-1"></i> PROSES FINAL SO
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== DRAFT ALERT ===== -->
                <div v-if="isDraft" class="alert alert-warning border-0 mb-4 d-flex align-items-center gap-3">
                    <i class="bx bx-info-circle fs-3 flex-shrink-0"></i>
                    <div>
                        <strong class="d-block fs-6">Mode Penginputan Stok Fisik (DRAFT)</strong>
                        <small>Stok sistem <strong>TIDAK BERUBAH</strong> selama proses input fisik.
                        Stok baru disesuaikan otomatis setelah menekan <strong class="text-success">PROSES FINAL SO</strong>.</small>
                    </div>
                </div>

                <!-- ===== MINI SUMMARY CARDS ===== -->
                <div class="row g-3 mb-4">
                    <!-- Total Item + Progress -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-left: 4px solid #6366f1 !important;">
                            <div class="card-body p-3">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Jenis Barang</small>
                                <div class="d-flex align-items-end gap-2">
                                    <span class="fw-bold text-dark" style="font-size: 2rem; line-height: 1;">{{ currentSummary.total_items }}</span>
                                    <span class="text-muted mb-1">item</span>
                                </div>
                                <div class="progress mt-2" style="height: 6px; border-radius: 99px;">
                                    <div class="progress-bar bg-primary"
                                        :style="`width: ${currentSummary.total_items > 0 ? Math.round(currentSummary.counted_items / currentSummary.total_items * 100) : 0}%`">
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Dihitung: <strong class="text-primary">{{ currentSummary.counted_items }}</strong>
                                    / {{ currentSummary.total_items }}
                                </small>
                            </div>
                        </div>
                    </div>
                    <!-- Hasil -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-left: 4px solid #10b981 !important;">
                            <div class="card-body p-3">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Hasil Penghitungan</small>
                                <div class="d-flex align-items-end gap-2 mb-2">
                                    <span class="fw-bold text-success" style="font-size: 2rem; line-height: 1;">{{ currentSummary.items_matched }}</span>
                                    <span class="text-muted mb-1">item sesuai</span>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="badge fw-semibold" style="background: rgba(239,68,68,0.12); color: #dc2626;">
                                        ▼ Kurang: {{ currentSummary.items_deficit }}
                                    </span>
                                    <span class="badge fw-semibold" style="background: rgba(59,130,246,0.12); color: #2563eb;">
                                        ▲ Lebih: {{ currentSummary.items_surplus }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Total Qty -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-left: 4px solid #f59e0b !important;">
                            <div class="card-body p-3">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Qty Fisik</small>
                                <div class="d-flex align-items-end gap-2">
                                    <span class="fw-bold text-dark" style="font-size: 2rem; line-height: 1;">{{ currentSummary.total_qty_physical }}</span>
                                    <span class="text-muted mb-1">pcs</span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Sistem: <strong>{{ currentSummary.total_qty_system }}</strong> pcs
                                </small>
                            </div>
                        </div>
                    </div>
                    <!-- Selisih Nilai -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;"
                            :style="`border-left: 4px solid ${currentSummary.difference_value === 0 ? '#10b981' : (currentSummary.difference_value < 0 ? '#ef4444' : '#3b6bff')} !important;`">
                            <div class="card-body p-3">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Selisih Nilai</small>
                                <div class="fw-bold mb-1"
                                    :class="currentSummary.difference_value === 0 ? 'text-success' : (currentSummary.difference_value < 0 ? 'text-danger' : 'text-primary')"
                                    style="font-size: 1.1rem;">
                                    {{ formatCurrency(currentSummary.difference_value) }}
                                </div>
                                <small class="text-muted d-block">Nilai Fisik: {{ formatCurrency(currentSummary.physical_total_value) }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== FILTER BAR ===== -->
                <div class="card border-0 shadow-sm p-3 mb-3" style="border-radius: 12px;">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 border">
                                    <i class="bx bx-search text-muted"></i>
                                </span>
                                <input type="text" v-model="searchQuery"
                                    class="form-control border-start-0"
                                    placeholder="Cari nama obat, kode barcode...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select v-model="categoryFilter" class="form-select">
                                <option value="">🏷️ Semua Kategori</option>
                                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select v-model="countStatusFilter" class="form-select">
                                <option value="">📋 Semua Status</option>
                                <option value="pending">⏳ Belum Dihitung</option>
                                <option value="counted">✅ Sudah Dihitung</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select v-model="diffStatusFilter" class="form-select">
                                <option value="">🔍 Semua Selisih</option>
                                <option value="diff">⚠️ Ada Selisih (≠ 0)</option>
                                <option value="match">✅ Sesuai (= 0)</option>
                                <option value="shortage">🔴 Kurang (Minus)</option>
                                <option value="surplus">🔵 Lebih (Plus)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ===== MAIN TABLE ===== -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; overflow: hidden;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                            <thead style="background: #1e293b; color: #e2e8f0;">
                                <tr>
                                    <th class="py-3 px-3 text-center" style="width: 42px;">#</th>
                                    <th class="py-3 px-3">Nama Produk</th>
                                    <th class="py-3 px-3">Supplier / Merk</th>
                                    <th class="py-3 px-3 text-center" style="width: 72px;">Satuan</th>
                                    <th class="py-3 px-3 text-end" style="width: 115px;">Harga</th>
                                    <th class="py-3 px-3 text-center" style="width: 95px;">
                                        <i class="bx bx-desktop me-1"></i>Sistem
                                    </th>
                                    <th class="py-3 px-3 text-center" style="width: 120px; background: rgba(16,185,129,0.18);">
                                        <i class="bx bx-edit me-1"></i>Fisik [Input]
                                    </th>
                                    <th class="py-3 px-3 text-center" style="width: 82px;">Selisih</th>
                                    <th class="py-3 px-3 text-end" style="width: 130px;">Nilai Fisik</th>
                                    <th class="py-3 px-3" style="width: 175px;">Alasan Selisih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in items.data" :key="item.id"
                                    :class="isDraft && !item.is_counted ? 'row-draft' : (item.is_counted && item.difference === 0 ? 'row-counted-matched' : '')">

                                    <!-- No -->
                                    <td class="text-center px-3 text-muted">
                                        <small>{{ (items.current_page - 1) * items.per_page + idx + 1 }}</small>
                                    </td>

                                    <!-- Produk -->
                                    <td class="px-3">
                                        <div class="fw-semibold text-dark" style="font-size: 0.9rem;">{{ item.medicine?.nama || item.medicine_id }}</div>
                                        <small class="text-muted font-monospace" style="font-size: 0.75rem;">{{ item.medicine_id }}</small>
                                        <div v-if="item.batch" class="mt-1">
                                            <span class="badge bg-secondary font-monospace" style="font-size: 0.72rem;">
                                                {{ item.batch?.batch_number }}
                                            </span>
                                            <span class="text-muted ms-1" style="font-size: 0.75rem;">{{ item.batch?.expired_date }}</span>
                                        </div>
                                    </td>

                                    <!-- Supplier / Merk -->
                                    <td class="px-3">
                                        <div class="text-primary fw-semibold" style="font-size: 0.82rem;">
                                            <i class="bx bx-store me-1"></i>{{ item.supplier_name || item.medicine?.supplier_name || item.medicine?.supplier?.name || '—' }}
                                        </div>
                                        <span v-if="item.merk || item.medicine?.merk"
                                            class="badge bg-light text-dark border mt-1" style="font-size: 0.72rem;">
                                            {{ item.merk || item.medicine?.merk }}
                                        </span>
                                    </td>

                                    <!-- Satuan -->
                                    <td class="text-center px-3">
                                        <span class="badge" style="background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; font-size: 0.78rem;">
                                            {{ item.unit_name }}
                                        </span>
                                    </td>

                                    <!-- Harga -->
                                    <td class="text-end px-3 text-muted font-monospace" style="font-size: 0.8rem;">
                                        {{ formatCurrency(item.unit_price) }}
                                    </td>

                                    <!-- Stok Sistem -->
                                    <td class="text-center px-3">
                                        <span class="fw-bold text-dark" style="font-size: 1.15rem;">{{ item.system_stock }}</span>
                                    </td>

                                    <!-- Stok Fisik -->
                                    <td class="text-center px-3" style="background: rgba(16,185,129,0.05);">
                                        <div v-if="isDraft">
                                            <input
                                                type="number"
                                                v-model.number="item.physical_stock"
                                                min="0"
                                                @change="handleStockChange(item)"
                                                class="form-control form-control-sm font-monospace fw-bold text-center mx-auto"
                                                :class="item.is_counted ? 'border-success' : 'border-warning'"
                                                style="width: 82px; font-size: 1rem; border-width: 2px;"
                                            >
                                        </div>
                                        <span v-else class="fw-bold text-dark font-monospace" style="font-size: 1rem;">
                                            {{ item.physical_stock }}
                                        </span>
                                    </td>

                                    <!-- Selisih -->
                                    <td class="text-center px-3">
                                        <span v-if="!item.is_counted"
                                            class="badge rounded-pill"
                                            style="background: #f1f5f9; color: #94a3b8; font-size: 0.75rem;">
                                            Belum
                                        </span>
                                        <span v-else-if="item.difference === 0"
                                            class="badge rounded-pill fw-bold"
                                            style="background: rgba(16,185,129,0.15); color: #059669; font-size: 0.82rem;">
                                            ✓ 0
                                        </span>
                                        <span v-else
                                            class="badge rounded-pill fw-bold"
                                            :class="item.difference < 0 ? 'bg-danger' : 'bg-primary'"
                                            style="font-size: 0.82rem; min-width: 50px;">
                                            {{ item.difference > 0 ? '+' : '' }}{{ item.difference }}
                                        </span>
                                    </td>

                                    <!-- Nilai Fisik -->
                                    <td class="text-end px-3">
                                        <span v-if="item.is_counted" class="fw-semibold text-dark font-monospace" style="font-size: 0.82rem;">
                                            {{ formatCurrency(item.physical_stock * item.unit_price) }}
                                        </span>
                                        <span v-else class="text-muted">—</span>
                                    </td>

                                    <!-- Alasan -->
                                    <td class="px-3">
                                        <div v-if="item.is_counted && item.difference !== 0">
                                            <select v-if="isDraft" v-model="item.reason"
                                                @change="handleStockChange(item)"
                                                class="form-select form-select-sm mb-1">
                                                <option value="">— Pilih Alasan —</option>
                                                <option v-for="r in reasonsList" :key="r" :value="r">{{ r }}</option>
                                            </select>
                                            <span v-else class="badge bg-warning text-dark" style="font-size: 0.78rem;">
                                                {{ item.reason || 'Penyesuaian SO' }}
                                            </span>
                                            <input v-if="isDraft && item.reason === 'Lainnya'"
                                                type="text" v-model="item.notes"
                                                @change="handleStockChange(item)"
                                                placeholder="Catatan tambahan..."
                                                class="form-control form-control-sm mt-1">
                                            <small v-if="!isDraft && item.notes" class="text-muted d-block mt-1">{{ item.notes }}</small>
                                        </div>
                                        <span v-else-if="item.is_counted && item.difference === 0" class="text-success fw-semibold" style="font-size: 0.82rem;">
                                            <i class="bx bx-check me-1"></i>Sesuai
                                        </span>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                </tr>

                                <tr v-if="!items.data || items.data.length === 0">
                                    <td colspan="10" class="text-center py-5">
                                        <i class="bx bx-package fs-1 text-muted d-block mb-2"></i>
                                        <span class="text-muted">Tidak ada barang ditemukan.</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links -->
                    <div class="px-4 pb-3">
                        <Pagination
                            :links="items.links"
                            :from="items.from"
                            :to="items.to"
                            :total="items.total"
                        />
                    </div>
                </div>

            </div>
        </section>

        <!-- ===== STICKY BOTTOM SUMMARY BAR ===== -->
        <div class="fixed-bottom mx-3 mb-3" style="z-index: 1040; pointer-events: none;">
            <div class="card border-0 shadow-lg"
                style="border-radius: 16px; background: #0f172a; pointer-events: all; border: 1.5px solid #334155 !important; box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;">
                <div class="card-body py-3 px-4">
                    <div class="row align-items-center g-3">

                        <!-- Progress bar -->
                        <div class="col-lg-3 border-end border-secondary pe-3">
                            <span class="text-uppercase fw-bold d-block mb-1" style="font-size: 0.85rem; letter-spacing: 0.05em; color: #cbd5e1;">PROGRESS PENGHITUNGAN</span>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height: 10px; border-radius: 99px; background: #1e293b;">
                                    <div class="progress-bar bg-success"
                                        :style="`width: ${currentSummary.total_items > 0 ? Math.round(currentSummary.counted_items / currentSummary.total_items * 100) : 0}%; border-radius: 99px;`">
                                    </div>
                                </div>
                                <span class="text-white fw-bold" style="font-size: 1.05rem; min-width: 44px;">
                                    {{ currentSummary.total_items > 0 ? Math.round(currentSummary.counted_items / currentSummary.total_items * 100) : 0 }}%
                                </span>
                            </div>
                            <small class="mt-1 d-block fw-semibold" style="font-size: 0.88rem; color: #94a3b8;">
                                <strong class="text-white">{{ currentSummary.counted_items }}</strong> / {{ currentSummary.total_items }} item dihitung
                            </small>
                        </div>

                        <!-- Hasil -->
                        <div class="col-lg-3 border-end border-secondary px-3">
                            <span class="text-uppercase fw-bold d-block mb-2" style="font-size: 0.85rem; letter-spacing: 0.05em; color: #cbd5e1;">HASIL PENGHITUNGAN</span>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge py-2 px-3 fw-bold" style="background: rgba(16,185,129,0.25); color: #34d399; font-size: 0.92rem;">
                                    ✓ Sesuai: {{ currentSummary.items_matched }}
                                </span>
                                <span class="badge py-2 px-3 fw-bold" style="background: rgba(239,68,68,0.25); color: #f87171; font-size: 0.92rem;">
                                    ▼ Kurang: {{ currentSummary.items_deficit }} ({{ currentSummary.deficit_qty_total }} qty)
                                </span>
                                <span class="badge py-2 px-3 fw-bold" style="background: rgba(59,130,246,0.25); color: #93c5fd; font-size: 0.92rem;">
                                    ▲ Lebih: {{ currentSummary.items_surplus }} (+{{ currentSummary.surplus_qty_total }} qty)
                                </span>
                            </div>
                        </div>

                        <!-- Nilai -->
                        <div class="col-lg-4 border-end border-secondary px-3">
                            <span class="text-uppercase fw-bold d-block mb-1" style="font-size: 0.85rem; letter-spacing: 0.05em; color: #cbd5e1;">NILAI BARANG (RP)</span>
                            <div class="d-flex gap-4">
                                <div>
                                    <div style="font-size: 0.82rem; color: #94a3b8;" class="fw-semibold">Sistem</div>
                                    <div class="text-white fw-bold font-monospace" style="font-size: 1.05rem;">{{ formatCurrency(currentSummary.system_total_value) }}</div>
                                </div>
                                <div>
                                    <div style="font-size: 0.82rem; color: #94a3b8;" class="fw-semibold">Fisik</div>
                                    <div class="fw-bold font-monospace" style="font-size: 1.05rem; color: #34d399;">{{ formatCurrency(currentSummary.physical_total_value) }}</div>
                                </div>
                                <div>
                                    <div style="font-size: 0.82rem; color: #94a3b8;" class="fw-semibold">Selisih</div>
                                    <div class="fw-bold font-monospace" style="font-size: 1.05rem;"
                                        :class="currentSummary.difference_value === 0 ? 'text-success' : (currentSummary.difference_value < 0 ? 'text-danger' : 'text-primary')">
                                        {{ formatCurrency(currentSummary.difference_value) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CTA -->
                        <div class="col-lg-2 text-end ps-3">
                            <button v-if="isDraft" @click="showFinalModal = true"
                                class="btn btn-success fw-bold w-100 py-2 px-3 fs-6 shadow-sm">
                                <i class="bx bx-check-circle me-1"></i> FINAL SO
                            </button>
                            <span v-else class="badge bg-success px-3 py-2 d-block text-center fs-6" style="font-size: 0.95rem;">
                                <i class="bx bx-lock me-1"></i> LOCKED
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== MODAL FINALISASI ===== -->
        <div v-if="showFinalModal" class="modal fade show d-block" tabindex="-1"
            style="background: rgba(0,0,0,0.6);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                    <div class="modal-header text-white border-0"
                        style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
                        <h5 class="modal-title fw-bold">
                            <i class="bx bx-check-shield me-2"></i>Konfirmasi Finalisasi Stok Opname
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="showFinalModal = false"></button>
                    </div>
                    <form @submit.prevent="submitFinalizeSO">
                        <div class="modal-body p-4">
                            <!-- Warning -->
                            <div class="alert alert-warning border-0 d-flex gap-3 align-items-start mb-4">
                                <i class="bx bx-error-circle fs-3 flex-shrink-0 mt-1"></i>
                                <div>
                                    <strong class="fs-6">Perhatian!</strong>
                                    <p class="mb-0 small mt-1">Setelah SO difinalisasi,
                                    <strong>stok sistem dan kartu stok akan disesuaikan otomatis</strong>.
                                    Dokumen SO ini akan <strong>terkunci</strong> dan tidak dapat diedit kembali.</p>
                                </div>
                            </div>

                            <!-- Summary Grid -->
                            <div class="p-4 mb-4 summary-box-light" style="border-radius: 12px;">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="bx bx-bar-chart me-2 text-primary"></i>
                                    Ringkasan Hasil SO — {{ opname.opname_number }}
                                </h6>
                                <div class="row g-3">
                                    <div class="col-6 col-md-3">
                                        <div class="text-center p-3 rounded-3 stat-box-blue">
                                            <div class="fw-bold text-primary" style="font-size: 1.8rem;">{{ currentSummary.total_items }}</div>
                                            <small class="text-muted">Total Item</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-center p-3 rounded-3 stat-box-green">
                                            <div class="fw-bold text-success" style="font-size: 1.8rem;">{{ currentSummary.items_matched }}</div>
                                            <small class="text-muted">Sesuai</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-center p-3 rounded-3 stat-box-red">
                                            <div class="fw-bold text-danger" style="font-size: 1.8rem;">{{ currentSummary.items_deficit }}</div>
                                            <small class="text-muted">Kurang ({{ currentSummary.deficit_qty_total }} qty)</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-center p-3 rounded-3 stat-box-blue">
                                            <div class="fw-bold text-primary" style="font-size: 1.8rem;">{{ currentSummary.items_surplus }}</div>
                                            <small class="text-muted">Lebih (+{{ currentSummary.surplus_qty_total }} qty)</small>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <hr class="my-2">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                            <div>
                                                <small class="text-muted d-block">Nilai Sistem</small>
                                                <strong class="font-monospace text-dark">{{ formatCurrency(currentSummary.system_total_value) }}</strong>
                                            </div>
                                            <i class="bx bx-right-arrow-alt text-muted fs-4"></i>
                                            <div>
                                                <small class="text-muted d-block">Nilai Fisik</small>
                                                <strong class="font-monospace text-success">{{ formatCurrency(currentSummary.physical_total_value) }}</strong>
                                            </div>
                                            <div class="ms-auto text-end">
                                                <small class="text-muted d-block">Total Selisih Nilai</small>
                                                <strong class="font-monospace"
                                                    style="font-size: 1.4rem;"
                                                    :class="currentSummary.difference_value === 0 ? 'text-success' : (currentSummary.difference_value < 0 ? 'text-danger' : 'text-primary')">
                                                    {{ formatCurrency(currentSummary.difference_value) }}
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <p class="fw-bold text-center text-dark mb-0">
                                Apakah Anda yakin ingin memfinalisasi Stok Opname ini?
                            </p>
                        </div>

                        <div class="modal-footer border-0 bg-light px-4 py-3">
                            <button type="button" class="btn btn-outline-secondary px-4" @click="showFinalModal = false">
                                Batal
                            </button>
                            <button type="submit" :disabled="finalizeForm.processing"
                                class="btn btn-success btn-lg fw-bold px-5 shadow-sm">
                                <span v-if="finalizeForm.processing">
                                    <span class="spinner-border spinner-border-sm me-2"></span>Memproses...
                                </span>
                                <span v-else>
                                    <i class="bx bx-check-circle me-1"></i> YA, FINALISASI SEKARANG
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </LegacyLayout>
</template>
