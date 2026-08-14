<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    shift: Object,
    canForceClose: Boolean,
});

const isShiftOpen = computed(() => props.shift.status === 'OPEN');

// Close Shift Form
const closeForm = useForm({
    actual_cash: props.shift.expected_cash || 0,
    notes: '',
});

const submitCloseShift = () => {
    if (confirm('Apakah Anda yakin ingin menutup shift ini? Pastikan jumlah kas fisik di laci telah dihitung dengan tepat.')) {
        closeForm.post(route('shifts.close', props.shift.id));
    }
};

// Force Close Form
const showForceCloseModal = ref(false);
const forceCloseForm = useForm({
    reason: '',
});

const submitForceClose = () => {
    forceCloseForm.post(route('shifts.force_close', props.shift.id), {
        onSuccess: () => {
            showForceCloseModal.value = false;
        }
    });
};

// Cash Adjustment Form
const showAdjustModal = ref(false);
const adjustForm = useForm({
    type: 'in',
    amount: 0,
    reason: '',
});

const submitAdjustCash = () => {
    adjustForm.post(route('shifts.adjust_cash', props.shift.id), {
        onSuccess: () => {
            showAdjustModal.value = false;
            adjustForm.reset();
        }
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
};

const liveDifference = computed(() => {
    return (closeForm.actual_cash || 0) - (props.shift.expected_cash || 0);
});

const differenceStatus = computed(() => {
    const diff = liveDifference.value;
    if (diff === 0) return { label: 'Sesuai', class: 'badge bg-success' };
    if (diff < 0) return { label: 'Kurang (Minus)', class: 'badge bg-danger' };
    return { label: 'Lebih (Surplus)', class: 'badge bg-primary' };
});

const getStatusBadge = (status) => {
    switch(status) {
        case 'OPEN': return 'bg-success';
        case 'CLOSED': return 'bg-secondary';
        case 'FORCE CLOSED': return 'bg-danger';
        default: return 'bg-secondary';
    }
};

const currentTimeStr = ref(new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));

const isCurrentShiftOvertimeOrMismatch = computed(() => {
    if (!props.shift || props.shift.status !== 'OPEN') return false;
    
    if (props.shift.is_out_of_schedule) return true;

    const now = new Date();
    const curStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0') + ':00';

    const master = props.shift.master_shift;
    if (master) {
        const start = master.start_time;
        const end = master.end_time;
        if (start <= end) {
            return curStr < start || curStr > end;
        } else {
            return curStr < start && curStr > end;
        }
    } else {
        const name = props.shift.shift_name;
        if (name.includes('Pagi')) {
            return curStr < '07:00:00' || curStr > '15:00:00';
        } else if (name.includes('Siang')) {
            return curStr < '15:00:00' || curStr > '22:00:00';
        } else if (name.includes('Malam')) {
            return curStr < '22:00:00' && curStr > '07:00:00';
        }
    }

    return false;
});
</script>

<template>
    <Head :title="'Detail Shift - ' + shift.shift_name" />
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <Link :href="route('shifts.index')" class="btn btn-outline-secondary btn-sm rounded-circle p-2">
                            <i class="bx bx-arrow-back fs-5"></i>
                        </Link>
                        <div>
                            <h2 class="fw-bold text-dark mb-0">
                                <i class="bx bx-time-five text-primary me-2"></i>Detail Shift: {{ shift.shift_name }} ({{ shift.user?.name }})
                            </h2>
                            <small class="text-muted">ID Shift: #{{ shift.id }} | Dibuka: {{ formatDate(shift.opened_at) }}</small>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button v-if="isShiftOpen" @click="showAdjustModal = true" class="btn btn-outline-primary shadow-sm fw-bold">
                            <i class="bx bx-transfer-alt me-1"></i> Input Penyesuaian Kas
                        </button>
                        <button v-if="isShiftOpen && canForceClose" @click="showForceCloseModal = true" class="btn btn-outline-danger shadow-sm fw-bold">
                            <i class="bx bx-power-off me-1"></i> Force Close Shift
                        </button>
                    </div>
                </div>

                <!-- OVERTIME WARNING ALERT BANNER -->
                <div v-if="isShiftOpen && isCurrentShiftOvertimeOrMismatch" class="alert alert-warning border border-warning border-opacity-50 rounded-3 p-3.5 mb-4 shadow-sm">
                    <div class="d-flex align-items-start gap-3">
                        <i class="bx bx-error text-warning fs-1 flex-shrink-0"></i>
                        <div>
                            <h5 class="fw-bold text-dark mb-1">⚠️ PERINGATAN KASIR: JAM SHIFT TELAH TERLEWATI!</h5>
                            <p class="text-dark small mb-0">
                                Shift ini adalah <strong>{{ shift.shift_name }}</strong>, tetapi jam saat ini menunjukkan pukul <strong>{{ currentTimeStr }} WIB</strong>.
                                Shift telah melewati jadwal operasional resmi. Harap segera lakukan penutupan shift pada form di bawah ini demi ketertiban administrasi kasir.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Shift Header Status Card -->
                <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                        <div>
                            <h4 class="fw-bold text-dark mb-1">{{ shift.shift_name }}</h4>
                            <span class="text-muted">Kasir: <strong>{{ shift.user?.name }}</strong> | Outlet: <strong>{{ shift.outlet?.name || 'Utama' }}</strong></span>
                        </div>
                        <div class="text-end">
                            <span :class="['badge fs-6', getStatusBadge(shift.status)]">{{ shift.status }}</span>
                            <small class="text-muted d-block mt-1">Ditutup: {{ formatDate(shift.closed_at) }}</small>
                        </div>
                    </div>

                    <!-- Metrics Overview Cards -->
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="bg-light p-3 rounded-3 border">
                                <small class="text-muted d-block">Modal Awal Kas</small>
                                <strong class="text-dark fs-5">{{ formatCurrency(shift.opening_cash) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-light p-3 rounded-3 border">
                                <small class="text-muted d-block">Total Transaksi</small>
                                <strong class="text-primary fs-5">{{ shift.sales ? shift.sales.length : 0 }} Transaksi</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-light p-3 rounded-3 border">
                                <small class="text-muted d-block">Penjualan Cash (Tunai)</small>
                                <strong class="text-success fs-5">{{ formatCurrency(shift.cash_sales) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-light p-3 rounded-3 border">
                                <small class="text-muted d-block">Penjualan Non-Cash</small>
                                <strong class="text-info fs-5">{{ formatCurrency(shift.non_cash_sales) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CASH FORMULA & RECONCILIATION CARD -->
                <div class="row g-4 mb-4">
                    <!-- Left: Cash Calculation Formula -->
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: 12px;">
                            <h5 class="fw-bold text-dark mb-3"><i class="bx bx-calculator text-primary me-2"></i>Perhitungan Fisik Uang Kas</h5>
                            
                            <div class="bg-light p-3 rounded-3 border mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Modal Awal Kas:</span>
                                    <strong>{{ formatCurrency(shift.opening_cash) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2 text-success">
                                    <span>+ Penjualan Cash (Tunai):</span>
                                    <strong>+{{ formatCurrency(shift.cash_sales) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2 text-danger">
                                    <span>- Refund Cash:</span>
                                    <strong>-{{ formatCurrency(shift.cash_refunds) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2 text-secondary">
                                    <span>+ / - Penyesuaian Kas (Masuk/Keluar):</span>
                                    <strong>{{ formatCurrency(shift.cash_adjustments) }}</strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between fs-5 fw-bold text-primary">
                                    <span>Cash Seharusnya di Laci:</span>
                                    <span>{{ formatCurrency(shift.expected_cash) }}</span>
                                </div>
                            </div>

                            <div v-if="!isShiftOpen" class="p-3 border rounded-3 bg-white">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Cash Aktual (Diinput Kasir):</span>
                                    <strong class="fs-5 text-dark">{{ formatCurrency(shift.actual_cash) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                    <span>Selisih Kas:</span>
                                    <span class="fs-5" :class="shift.difference === 0 ? 'text-success fw-bold' : (shift.difference < 0 ? 'text-danger fw-bold' : 'text-primary fw-bold')">
                                        {{ formatCurrency(shift.difference) }}
                                    </span>
                                </div>
                                <div v-if="shift.difference_reason" class="mt-2 text-muted small">
                                    Catatan: {{ shift.difference_reason }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: TUTUP SHIFT FORM (If OPEN) -->
                    <div class="col-md-5" v-if="isShiftOpen">
                        <div class="card border-0 shadow-sm p-4 h-100 bg-white" style="border-radius: 12px; border-left: 5px solid #28a745 !important;">
                            <h5 class="fw-bold text-success mb-3"><i class="bx bx-check-shield me-2"></i>Form Tutup Shift</h5>
                            
                            <form @submit.prevent="submitCloseShift">
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Input Cash Aktual di Laci (Rp)</label>
                                    <input type="number" v-model.number="closeForm.actual_cash" min="0" class="form-control form-control-lg font-monospace fw-bold text-dark" required>
                                    <small class="text-muted">Hitung total uang fisik pecahan kertas & koin yang ada di laci kasir saat ini.</small>
                                </div>

                                <div class="bg-light p-3 rounded-3 border mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small text-muted">Estimasi Selisih Kas:</span>
                                        <span :class="differenceStatus.class">{{ differenceStatus.label }}</span>
                                    </div>
                                    <div class="fs-4 fw-bold text-end" :class="liveDifference === 0 ? 'text-success' : (liveDifference < 0 ? 'text-danger' : 'text-primary')">
                                        {{ formatCurrency(liveDifference) }}
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small text-muted">Catatan Penutupan / Alasan Selisih</label>
                                    <textarea v-model="closeForm.notes" rows="2" class="form-control" placeholder="Tuliskan alasan jika terdapat selisih kas..."></textarea>
                                </div>

                                <button type="submit" :disabled="closeForm.processing" class="btn btn-success btn-lg w-100 fw-bold shadow-sm">
                                    <i class="bx bx-lock-alt me-1"></i> TUTUP SHIFT SEKARANG
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- CASH ADJUSTMENTS TABLE -->
                <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 12px;" v-if="shift.cash_movements && shift.cash_movements.length > 0">
                    <h5 class="fw-bold text-dark mb-3"><i class="bx bx-transfer-alt me-2 text-warning"></i>Riwayat Penyesuaian Kas Masuk / Keluar</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Tipe</th>
                                    <th>Jumlah</th>
                                    <th>Alasan / Keperluan</th>
                                    <th>Petugas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in shift.cash_movements" :key="m.id">
                                    <td>{{ formatDate(m.created_at) }}</td>
                                    <td>
                                        <span :class="['badge', m.type === 'in' ? 'bg-success' : 'bg-danger']">
                                            {{ m.type === 'in' ? 'KAS MASUK' : 'KAS KELUAR' }}
                                        </span>
                                    </td>
                                    <td class="fw-bold" :class="m.type === 'in' ? 'text-success' : 'text-danger'">
                                        {{ m.type === 'in' ? '+' : '-' }}{{ formatCurrency(m.amount) }}
                                    </td>
                                    <td>{{ m.reason }}</td>
                                    <td>{{ m.user?.name || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TRANSACTIONS IN THIS SHIFT -->
                <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 12px;">
                    <h5 class="fw-bold text-dark mb-3"><i class="bx bx-receipt me-2 text-primary"></i>Daftar Transaksi Pada Shift Ini</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>No Invoice</th>
                                    <th>Waktu</th>
                                    <th>Pelanggan</th>
                                    <th>Metode Bayar</th>
                                    <th class="text-end">Grand Total</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in shift.sales" :key="s.id">
                                    <td class="fw-bold text-primary">{{ s.invoice_number }}</td>
                                    <td>{{ formatDate(s.sale_date) }}</td>
                                    <td>{{ s.customer?.name || 'Umum' }}</td>
                                    <td><span class="badge bg-info text-dark text-uppercase">{{ s.payment_method }}</span></td>
                                    <td class="text-end fw-bold text-dark">{{ formatCurrency(s.grand_total) }}</td>
                                    <td class="text-center">
                                        <Link :href="route('sales.history.show', s.id)" class="btn btn-sm btn-outline-primary">
                                            <i class="bx bx-show me-1"></i> Detail
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!shift.sales || shift.sales.length === 0">
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada transaksi pada shift ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- INPUT CASH ADJUSTMENT MODAL -->
        <div v-if="showAdjustModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);" aria-modal="true" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bx bx-transfer-alt me-2"></i>Input Penyesuaian Kas Shift</h5>
                        <button type="button" class="btn-close btn-close-white" @click="showAdjustModal = false"></button>
                    </div>
                    <form @submit.prevent="submitAdjustCash">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small text-muted">Jenis Penyesuaian Kas</label>
                                <select v-model="adjustForm.type" class="form-select fw-bold">
                                    <option value="in">Kas Masuk (+ Modal Tambahan / Operasional)</option>
                                    <option value="out">Kas Keluar (- Pengeluaran Kasir / Setor sementara)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Jumlah Uang Kas (Rp)</label>
                                <input type="number" v-model.number="adjustForm.amount" min="1" class="form-control form-control-lg font-monospace fw-bold" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Alasan / Keperluan Penyesuaian Kas</label>
                                <textarea v-model="adjustForm.reason" rows="2" class="form-control" placeholder="Contoh: Tambah uang kembalian pecahan 5.000..." required></textarea>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showAdjustModal = false">Batal</button>
                            <button type="submit" :disabled="adjustForm.processing" class="btn btn-primary fw-bold">Simpan Penyesuaian</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- FORCE CLOSE MODAL -->
        <div v-if="showForceCloseModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);" aria-modal="true" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fw-bold"><i class="bx bx-power-off me-2"></i>Force Close Shift (Admin/Manager)</h5>
                        <button type="button" class="btn-close btn-close-white" @click="showForceCloseModal = false"></button>
                    </div>
                    <form @submit.prevent="submitForceClose">
                        <div class="modal-body">
                            <div class="alert alert-danger border-0 small">
                                <strong>Peringatan!</strong> Force close digunakan jika kasir lupa menutup shift atau berhalangan. Aksi ini akan dicatat ke dalam <strong>Audit Log</strong>.
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Alasan Force Close (Wajib Diisi)</label>
                                <textarea v-model="forceCloseForm.reason" rows="3" class="form-control" placeholder="Contoh: Kasir lupa tutup shift setelah pergantian jam kerja..." required></textarea>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showForceCloseModal = false">Batal</button>
                            <button type="submit" :disabled="forceCloseForm.processing" class="btn btn-danger fw-bold">FORCE CLOSE SHIFT</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </LegacyLayout>
</template>
