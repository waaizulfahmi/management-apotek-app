<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    topMembers: Array,
    activeMembers: Array,
    recentTransactions: Array,
});

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-0">
                            <i class="bx bx-id-card text-primary me-2"></i>Dashboard Membership & Customer Loyalty
                        </h2>
                        <p class="text-muted small mb-0">Ringkasan statistik member, pertumbuhan poin, member paling aktif, dan omset apotek</p>
                    </div>
                    <div class="d-flex gap-2">
                        <Link :href="route('membership.members.index')" class="btn btn-primary shadow-sm">
                            <i class="bx bx-user-plus me-1"></i>Kelola Member
                        </Link>
                    </div>
                </div>

                <!-- 4 Primary Stat Cards Grid -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm p-3 rounded-4 bg-primary text-white h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-white-50 fw-bold">TOTAL MEMBER</small>
                                    <h3 class="fw-bold mb-0 text-white mt-1">{{ stats.total_members }}</h3>
                                    <small class="text-white-50">Member Terdaftar</small>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bx bx-group fs-3 text-white"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm p-3 rounded-4 bg-success text-white h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-white-50 fw-bold">MEMBER AKTIF</small>
                                    <h3 class="fw-bold mb-0 text-white mt-1">{{ stats.active_members }}</h3>
                                    <small class="text-white-50">+{{ stats.new_members_this_month }} Member Baru Bulan Ini</small>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bx bx-user-check fs-3 text-white"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm p-3 rounded-4 bg-warning text-dark h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-dark-50 fw-bold">POIN BEREDAR</small>
                                    <h3 class="fw-bold mb-0 text-dark mt-1">{{ stats.total_points_circulating.toLocaleString() }} Pts</h3>
                                    <small class="text-dark-50">Saldo Poin Pelanggan</small>
                                </div>
                                <div class="bg-dark bg-opacity-10 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bx bx-coin-stack fs-3 text-dark"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm p-3 rounded-4 bg-info text-dark h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-dark-50 fw-bold">PENJUALAN MEMBER</small>
                                    <h3 class="fw-bold mb-0 text-dark mt-1">{{ formatRupiah(stats.total_member_sales) }}</h3>
                                    <small class="text-dark-50">{{ stats.member_contribution_percent }}% Dari Total Omset</small>
                                </div>
                                <div class="bg-dark bg-opacity-10 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bx bx-dollar-circle fs-3 text-dark"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Member Paling Aktif & Top Spender -->
                <div class="row g-4 mb-4">
                    <!-- Member Paling Aktif (Frekuensi Belanja) -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold text-dark mb-0"><i class="bx bx-zap text-warning me-2"></i>Member Paling Aktif (Frekuensi Belanja)</h5>
                                <Link :href="route('membership.members.index')" class="small text-primary text-decoration-none">Lihat Semua</Link>
                            </div>
                            <div class="card-body px-4">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Member</th>
                                                <th>Tier</th>
                                                <th>Frekuensi Transaksi</th>
                                                <th class="text-end">Total Spending</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="am in activeMembers" :key="am.id">
                                                <td>
                                                    <div class="fw-bold text-dark">{{ am.name }}</div>
                                                    <small class="text-muted">{{ am.code }} | {{ am.phone }}</small>
                                                </td>
                                                <td>
                                                    <span class="badge text-uppercase" :style="{ backgroundColor: am.tier?.badge_color || '#6b7280' }">
                                                        {{ am.tier?.name || am.membership_level }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success px-3 py-2 rounded-pill fs-6">
                                                        ⚡ {{ am.sales_count || 0 }}x Transaksi
                                                    </span>
                                                </td>
                                                <td class="text-end fw-bold text-primary">{{ formatRupiah(am.total_spending) }}</td>
                                            </tr>
                                            <tr v-if="!activeMembers || activeMembers.length === 0">
                                                <td colspan="4" class="text-center py-4 text-muted">Belum ada data member aktif.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Member Spender (Nominal Belanja Terbesar) -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold text-dark mb-0"><i class="bx bx-trophy text-warning me-2"></i>Top Member Spender (Belanja Terbesar)</h5>
                                <Link :href="route('membership.members.index')" class="small text-primary text-decoration-none">Lihat Semua</Link>
                            </div>
                            <div class="card-body px-4">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Member</th>
                                                <th>Tier</th>
                                                <th>Poin Available</th>
                                                <th class="text-end">Total Spending</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="m in topMembers" :key="m.id">
                                                <td>
                                                    <div class="fw-bold text-dark">{{ m.name }}</div>
                                                    <small class="text-muted">{{ m.code }} | {{ m.phone }}</small>
                                                </td>
                                                <td>
                                                    <span class="badge text-uppercase" :style="{ backgroundColor: m.tier?.badge_color || '#6b7280' }">
                                                        {{ m.tier?.name || m.membership_level }}
                                                    </span>
                                                </td>
                                                <td class="fw-bold text-success">{{ m.points.toLocaleString() }} Pts</td>
                                                <td class="text-end fw-bold text-primary fs-6">{{ formatRupiah(m.total_spending) }}</td>
                                            </tr>
                                            <tr v-if="!topMembers || topMembers.length === 0">
                                                <td colspan="4" class="text-center py-4 text-muted">Belum ada data spender.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Point Transactions Row -->
                <div class="row g-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold text-dark mb-0"><i class="bx bx-history text-primary me-2"></i>Aktivitas Poin Member Terakhir</h5>
                                <Link :href="route('membership.points.index')" class="small text-primary text-decoration-none">Lihat Riwayat Poin</Link>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Member</th>
                                                <th>Tipe Transaksi</th>
                                                <th>Keterangan / Ref</th>
                                                <th>Poin</th>
                                                <th>Waktu</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="t in recentTransactions" :key="t.id">
                                                <td>
                                                    <div class="fw-bold text-dark">{{ t.customer?.name || 'Member' }}</div>
                                                    <small class="text-muted">{{ t.customer?.code }} | {{ t.customer?.phone }}</small>
                                                </td>
                                                <td>
                                                    <span class="badge" :class="t.points_in > 0 ? 'bg-success' : 'bg-danger'">
                                                        {{ t.transaction_type }}
                                                    </span>
                                                </td>
                                                <td>{{ t.description }}</td>
                                                <td class="fw-bold" :class="t.points_in > 0 ? 'text-success' : 'text-danger'">
                                                    {{ t.points_in > 0 ? '+' + t.points_in : '-' + t.points_out }} Pts
                                                </td>
                                                <td class="small text-muted">{{ formatDate(t.created_at) }}</td>
                                            </tr>
                                            <tr v-if="!recentTransactions || recentTransactions.length === 0">
                                                <td colspan="5" class="text-center py-4 text-muted">Belum ada aktivitas poin.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
