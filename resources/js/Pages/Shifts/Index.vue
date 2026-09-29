<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    shifts: Object,
    outlets: Array,
    cashiers: Array,
    activeShift: Object,
    masterShifts: Array,
    filters: Object,
});

const cashierFilter = ref(props.filters?.cashier_id || '');
const statusFilter = ref(props.filters?.status || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');

let debounceTimer = null;
const debouncedSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(
            route('shifts.index'),
            {
                cashier_id: cashierFilter.value,
                status: statusFilter.value,
                start_date: startDate.value,
                end_date: endDate.value,
            },
            { preserveState: true, replace: true }
        );
    }, 300);
};

watch([cashierFilter, statusFilter, startDate, endDate], () => {
    debouncedSearch();
});

// Digital Clock for Real-time Shift Mismatch Check
const currentTimeStr = ref(new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));
let clockTimer = null;
onMounted(() => {
    clockTimer = setInterval(() => {
        currentTimeStr.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }, 10000);
});
onUnmounted(() => {
    if (clockTimer) clearInterval(clockTimer);
});

// Auto-detect default Master Shift based on current time
const getInitialMasterShift = () => {
    if (!props.masterShifts || props.masterShifts.length === 0) return null;
    const now = new Date();
    const curStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0') + ':00';

    const matched = props.masterShifts.find(s => {
        const start = s.start_time;
        const end = s.end_time;
        if (start <= end) {
            return curStr >= start && curStr <= end;
        } else {
            return curStr >= start || curStr <= end;
        }
    });

    return matched ? matched : props.masterShifts[0];
};

const initialShift = getInitialMasterShift();

// Modal Buka Shift State
const showOpenModal = ref(false);
const openForm = useForm({
    master_shift_id: initialShift ? initialShift.id : '',
    shift_name: initialShift ? initialShift.name : 'Shift Pagi',
    opening_cash: 500000,
    outlet_id: props.outlets && props.outlets.length > 0 ? props.outlets[0].id : '',
});

const selectedMasterShift = computed(() => {
    if (!props.masterShifts) return null;
    return props.masterShifts.find(s => s.id === openForm.master_shift_id);
});

// Watch when master shift selected changes to sync shift_name
watch(() => openForm.master_shift_id, (newId) => {
    const s = props.masterShifts?.find(x => x.id === newId);
    if (s) {
        openForm.shift_name = s.name;
    }
});

// Live Check: Current Time vs Selected Master Shift Schedule
const isShiftTimeMismatch = computed(() => {
    if (!selectedMasterShift.value) return false;
    const now = new Date();
    const curStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0') + ':00';

    const start = selectedMasterShift.value.start_time;
    const end = selectedMasterShift.value.end_time;

    if (start <= end) {
        return curStr < start || curStr > end;
    } else {
        return curStr < start && curStr > end;
    }
});

