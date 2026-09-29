<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { showSuccess } from '@/Utils/swal';

const props = defineProps({
    customer: Object,
    nextTier: Object,
});

const editForm = useForm({
    name: props.customer.name,
    phone: props.customer.phone,
    email: props.customer.email || '',
    date_of_birth: props.customer.date_of_birth || '',
    gender: props.customer.gender || 'L',
    address: props.customer.address || '',
    allergies: props.customer.allergies || '',
    status: props.customer.status || 'ACTIVE',
});

const submitAllergiesUpdate = () => {
    editForm.put(route('membership.members.update', props.customer.id), {
        onSuccess: () => {
            const modalEl = document.getElementById('editAlergiModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
            showSuccess('Alergi Berhasil Diperbarui', 'Data riwayat alergi obat member berhasil disimpan!');
        }
    });
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

// Calculate Progress Tier Percentage
const currentSpending = props.customer.total_spending || 0;
const nextMin = props.nextTier ? props.nextTier.min_spending : currentSpending;
const progressPercent = props.nextTier && nextMin > 0 ? Math.min(100, Math.round((currentSpending / nextMin) * 100)) : 100;
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <!-- Top Navigation Back -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <Link :href="route('membership.members.index')" class="btn btn-sm btn-outline-secondary rounded-pill">
                        <i class="bx bx-arrow-back me-1"></i>Kembali ke Data Member
                    </Link>
                    <span class="badge bg-success px-3 py-2 fs-6 rounded-pill">Status Member: {{ customer.status || 'ACTIVE' }}</span>
                </div>

                <!-- Member Card & Profile Hero -->
                <div class="row g-4 mb-4">
                    <!-- Digital Member Card -->
                    <div class="col-md-5">
                        <div class="card border-0 shadow-lg rounded-4 p-4 text-white" :style="{ background: customer.tier?.badge_color ? `linear-gradient(135deg, ${customer.tier.badge_color} 0%, #1e293b 100%)` : 'linear-gradient(135deg, #3b6bff 0%, #1e293b 100%)' }">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h5 class="fw-bold mb-0 text-white"><i class="bx bx-plus-medical text-warning me-2"></i>APOTEK MEDIKA SORE</h5>
                                    <small class="text-white-50">DIGITAL MEMBERSHIP CARD</small>
                                </div>
                                <span class="badge bg-white text-dark fw-bold text-uppercase px-3 py-2 fs-6 rounded-pill shadow-sm">
                                    {{ customer.tier?.name || customer.membership_level }}
                                </span>
                            </div>

                            <div class="mb-4">
                                <h3 class="fw-bold text-white mb-1">{{ customer.name }}</h3>
                                <div class="font-monospace text-warning fs-5 tracking-wide">{{ customer.code }}</div>
                                <small class="text-white-50"><i class="bx bx-phone me-1"></i>{{ customer.phone }}</small>
                            </div>

                            <div class="d-flex justify-content-between align-items-end pt-3 border-top border-white border-opacity-25">
                                <div>
                                    <small class="text-white-50 d-block">SALDO POIN</small>
                                    <span class="fs-4 fw-bold text-warning">{{ customer.points.toLocaleString() }} Pts</span>
                                </div>
                                <div class="text-end">
                                    <small class="text-white-50 d-block">MEMBER SINCE</small>
                                    <span class="fw-bold text-white">{{ formatDate(customer.created_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Tier & Spending Summary -->
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                            <h5 class="fw-bold text-dark mb-3"><i class="bx bx-crown text-warning me-2"></i>Progress Tier & Belanja Member</h5>
                            
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-dark">Tier Saat Ini: <span class="text-primary text-uppercase">{{ customer.tier?.name || 'BRONZE' }}</span></span>
                                    <span class="small text-muted" v-if="nextTier">Target Berikutnya: <strong class="text-dark">{{ nextTier.name }}</strong> ({{ formatRupiah(nextTier.min_spending) }})</span>
                                    <span class="small text-success fw-bold" v-else>🎉 Member Tier Tertinggi (PLATINUM)!</span>
                                </div>

                                <div class="progress rounded-pill mb-2" style="height: 14px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" :style="{ width: progressPercent + '%' }">
                                        {{ progressPercent }}%
                                    </div>
                                </div>
                                <small class="text-muted">Total Belanja Terakumulasi: <strong class="text-success">{{ formatRupiah(customer.total_spending) }}</strong></small>
                            </div>

                            <!-- Stat Pills -->
                            <div class="row g-2">
                                <div class="col-4">
                                    <div class="bg-light p-3 rounded-3 text-center">
                                        <small class="text-muted d-block">TOTAL POIN</small>
                                        <strong class="fs-5 text-success">{{ customer.points }} Pts</strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light p-3 rounded-3 text-center">
                                        <small class="text-muted d-block">TOTAL BELANJA</small>
                                        <strong class="fs-5 text-primary">{{ formatRupiah(customer.total_spending) }}</strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light p-3 rounded-3 text-center">
                                        <small class="text-muted d-block">TRANSAKSI</small>
                                        <strong class="fs-5 text-dark">{{ customer.sales ? customer.sales.length : 0 }}x</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Identitas Member Card Section -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 p-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="bx bx-user-pin text-primary me-2"></i>Identitas Lengkap Member</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-3">
                            <small class="text-muted d-block">KODE MEMBER / ID</small>
                            <strong class="text-primary font-monospace fs-6">{{ customer.code }}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">NAMA LENGKAP</small>
                            <strong class="text-dark fs-6">{{ customer.name }}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">NOMOR HP (WHATSAPP)</small>
                            <strong class="text-dark fs-6">{{ customer.phone }}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">EMAIL</small>
                            <strong class="text-dark fs-6">{{ customer.email || '-' }}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">TANGGAL LAHIR</small>
                            <strong class="text-dark fs-6">{{ formatDate(customer.date_of_birth) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">JENIS KELAMIN</small>
                            <strong class="text-dark fs-6">{{ customer.gender === 'L' ? 'Laki-laki' : (customer.gender === 'P' ? 'Perempuan' : '-') }}</strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">ALAMAT TEMPAT TINGGAL</small>
                            <strong class="text-dark fs-6">{{ customer.address || 'Belum diisi' }}</strong>
                        </div>
                    </div>

                    <!-- Warning / Safe Box Alergi Obat -->
                    <div v-if="customer.allergies" class="mt-3 p-3 rounded-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="fw-bold fs-6 d-flex align-items-center gap-1 text-danger">
                                <i class="bx bx-shield-x me-1 fs-5 align-middle"></i>PERINGATAN ALERGI OBAT MEMBER:
                            </div>
                            <div class="fs-6 mt-1 text-dark fw-bold">
                                Sensitif Terhadap: <span class="badge bg-danger text-white fs-6 px-2 py-1">{{ customer.allergies }}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger shadow-xs fw-bold px-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#editAlergiModal">
                            <i class="bx bx-edit me-1"></i>Edit Alergi Obat
                        </button>
                    </div>
                    <div v-else class="mt-3 p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25 text-success d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bx bx-shield-quarter fs-4 text-success"></i>
                            <div>
                                <strong class="d-block text-success">Riwayat Alergi Obat: <span class="badge bg-success text-white ms-1"><i class="bx bx-check-circle me-1"></i>Tidak Ada (Aman)</span></strong>
                                <small class="text-muted">Member tidak memiliki riwayat alergi obat terdaftar.</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-success shadow-xs fw-bold px-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#editAlergiModal">
                            <i class="bx bx-plus me-1"></i>+ Catat Alergi
                        </button>
                    </div>
                </div>

                <!-- Tabs Section: Riwayat Pembelian & Riwayat Poin -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <ul class="nav nav-tabs border-bottom-0" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#tab-belanja">
                                    <i class="bx bx-receipt me-1"></i>Riwayat Pembelian Member (POS)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#tab-poin">
                                    <i class="bx bx-history me-1"></i>Riwayat Transaksi Poin
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body p-4 tab-content">
                        <!-- Tab Riwayat Pembelian -->
                        <div class="tab-pane fade show active" id="tab-belanja">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>No. Invoice</th>
                                            <th>Tanggal Belanja</th>
                                            <th>Metode Bayar</th>
                                            <th>Total Belanja</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="s in customer.sales" :key="s.id">
                                            <td class="fw-bold text-primary font-monospace">{{ s.invoice_number }}</td>
                                            <td>{{ formatDate(s.sale_date) }}</td>
                                            <td><span class="badge bg-info text-dark text-uppercase">{{ s.payment_method }}</span></td>
                                            <td class="fw-bold text-dark fs-6">{{ formatRupiah(s.grand_total) }}</td>
                                            <td><span class="badge bg-success">{{ s.status }}</span></td>
                                        </tr>
                                        <tr v-if="!customer.sales || customer.sales.length === 0">
                                            <td colspan="5" class="text-center py-4 text-muted">Belum ada riwayat transaksi pembelian POS untuk member ini.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab Riwayat Poin -->
                        <div class="tab-pane fade" id="tab-poin">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Tipe Transaksi</th>
                                            <th>Keterangan / Ref</th>
                                            <th>Poin Masuk</th>
                                            <th>Poin Keluar</th>
                                            <th>Saldo Akhir</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="pt in customer.point_transactions" :key="pt.id">
                                            <td>{{ formatDate(pt.created_at) }}</td>
                                            <td><span class="badge bg-secondary">{{ pt.transaction_type }}</span></td>
                                            <td>{{ pt.description }} <small v-if="pt.reference_id" class="text-primary">({{ pt.reference_id }})</small></td>
                                            <td class="fw-bold text-success">{{ pt.points_in > 0 ? '+' + pt.points_in : '-' }}</td>
                                            <td class="fw-bold text-danger">{{ pt.points_out > 0 ? '-' + pt.points_out : '-' }}</td>
                                            <td class="fw-bold text-dark">{{ pt.balance_after }} Pts</td>
                                        </tr>
                                        <tr v-if="!customer.point_transactions || customer.point_transactions.length === 0">
                                            <td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat transaksi poin.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Modal Edit Alergi Obat Member -->
        <div class="modal fade" id="editAlergiModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark"><i class="bx bx-shield-x text-danger me-2"></i>Kelola Riwayat Alergi Obat Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitAllergiesUpdate">
                        <div class="modal-body">
                            <div class="alert alert-warning small border-0 mb-3">
                                <i class="bx bx-info-circle me-1"></i>
                                Informasi alergi obat sangat penting untuk keselamatan pasien saat transaksi di Kasir (POS).
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Nama Member</label>
                                <input type="text" class="form-control bg-light" :value="customer.name" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-danger"><i class="bx bx-error-circle me-1"></i>Catatan / Zat Alergi Obat</label>
                                <textarea class="form-control border-danger border-opacity-50" rows="3" v-model="editForm.allergies" placeholder="Contoh: Paracetamol, Amoxicillin, Penisilin, Cefadroxil (Kosongkan jika tidak ada/aman)"></textarea>
                                <div class="form-text small text-muted">Ketik nama obat atau zat aktif yang memicu reaksi alergi pada pasien ini.</div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger font-semibold" :disabled="editForm.processing">Simpan Alergi Obat</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
