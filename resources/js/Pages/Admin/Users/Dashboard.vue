<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    recentLogins: Array,
    roleDistribution: Array,
});

const formatDateTime = (val) => {
    if (!val) return '-';
    return new Date(val).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="Dashboard User & Akses" />
    <LegacyLayout>
        <div class="content p-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1"><i class="bx bx-group me-2 text-primary"></i>Dashboard User & Akses</h4>
                    <p class="text-muted small mb-0">Ringkasan statistik pengguna dan aktivitas login</p>
                </div>
                <Link :href="route('admin.users.index')" class="btn btn-primary shadow-sm">
                    <i class="bx bx-user-plus me-1"></i> Kelola User
                </Link>
            </div>

            <!-- Stat Cards Row -->
            <div class="row g-3 mb-4">
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-body text-center p-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px;">
                                <i class="bx bx-group text-primary fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0">{{ stats.total_users }}</h3>
                            <small class="text-muted">Total User</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-body text-center p-3">
                            <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px;">
                                <i class="bx bx-check-circle text-success fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0 text-success">{{ stats.active_users }}</h3>
                            <small class="text-muted">User Aktif</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-body text-center p-3">
                            <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px;">
                                <i class="bx bx-x-circle text-warning fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0 text-warning">{{ stats.inactive_users }}</h3>
                            <small class="text-muted">User Nonaktif</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-body text-center p-3">
                            <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px;">
                                <i class="bx bx-block text-danger fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0 text-danger">{{ stats.suspended_users }}</h3>
                            <small class="text-muted">Suspended</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-body text-center p-3">
                            <div class="rounded-circle bg-info bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px;">
                                <i class="bx bx-shield-quarter text-info fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0 text-info">{{ stats.total_roles }}</h3>
                            <small class="text-muted">Jumlah Role</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-body text-center p-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px;">
                                <i class="bx bx-log-in text-primary fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0 text-primary">{{ stats.today_logins }}</h3>
                            <small class="text-muted">Login Hari Ini</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Role Distribution -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white border-bottom-0 pt-3 pb-0 px-4">
                            <h6 class="fw-bold mb-0"><i class="bx bx-pie-chart-alt-2 me-2 text-primary"></i>Distribusi Role</h6>
                        </div>
                        <div class="card-body px-4">
                            <div v-for="role in roleDistribution" :key="role.id" class="d-flex align-items-center justify-content-between py-2 border-bottom border-light">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-pill" :class="{
                                        'bg-danger': role.name === 'Super Admin',
                                        'bg-primary': role.name === 'Owner',
                                        'bg-success': role.name === 'Apoteker',
                                        'bg-warning text-dark': role.name === 'Kasir',
                                        'bg-info': role.name === 'Gudang',
                                        'bg-secondary': !['Super Admin','Owner','Apoteker','Kasir','Gudang'].includes(role.name),
                                    }">{{ role.name }}</span>
                                </div>
                                <span class="fw-bold">{{ role.users_count }} user</span>
                            </div>
                            <div v-if="!roleDistribution.length" class="text-muted text-center py-3 small">Belum ada data role.</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Logins -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white border-bottom-0 pt-3 pb-0 px-4">
                            <h6 class="fw-bold mb-0"><i class="bx bx-log-in me-2 text-success"></i>Login Terakhir</h6>
                        </div>
                        <div class="card-body px-4">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="small">Nama</th>
                                            <th class="small">Role</th>
                                            <th class="small">Outlet</th>
                                            <th class="small">Last Login</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="u in recentLogins" :key="u.id">
                                            <td>
                                                <div class="fw-semibold small">{{ u.name }}</div>
                                                <small class="text-muted">{{ u.email }}</small>
                                            </td>
                                            <td><span class="badge bg-primary bg-opacity-75 small">{{ u.roles?.[0]?.name || u.role }}</span></td>
                                            <td class="small">{{ u.primary_outlet?.code || '-' }}</td>
                                            <td class="small text-muted">{{ formatDateTime(u.last_login_at) }}</td>
                                        </tr>
                                        <tr v-if="!recentLogins.length">
                                            <td colspan="4" class="text-center text-muted py-3">Belum ada aktivitas login.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