const submitOpenShift = () => {
    openForm.post(route('shifts.open'), {
        onSuccess: () => {
            showOpenModal.value = false;
        }
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatTimeOnly = (timeStr) => {
    if (!timeStr) return '-';
    return timeStr.substring(0, 5) + ' WIB';
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
};

const getStatusBadge = (status) => {
    switch(status) {
        case 'OPEN': return 'bg-success';
        case 'CLOSED': return 'bg-secondary';
        case 'FORCE CLOSED': return 'bg-danger';
        default: return 'bg-secondary';
    }
};

const getDifferenceBadge = (diff) => {
    if (diff === 0) return 'text-success fw-bold';
    if (diff < 0) return 'text-danger fw-bold';
    return 'text-primary fw-bold';
};

const isCurrentShiftOvertimeOrMismatch = computed(() => {
    if (!props.activeShift) return false;

    const now = new Date();
    const curStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0') + ':00';

    let master = props.activeShift.master_shift;
    if (!master && props.masterShifts) {
        const sName = (props.activeShift.shift_name || '').toLowerCase().trim();
        master = props.masterShifts.find(m => {
            const mName = m.name.toLowerCase().trim();
            return mName === sName || mName.includes(sName) || sName.includes(mName);
        });
    }

    if (master) {
        const start = master.start_time;
        const end = master.end_time;
        if (start <= end) {
            return curStr < start || curStr > end;
        } else {
            return curStr < start && curStr > end;
        }
    } else {
        const name = (props.activeShift.shift_name || '').toLowerCase();
        if (name.includes('pagi')) {
            return curStr < '07:00:00' || curStr > '15:00:00';
        } else if (name.includes('siang')) {
            return curStr < '15:00:00' || curStr > '22:00:00';
        } else if (name.includes('malam')) {
            return curStr < '22:00:00' && curStr > '07:00:00';
        }
    }

    return false;
});
</script>

<template>
    <Head title="Manajemen Shift Kasir" />
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">
                            <i class="bx bx-time-five text-primary me-2"></i>Manajemen Shift Kasir POS
                        </h2>
                        <p class="text-muted small mb-0">Kelola pembukaan shift operasional, modal kasir, dan integrasi jadwal Master Shift.</p>
                    </div>

                    <div class="d-flex gap-2">
                        <Link :href="route('master-shifts.index')" class="btn btn-outline-secondary fw-semibold shadow-xs">
                            <i class="bx bx-cog me-1"></i> Kelola Master Shift
                        </Link>

                        <button v-if="!activeShift" @click="showOpenModal = true" class="btn btn-primary fw-bold shadow-sm">
                            <i class="bx bx-plus-circle me-1"></i> Buka Shift Baru
                        </button>
                        <Link v-else :href="route('shifts.show', activeShift.id)" class="btn fw-bold shadow-sm" :class="isCurrentShiftOvertimeOrMismatch ? 'btn-warning text-dark' : 'btn-success'">
                            <i class="bx" :class="isCurrentShiftOvertimeOrMismatch ? 'bx-error-circle me-1' : 'bx-check-circle me-1'"></i> 
                            {{ isCurrentShiftOvertimeOrMismatch ? `⚠️ Shift Aktif: ${activeShift.shift_name} (Melebihi Jam)` : `Shift Aktif: ${activeShift.shift_name} (Tutup Shift)` }}
                        </Link>
                    </div>
                </div>

                <!-- Active Shift Banner if any -->
                <div v-if="activeShift" class="card border-0 shadow-md mb-4 overflow-hidden rounded-3" :style="{
                    background: isCurrentShiftOvertimeOrMismatch 
                        ? 'linear-gradient(135deg, #78350f 0%, #1e1b4b 100%)' 
                        : 'linear-gradient(135deg, #064e3b 0%, #0f172a 100%)',
                    borderLeft: isCurrentShiftOvertimeOrMismatch ? '6px solid #f59e0b' : '6px solid #10b981'
                }">
                    <div class="card-body p-3.5 d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-3 p-2.5 d-flex align-items-center justify-content-center shadow-sm" :class="isCurrentShiftOvertimeOrMismatch ? 'bg-warning bg-opacity-25 text-warning' : 'bg-success bg-opacity-20 text-success'" style="width: 54px; height: 54px;">
                                <i class="bx fs-1" :class="isCurrentShiftOvertimeOrMismatch ? 'bx-error-circle text-warning' : 'bx-store-alt text-success'"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    <h5 class="fw-bold text-white mb-0">Shift Anda Sedang Aktif: {{ activeShift.shift_name }}</h5>
                                    <span v-if="activeShift.master_shift" class="badge bg-white bg-opacity-15 text-white border border-white border-opacity-20 font-monospace">
                                        <i class="bx bx-time me-1"></i>Jadwal: {{ formatTimeOnly(activeShift.master_shift.start_time) }} – {{ formatTimeOnly(activeShift.master_shift.end_time) }}
                                    </span>
                                    <span v-if="isCurrentShiftOvertimeOrMismatch" class="badge bg-warning text-dark fw-bold px-2.5 py-1">
                                        ⚠️ PERINGATAN: TERLEWATI / DI LUAR JAM SHIFT
                                    </span>
                                    <span v-else class="badge bg-success bg-opacity-30 text-white border border-success border-opacity-40 fw-semibold">
                                        ✓ Sesuai Jam Operasional
                                    </span>
                                </div>

                                <!-- Alert Warning message if time mismatch -->
                                <div v-if="isCurrentShiftOvertimeOrMismatch" class="text-warning small fw-semibold mt-1 mb-1">
                                    <i class="bx bx-info-circle me-1"></i>
                                    Jam sekarang adalah <strong>{{ currentTimeStr }} WIB</strong>. Shift {{ activeShift.shift_name }} telah terlampaui / tidak sesuai jadwal jam operasional! Harap segera lakukan Tutup Shift.
                                </div>
                                <div v-else class="small text-white-50 mt-0.5">
                                    Dibuka: <strong class="text-white">{{ formatDate(activeShift.opened_at) }}</strong> 
                                    <span class="mx-2 text-white-50">|</span> 
                                    Modal Awal: <strong class="text-white">{{ formatCurrency(activeShift.opening_cash) }}</strong> 
                                    <span class="mx-2 text-white-50">|</span> 
                                    Cash Sales: <strong class="text-warning fw-bold fs-6">{{ formatCurrency(activeShift.cash_sales) }}</strong>
                                </div>
                            </div>
                        </div>

                        <Link :href="route('shifts.show', activeShift.id)" class="btn fw-bold px-4 py-2.5 rounded-3 shadow-sm d-flex align-items-center gap-1.5" :class="isCurrentShiftOvertimeOrMismatch ? 'btn-warning text-dark' : 'btn-success'">
                            <i class="bx bx-log-out-circle fs-5"></i>
                            {{ isCurrentShiftOvertimeOrMismatch ? '⚠️ Harap Tutup Shift Sekarang' : 'Lihat Detail / Tutup Shift' }}
                        </Link>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm p-3 mb-4 rounded-3">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <select v-model="cashierFilter" class="form-select">
                                <option value="">-- Semua Kasir --</option>
                                <option v-for="c in cashiers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select v-model="statusFilter" class="form-select">
                                <option value="">-- Semua Status --</option>
                                <option value="OPEN">OPEN (Aktif)</option>
                                <option value="CLOSED">CLOSED (Ditutup)</option>
                                <option value="FORCE CLOSED">FORCE CLOSED</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex gap-2 align-items-center">
                            <input type="date" v-model="startDate" class="form-control">
                            <span class="text-muted">s/d</span>
                            <input type="date" v-model="endDate" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Shift History Table -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>Kasir / Outlet</th>
                                    <th>Shift / Tanggal</th>
                                    <th class="text-end">Modal Awal</th>
                                    <th class="text-end">Cash Sales</th>
                                    <th class="text-end">Cash Seharusnya</th>
                                    <th class="text-end">Cash Aktual</th>
                                    <th class="text-end">Selisih</th>
                                    <th class="text-center">Status Jadwal</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in shifts.data" :key="s.id">
                                    <td>
                                        <div class="fw-bold text-dark">{{ s.user?.name || '-' }}</div>
                                        <small class="text-muted">{{ s.outlet?.name || 'Utama' }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-primary">{{ s.shift_name }}</div>
                                        <small class="text-muted">{{ formatDate(s.opened_at) }}</small>
                                    </td>
                                    <td class="text-end text-muted">{{ formatCurrency(s.opening_cash) }}</td>
                                    <td class="text-end fw-bold text-dark">{{ formatCurrency(s.cash_sales) }}</td>
                                    <td class="text-end fw-bold text-primary">{{ formatCurrency(s.expected_cash) }}</td>
                                    <td class="text-end fw-bold text-dark">{{ s.status === 'OPEN' ? '-' : formatCurrency(s.actual_cash) }}</td>
                                    <td class="text-end" :class="getDifferenceBadge(s.difference)">
                                        {{ s.status === 'OPEN' ? '-' : formatCurrency(s.difference) }}
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex flex-column align-items-center gap-1">
                                            <span :class="['badge', getStatusBadge(s.status)]">
                                                {{ s.status }}
                                            </span>
                                            <span v-if="s.is_out_of_schedule" class="badge bg-warning text-dark" style="font-size: 0.65rem;">
                                                ⚠️ Di Luar Jam Shift
                                            </span>
                                            <span v-else class="badge bg-light text-success border" style="font-size: 0.65rem;">
                                                ✓ Sesuai Jadwal
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <Link :href="route('shifts.show', s.id)" class="btn btn-sm btn-outline-primary">
                                            <i class="bx bx-show me-1"></i> Detail
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!shifts.data || shifts.data.length === 0">
                                    <td colspan="9" class="text-center py-4 text-muted">Belum ada data riwayat shift.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top" v-if="shifts.links && shifts.links.length > 3">
                        <small class="text-muted">
                            Menampilkan {{ shifts.from }} - {{ shifts.to }} dari {{ shifts.total }} hasil
                        </small>
                        <div class="btn-group btn-group-sm">
                            <template v-for="(link, k) in shifts.links" :key="k">
                                <div v-if="link.url === null" class="btn btn-outline-secondary disabled" v-html="link.label"></div>
                                <Link v-else :href="link.url" :class="['btn', link.active ? 'btn-primary' : 'btn-outline-secondary']" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- BUKA SHIFT MODAL -->
        <div v-if="showOpenModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bx bx-time-five me-2"></i>Buka Shift Kasir Baru</h5>
                        <button type="button" class="btn-close btn-close-white" @click="showOpenModal = false"></button>
                    </div>
                    <form @submit.prevent="submitOpenShift">
                        <div class="modal-body p-4">

                            <!-- Alert Warning if Mismatch -->
                            <div v-if="isShiftTimeMismatch" class="alert alert-warning border border-warning border-opacity-50 rounded-3 p-3 mb-3 shadow-xs">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bx bx-error text-warning fs-3 flex-shrink-0"></i>
                                    <div>
                                        <strong class="text-dark d-block">⚠️ PERINGATAN JAM OPERASIONAL SHIFT!</strong>
                                        <p class="text-dark small mb-0 mt-0.5">
                                            Jam saat ini (<strong>{{ currentTimeStr }} WIB</strong>) berada di luar jam operasional resmi <strong>{{ selectedMasterShift?.name }}</strong> ({{ formatTimeOnly(selectedMasterShift?.start_time) }} – {{ formatTimeOnly(selectedMasterShift?.end_time) }}).
                                        </p>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2 pt-2 border-top border-warning border-opacity-25">
                                    * Anda tetap diperbolehkan membuka shift ini, namun catatan <em>Di Luar Jadwal</em> akan tersimpan di Audit Log.
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Pilih Outlet Apotek</label>
                                <select v-model="openForm.outlet_id" class="form-select">
                                    <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
                                </select>
                            </div>
                            
                            <!-- Master Shift Selector -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark d-flex align-items-center justify-content-between">
                                    <span>Pilih Master Shift</span>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bx bx-time me-1"></i>Jam Sekarang: {{ currentTimeStr }} WIB
                                    </span>
                                </label>
                                <select v-model="openForm.master_shift_id" class="form-select form-select-lg fw-bold text-primary">
                                    <option v-for="ms in masterShifts" :key="ms.id" :value="ms.id">
                                        {{ ms.name }} ({{ formatTimeOnly(ms.start_time) }} – {{ formatTimeOnly(ms.end_time) }})
                                    </option>
                                </select>
                                <small v-if="selectedMasterShift" class="text-muted mt-1.5 d-block">
                                    <i class="bx bx-info-circle me-1 text-primary"></i>
                                    Jadwal Shift: <strong>{{ formatTimeOnly(selectedMasterShift.start_time) }} s/d {{ formatTimeOnly(selectedMasterShift.end_time) }}</strong> (Toleransi: {{ selectedMasterShift.grace_minutes }} menit).
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Modal Awal Kas (Laci Kasir) - Rp</label>
                                <input type="number" v-model.number="openForm.opening_cash" min="0" class="form-control form-control-lg font-monospace fw-bold text-primary" required>
                                <small class="text-muted">Masukkan jumlah uang tunai fisik yang ada di laci kasir saat ini.</small>
                            </div>
                        </div>

                        <div class="modal-footer border-top bg-light">
                            <button type="button" class="btn btn-secondary" @click="showOpenModal = false">Batal</button>
                            <button type="submit" :disabled="openForm.processing" class="btn btn-primary fw-bold px-4">
                                <i class="bx bx-check-circle me-1"></i> BUKA SHIFT KASIR
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </LegacyLayout>
</template>
