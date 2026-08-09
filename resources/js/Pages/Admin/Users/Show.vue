<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { showConfirm } from '@/Utils/swal';

const props = defineProps({
    user: Object,
    permissions: Array,
    outlets: Array,
});

const activeTab = ref('profile');

const editForm = useForm({
    name: props.user.name,
    username: props.user.username,
    email: props.user.email,
    no_hp: props.user.no_hp || '',
    role: props.user.roles?.[0]?.name || props.user.role || '',
    status: props.user.status || 'ACTIVE',
    outlet_id: props.user.outlet_id || '',
    outlet_ids: props.user.outlets?.map(o => o.id) || [],
});

const isEditing = ref(false);

const submitEdit = () => {
    editForm.put(route('admin.users.update', props.user.id), {
        onSuccess: () => { isEditing.value = false; },
    });
};

const toggleOutlet = (outletId) => {
    const idx = editForm.outlet_ids.indexOf(outletId);
    if (idx === -1) {
        editForm.outlet_ids.push(outletId);
    } else {
        editForm.outlet_ids.splice(idx, 1);
    }
};

const formatDateTime = (val) => {
    if (!val) return '-';
    return new Date(val).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const statusBadge = (status) => {
    const map = { 'ACTIVE': 'bg-success', 'INACTIVE': 'bg-warning text-dark', 'SUSPENDED': 'bg-danger' };
    return map[status] || 'bg-secondary';
};

// Group permissions by module for display
const permissionsByModule = computed(() => {
    const grouped = {};
    if (props.permissions) {
        props.permissions.forEach(p => {
            const parts = p.name.split('.');
            const module = parts[0];
            const action = parts[1] || '';
            if (!grouped[module]) grouped[module] = [];
            grouped[module].push(action);
        });
    }
    return grouped;
});

const moduleLabels = {
    dashboard: 'Dashboard', users: 'User & Access', products: 'Produk', stock: 'Stok',
    opname: 'Stok Opname', stock_card: 'Kartu Stok', pos: 'Penjualan / POS', po: 'Pembelian / PO',
    suppliers: 'Supplier / PBF', membership: 'Membership', finance: 'Keuangan', reports: 'Laporan',
    settings: 'Pengaturan',
};

const actionLabels = { view: 'View', create: 'Create', edit: 'Edit', delete: 'Delete', approve: 'Approve', export: 'Export' };
const allActions = ['view', 'create', 'edit', 'delete', 'approve', 'export'];
</script>

<template>
    <Head :title="`Detail User — ${user.name}`" />
    <LegacyLayout>
        <div class="content p-4">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><Link :href="route('admin.users.index')">Manajemen User</Link></li>
                    <li class="breadcrumb-item active">{{ user.name }}</li>
                </ol>
            </nav>

            <!-- User Header Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                                <span class="fw-bold text-primary fs-3">{{ (user.name || '?').charAt(0).toUpperCase() }}</span>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1">{{ user.name }}</h5>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <span class="badge bg-primary bg-opacity-75">{{ user.roles?.[0]?.name || user.role }}</span>
                                    <span class="badge rounded-pill" :class="statusBadge(user.status)">{{ user.status || 'ACTIVE' }}</span>
                                    <small class="text-muted">{{ user.email }}</small>
                                    <small class="text-muted" v-if="user.no_hp">• {{ user.no_hp }}</small>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Outlet: <strong>{{ user.primary_outlet?.name || '-' }}</strong>
                                    <span v-if="user.outlets?.length > 1"> + {{ user.outlets.length - 1 }} lainnya</span>
                                     | Login terakhir: {{ formatDateTime(user.last_login_at) }}
                                     | Dibuat: {{ formatDateTime(user.created_at) }}
                                </small>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" @click="isEditing = !isEditing">
                                <i class="bx bx-edit me-1"></i> {{ isEditing ? 'Batal' : 'Edit' }}
                            </button>
                            <Link :href="route('admin.users.index')" class="btn btn-sm btn-light border">
                                <i class="bx bx-arrow-back me-1"></i> Kembali
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-pills mb-4 gap-2">
                <li class="nav-item">
                    <button class="nav-link rounded-pill px-4" :class="{ active: activeTab === 'profile' }" @click="activeTab = 'profile'">
                        <i class="bx bx-user me-1"></i> Profile
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link rounded-pill px-4" :class="{ active: activeTab === 'permissions' }" @click="activeTab = 'permissions'">
                        <i class="bx bx-shield-quarter me-1"></i> Role & Permission
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link rounded-pill px-4" :class="{ active: activeTab === 'outlets' }" @click="activeTab = 'outlets'">
                        <i class="bx bx-store me-1"></i> Outlet Access
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link rounded-pill px-4" :class="{ active: activeTab === 'audit' }" @click="activeTab = 'audit'">
                        <i class="bx bx-history me-1"></i> Activity Log
                    </button>
                </li>
            </ul>

            <!-- TAB: Profile -->
            <div v-if="activeTab === 'profile'">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white pt-3 px-4">
                        <h6 class="fw-bold mb-0"><i class="bx bx-user me-2 text-primary"></i>Informasi Profil</h6>
                    </div>
                    <div class="card-body p-4">
                        <form v-if="isEditing" @submit.prevent="submitEdit">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Nama</label>
                                    <input type="text" class="form-control" v-model="editForm.name" :class="{'is-invalid': editForm.errors.name}" />
                                    <div v-if="editForm.errors.name" class="invalid-feedback">{{ editForm.errors.name }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Username</label>
                                    <input type="text" class="form-control" v-model="editForm.username" :class="{'is-invalid': editForm.errors.username}" />
                                    <div v-if="editForm.errors.username" class="invalid-feedback">{{ editForm.errors.username }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Email</label>
                                    <input type="email" class="form-control" v-model="editForm.email" :class="{'is-invalid': editForm.errors.email}" />
                                    <div v-if="editForm.errors.email" class="invalid-feedback">{{ editForm.errors.email }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Nomor HP</label>
                                    <input type="text" class="form-control" v-model="editForm.no_hp" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Role</label>
                                    <select class="form-select" v-model="editForm.role">
                                        <option v-for="r in (user.roles || []).map(rx => rx.name)" :key="r" :value="r">{{ r }}</option>
                                        <option value="Super Admin">Super Admin</option>
                                        <option value="Owner">Owner</option>
                                        <option value="Apoteker">Apoteker</option>
                                        <option value="Kasir">Kasir</option>
                                        <option value="Gudang">Gudang</option>
                                        <option value="Purchasing">Purchasing</option>
                                        <option value="Keuangan">Keuangan</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Status</label>
                                    <select class="form-select" v-model="editForm.status">
                                        <option value="ACTIVE">Active</option>
                                        <option value="INACTIVE">Inactive</option>
                                        <option value="SUSPENDED">Suspended</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Primary Outlet</label>
                                    <select class="form-select" v-model="editForm.outlet_id">
                                        <option value="">Tanpa Outlet</option>
                                        <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.code }} - {{ o.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-4" :disabled="editForm.processing">
                                <i class="bx bx-save me-1"></i> Simpan Perubahan
                            </button>
                        </form>

                        <div v-else>
                            <div class="row g-3">
                                <div class="col-md-6"><div class="bg-light rounded-3 p-3"><small class="text-muted d-block">Nama</small><span class="fw-semibold">{{ user.name }}</span></div></div>
                                <div class="col-md-6"><div class="bg-light rounded-3 p-3"><small class="text-muted d-block">Username</small><code>{{ user.username }}</code></div></div>
                                <div class="col-md-6"><div class="bg-light rounded-3 p-3"><small class="text-muted d-block">Email</small><span>{{ user.email }}</span></div></div>
                                <div class="col-md-6"><div class="bg-light rounded-3 p-3"><small class="text-muted d-block">Nomor HP</small><span>{{ user.no_hp || '-' }}</span></div></div>
                                <div class="col-md-6"><div class="bg-light rounded-3 p-3"><small class="text-muted d-block">Role</small><span class="badge bg-primary">{{ user.roles?.[0]?.name || user.role }}</span></div></div>
                                <div class="col-md-6"><div class="bg-light rounded-3 p-3"><small class="text-muted d-block">Status</small><span class="badge rounded-pill" :class="statusBadge(user.status)">{{ user.status || 'ACTIVE' }}</span></div></div>
                                <div class="col-md-6"><div class="bg-light rounded-3 p-3"><small class="text-muted d-block">Outlet Utama</small><span>{{ user.primary_outlet?.name || '-' }}</span></div></div>
                                <div class="col-md-6"><div class="bg-light rounded-3 p-3"><small class="text-muted d-block">Terakhir Login</small><span>{{ formatDateTime(user.last_login_at) }}</span></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: Role & Permission -->
            <div v-if="activeTab === 'permissions'">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white pt-3 px-4">
                        <h6 class="fw-bold mb-0"><i class="bx bx-shield-quarter me-2 text-primary"></i>Hak Akses User</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4 small fw-bold">Modul</th>
                                        <th v-for="a in allActions" :key="a" class="text-center small fw-bold text-capitalize">{{ actionLabels[a] }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(label, key) in moduleLabels" :key="key">
                                        <td class="ps-4 fw-semibold small">{{ label }}</td>
                                        <td v-for="a in allActions" :key="a" class="text-center">
                                            <i v-if="permissionsByModule[key]?.includes(a)" class="bx bx-check-circle text-success fs-5"></i>
                                            <i v-else class="bx bx-x-circle text-danger opacity-25 fs-5"></i>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: Outlet Access -->
            <div v-if="activeTab === 'outlets'">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white pt-3 px-4 d-flex justify-content-between">
                        <h6 class="fw-bold mb-0"><i class="bx bx-store me-2 text-primary"></i>Akses Outlet</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div v-for="outlet in outlets" :key="outlet.id" class="col-md-6">
                                <div class="border rounded-3 p-3 d-flex align-items-center gap-3"
                                     :class="{ 'border-primary bg-primary bg-opacity-10': user.outlets?.some(uo => uo.id === outlet.id) }">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" disabled
                                               :checked="user.outlets?.some(uo => uo.id === outlet.id)" />
                                    </div>
                                    <div>
                                        <div class="fw-semibold small">{{ outlet.name }}</div>
                                        <small class="text-muted">{{ outlet.code }} — {{ outlet.address || 'Alamat belum diisi' }}</small>
                                    </div>
                                    <span v-if="user.outlet_id === outlet.id" class="badge bg-primary ms-auto">Primary</span>
                                </div>
                            </div>
                        </div>
                        <p v-if="!outlets?.length" class="text-muted text-center py-3">Belum ada data outlet.</p>
                    </div>
                </div>
            </div>

            <!-- TAB: Activity / Audit Log -->
            <div v-if="activeTab === 'audit'">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white pt-3 px-4">
                        <h6 class="fw-bold mb-0"><i class="bx bx-history me-2 text-primary"></i>Riwayat Aktivitas</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4 small">Action</th>
                                        <th class="small">Module</th>
                                        <th class="small">IP Address</th>
                                        <th class="small">Tanggal</th>
                                        <th class="small">Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="log in user.audit_logs?.slice(0, 20)" :key="log.id">
                                        <td class="ps-4">
                                            <span class="badge" :class="{
                                                'bg-success': log.action.includes('CREATE'),
                                                'bg-info': log.action.includes('EDIT') || log.action.includes('UPDATE'),
                                                'bg-danger': log.action.includes('DELETE') || log.action.includes('SUSPEND'),
                                                'bg-warning text-dark': log.action.includes('RESET') || log.action.includes('TOGGLE'),
                                                'bg-primary': log.action.includes('LOGIN') || log.action.includes('APPROVE'),
                                                'bg-secondary': !['CREATE','EDIT','UPDATE','DELETE','RESET','TOGGLE','LOGIN','APPROVE','SUSPEND'].some(k => log.action.includes(k)),
                                            }">{{ log.action }}</span>
                                        </td>
                                        <td class="small">{{ log.module }}</td>
                                        <td class="small text-muted font-monospace">{{ log.ip_address || '-' }}</td>
                                        <td class="small text-muted">{{ formatDateTime(log.created_at) }}</td>
                                        <td class="small">
                                            <span v-if="log.old_values || log.new_values" class="text-muted"
                                                  :title="JSON.stringify({ before: log.old_values, after: log.new_values })">
                                                <i class="bx bx-info-circle"></i> Hover utk detail
                                            </span>
                                            <span v-else class="text-muted">-</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!user.audit_logs?.length">
                                        <td colspan="5" class="text-center text-muted py-4">Belum ada aktivitas tercatat.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
