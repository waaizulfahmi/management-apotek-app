<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { showConfirm, showSuccess } from '@/Utils/swal';

const props = defineProps({
    users: Object,
    roles: Array,
    outlets: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const filterRole = ref(props.filters?.role || '');
const filterStatus = ref(props.filters?.status || '');
const filterOutlet = ref(props.filters?.outlet_id || '');

const showCreateModal = ref(false);
const showResetModal = ref(false);
const resetUserId = ref(null);
const resetUserName = ref('');

const form = useForm({
    name: '',
    username: '',
    email: '',
    no_hp: '',
    password: '',
    role: '',
    outlet_id: '',
    outlet_ids: [],
});

const resetForm = useForm({
    password: '',
});

let searchTimeout = null;
const applyFilters = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('admin.users.index'), {
            search: search.value || undefined,
            role: filterRole.value || undefined,
            status: filterStatus.value || undefined,
            outlet_id: filterOutlet.value || undefined,
        }, { preserveState: true, replace: true });
    }, 350);
};

watch([search, filterRole, filterStatus, filterOutlet], applyFilters);

const clearFilters = () => {
    search.value = '';
    filterRole.value = '';
    filterStatus.value = '';
    filterOutlet.value = '';
};

const submitCreate = () => {
    form.post(route('admin.users.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const openResetModal = (user) => {
    resetUserId.value = user.id;
    resetUserName.value = user.name;
    resetForm.password = '';
    showResetModal.value = true;
};

const submitReset = () => {
    resetForm.post(route('admin.users.reset-password', resetUserId.value), {
        onSuccess: () => {
            showResetModal.value = false;
            resetForm.reset();
        },
    });
};

const toggleStatus = (user, newStatus) => {
    showConfirm(
        `Ubah Status ke ${newStatus}?`,
        `User "${user.name}" akan diubah statusnya menjadi ${newStatus}.`,
        () => {
            router.post(route('admin.users.toggle-status', user.id), { status: newStatus });
        }
    );
};

const forceLogout = (user) => {
    showConfirm(
        'Force Logout User?',
        `Sesi aktif user "${user.name}" akan dihentikan secara paksa.`,
        () => {
            router.post(route('admin.users.force-logout', user.id));
        }
    );
};

const statusBadge = (status) => {
    const map = { 'ACTIVE': 'bg-success', 'INACTIVE': 'bg-warning text-dark', 'SUSPENDED': 'bg-danger' };
    return map[status] || 'bg-secondary';
};

const formatDateTime = (val) => {
    if (!val) return '-';
    return new Date(val).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="Manajemen User" />
    <LegacyLayout>
        <div class="content p-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1"><i class="bx bx-user-pin me-2 text-primary"></i>Manajemen User</h4>
                    <p class="text-muted small mb-0">Kelola semua pengguna dan hak akses sistem</p>
                </div>
                <button class="btn btn-primary shadow-sm" @click="showCreateModal = true">
                    <i class="bx bx-user-plus me-1"></i> Tambah User
                </button>
            </div>

            <!-- Filters Bar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body py-3 px-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4">
                            <label class="form-label small fw-semibold text-muted">Cari User</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bx bx-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 bg-light" placeholder="Nama, username, email, HP..." v-model="search" />
                            </div>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label small fw-semibold text-muted">Filter Role</label>
                            <select class="form-select bg-light" v-model="filterRole">
                                <option value="">Semua Role</option>
                                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label small fw-semibold text-muted">Filter Status</label>
                            <select class="form-select bg-light" v-model="filterStatus">
                                <option value="">Semua Status</option>
                                <option value="ACTIVE">Active</option>
                                <option value="INACTIVE">Inactive</option>
                                <option value="SUSPENDED">Suspended</option>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label small fw-semibold text-muted">Filter Outlet</label>
                            <select class="form-select bg-light" v-model="filterOutlet">
                                <option value="">Semua Outlet</option>
                                <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.code }} - {{ o.name }}</option>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <button class="btn btn-outline-secondary w-100" @click="clearFilters">
                                <i class="bx bx-reset me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 small fw-bold">Nama</th>
                                    <th class="small fw-bold">Username</th>
                                    <th class="small fw-bold">Role</th>
                                    <th class="small fw-bold">Outlet</th>
                                    <th class="small fw-bold">Status</th>
                                    <th class="small fw-bold">Last Login</th>
                                    <th class="small fw-bold text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="u in users.data" :key="u.id">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; min-width: 36px;">
                                                <span class="fw-bold text-primary small">{{ (u.name || '?').charAt(0).toUpperCase() }}</span>
                                            </div>
                                            <div>
                                                <div class="fw-semibold small">{{ u.name }}</div>
                                                <small class="text-muted">{{ u.email }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small"><code>{{ u.username }}</code></td>
                                    <td><span class="badge bg-primary bg-opacity-75 small">{{ u.roles?.[0]?.name || u.role || '-' }}</span></td>
                                    <td class="small">{{ u.primary_outlet?.code || '-' }}</td>
                                    <td><span class="badge rounded-pill small" :class="statusBadge(u.status)">{{ u.status || 'ACTIVE' }}</span></td>
                                    <td class="small text-muted">{{ formatDateTime(u.last_login_at) }}</td>
                                    <td class="text-end pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border shadow-xs" data-bs-toggle="dropdown">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <li>
                                                    <Link :href="route('admin.users.show', u.id)" class="dropdown-item small">
                                                        <i class="bx bx-show me-2 text-primary"></i> Detail
                                                    </Link>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><button class="dropdown-item small" @click="openResetModal(u)"><i class="bx bx-key me-2 text-warning"></i> Reset Password</button></li>
                                                <li><button class="dropdown-item small" @click="forceLogout(u)"><i class="bx bx-power-off me-2 text-info"></i> Force Logout</button></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li v-if="u.status !== 'ACTIVE'"><button class="dropdown-item small text-success" @click="toggleStatus(u, 'ACTIVE')"><i class="bx bx-check-circle me-2"></i> Aktifkan</button></li>
                                                <li v-if="u.status !== 'INACTIVE'"><button class="dropdown-item small text-warning" @click="toggleStatus(u, 'INACTIVE')"><i class="bx bx-x-circle me-2"></i> Nonaktifkan</button></li>
                                                <li v-if="u.status !== 'SUSPENDED'"><button class="dropdown-item small text-danger" @click="toggleStatus(u, 'SUSPENDED')"><i class="bx bx-block me-2"></i> Suspend</button></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!users.data?.length">
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="bx bx-ghost fs-1 d-block mb-2 text-secondary"></i>
                                        Tidak ada user ditemukan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="users.last_page > 1" class="card-footer bg-white border-top-0 px-4 py-3">
                    <nav class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Menampilkan {{ users.from }}-{{ users.to }} dari {{ users.total }} user</small>
                        <ul class="pagination pagination-sm mb-0">
                            <li v-for="link in users.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                                <Link v-if="link.url" :href="link.url" class="page-link" v-html="link.label" preserve-state />
                                <span v-else class="page-link" v-html="link.label" />
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <!-- Create User Modal -->
            <div v-if="showCreateModal" class="modal-backdrop-custom" @click.self="showCreateModal = false">
                <div class="modal-dialog-custom">
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="card-header bg-primary text-white py-3 px-4">
                            <h6 class="fw-bold mb-0"><i class="bx bx-user-plus me-2"></i> Tambah User Baru</h6>
                        </div>
                        <div class="card-body p-4">
                            <form @submit.prevent="submitCreate">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Nama Lengkap *</label>
                                        <input type="text" class="form-control" v-model="form.name" :class="{'is-invalid': form.errors.name}" required />
                                        <div v-if="form.errors.name" class="invalid-feedback">{{ form.errors.name }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Username *</label>
                                        <input type="text" class="form-control" v-model="form.username" :class="{'is-invalid': form.errors.username}" required />
                                        <div v-if="form.errors.username" class="invalid-feedback">{{ form.errors.username }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Email *</label>
                                        <input type="email" class="form-control" v-model="form.email" :class="{'is-invalid': form.errors.email}" required />
                                        <div v-if="form.errors.email" class="invalid-feedback">{{ form.errors.email }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Nomor HP</label>
                                        <input type="text" class="form-control" v-model="form.no_hp" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Password *</label>
                                        <input type="password" class="form-control" v-model="form.password" :class="{'is-invalid': form.errors.password}" required />
                                        <div v-if="form.errors.password" class="invalid-feedback">{{ form.errors.password }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Role *</label>
                                        <select class="form-select" v-model="form.role" :class="{'is-invalid': form.errors.role}" required>
                                            <option value="">Pilih Role...</option>
                                            <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                                        </select>
                                        <div v-if="form.errors.role" class="invalid-feedback">{{ form.errors.role }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Primary Outlet</label>
                                        <select class="form-select" v-model="form.outlet_id">
                                            <option value="">Tanpa Outlet</option>
                                            <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.code }} - {{ o.name }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary flex-fill" :disabled="form.processing">
                                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                                        <i v-else class="bx bx-save me-1"></i> Simpan User
                                    </button>
                                    <button type="button" class="btn btn-light" @click="showCreateModal = false">Batal</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reset Password Modal -->
            <div v-if="showResetModal" class="modal-backdrop-custom" @click.self="showResetModal = false">
                <div class="modal-dialog-custom modal-sm-custom">
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="card-header bg-warning text-dark py-3 px-4">
                            <h6 class="fw-bold mb-0"><i class="bx bx-key me-2"></i> Reset Password</h6>
                        </div>
                        <div class="card-body p-4">
                            <p class="small text-muted mb-3">Reset password untuk user: <strong>{{ resetUserName }}</strong></p>
                            <form @submit.prevent="submitReset">
                                <label class="form-label small fw-bold">Password Baru *</label>
                                <input type="password" class="form-control mb-3" v-model="resetForm.password" :class="{'is-invalid': resetForm.errors.password}" required />
                                <div v-if="resetForm.errors.password" class="invalid-feedback">{{ resetForm.errors.password }}</div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-warning flex-fill" :disabled="resetForm.processing">
                                        <i class="bx bx-check me-1"></i> Reset Password
                                    </button>
                                    <button type="button" class="btn btn-light" @click="showResetModal = false">Batal</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>

<style scoped>
.modal-backdrop-custom {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-dialog-custom {
    width: 100%;
    max-width: 650px;
    animation: slideUp 0.3s ease;
}
.modal-sm-custom {
    max-width: 420px;
}
@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>
