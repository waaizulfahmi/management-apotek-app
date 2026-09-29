<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import { showConfirm } from '@/Utils/swal';

const props = defineProps({
    roles: Array,
    modules: Object,
    actions: Array,
    allPermissions: Array,
});

const showCreateRoleModal = ref(false);
const selectedRoleId = ref(props.roles?.[0]?.id || null);

const newRoleForm = useForm({ name: '' });

// Build a reactive matrix: { roleId: { 'module.action': true/false } }
const matrix = reactive({});
props.roles.forEach(role => {
    matrix[role.id] = {};
    Object.keys(props.modules).forEach(modKey => {
        props.actions.forEach(action => {
            const permName = `${modKey}.${action}`;
            matrix[role.id][permName] = role.permissions?.some(p => p.name === permName) || false;
        });
    });
});

const selectedRole = () => props.roles.find(r => r.id === selectedRoleId.value);

const togglePerm = (roleId, permName) => {
    matrix[roleId][permName] = !matrix[roleId][permName];
};

const savePermissions = (roleId) => {
    const perms = Object.keys(matrix[roleId]).filter(k => matrix[roleId][k]);
    router.put(route('admin.roles.permissions.update', roleId), {
        permissions: perms,
    }, {
        preserveState: true,
    });
};

const submitCreateRole = () => {
    newRoleForm.post(route('admin.roles.store'), {
        onSuccess: () => {
            showCreateRoleModal.value = false;
            newRoleForm.reset();
        },
    });
};

const deleteRole = (role) => {
    showConfirm(
        `Hapus Role "${role.name}"?`,
        'Role custom ini akan dihapus secara permanen. Role default tidak dapat dihapus.',
        () => {
            router.delete(route('admin.roles.destroy', role.id));
        }
    );
};

const protectedRoles = ['Super Admin', 'Owner', 'Apoteker', 'Kasir', 'Gudang', 'Purchasing', 'Keuangan'];

const actionLabels = { view: 'View', create: 'Create', edit: 'Edit', delete: 'Delete', approve: 'Approve', export: 'Export', refund: 'Refund', cancel: 'Cancel' };

const roleBadgeClass = (name) => {
    const map = {
        'Super Admin': 'bg-danger', 'Owner': 'bg-primary', 'Apoteker': 'bg-success',
        'Kasir': 'bg-warning text-dark', 'Gudang': 'bg-info', 'Purchasing': 'bg-secondary', 'Keuangan': 'bg-dark',
    };
    return map[name] || 'bg-secondary';
};
</script>

<template>
    <Head title="Role & Permission Matrix" />
    <LegacyLayout>
        <div class="content p-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1"><i class="bx bx-shield-quarter me-2 text-primary"></i>Role & Permission Matrix</h4>
                    <p class="text-muted small mb-0">Kelola hak akses setiap role dengan model checkbox per modul</p>
                </div>
                <button class="btn btn-primary shadow-sm" @click="showCreateRoleModal = true">
                    <i class="bx bx-plus me-1"></i> Tambah Role
                </button>
            </div>

            <!-- Role Tabs -->
            <div class="d-flex gap-2 flex-wrap mb-4">
                <button v-for="role in roles" :key="role.id"
                    class="btn btn-sm rounded-pill px-3 py-2 shadow-xs border"
                    :class="selectedRoleId === role.id ? 'btn-primary text-white' : 'btn-light'"
                    @click="selectedRoleId = role.id">
                    <span class="me-1">{{ role.name }}</span>
                    <span class="badge bg-white bg-opacity-25 text-white rounded-pill ms-1" v-if="selectedRoleId === role.id">{{ role.permissions?.length || 0 }}</span>
                </button>
            </div>

            <!-- Permission Matrix Table -->
            <div v-for="role in roles" :key="'matrix-' + role.id" v-show="selectedRoleId === role.id">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white pt-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill" :class="roleBadgeClass(role.name)">{{ role.name }}</span>
                            <small class="text-muted">{{ role.permissions?.length || 0 }} permissions aktif</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary" @click="savePermissions(role.id)">
                                <i class="bx bx-save me-1"></i> Simpan Perubahan
                            </button>
                            <button v-if="!protectedRoles.includes(role.name)" class="btn btn-sm btn-outline-danger" @click="deleteRole(role)">
                                <i class="bx bx-trash me-1"></i> Hapus
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4 small fw-bold" style="min-width: 180px;">Modul</th>
                                        <th v-for="a in actions" :key="a" class="text-center small fw-bold" style="width: 90px;">{{ actionLabels[a] }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(label, modKey) in modules" :key="modKey">
                                        <td class="ps-4 fw-semibold small">{{ label }}</td>
                                        <td v-for="a in actions" :key="a" class="text-center">
                                            <div class="form-check d-flex justify-content-center m-0">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    :checked="matrix[role.id]?.[modKey + '.' + a]"
                                                    @change="togglePerm(role.id, modKey + '.' + a)"
                                                    style="width: 20px; height: 20px; cursor: pointer;"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Role Modal -->
            <div v-if="showCreateRoleModal" class="modal-backdrop-custom" @click.self="showCreateRoleModal = false">
                <div class="modal-dialog-custom modal-sm-custom">
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="card-header bg-primary text-white py-3 px-4">
                            <h6 class="fw-bold mb-0"><i class="bx bx-plus me-2"></i> Tambah Role Baru</h6>
                        </div>
                        <div class="card-body p-4">
                            <form @submit.prevent="submitCreateRole">
                                <label class="form-label small fw-bold">Nama Role *</label>
                                <input type="text" class="form-control mb-3" v-model="newRoleForm.name"
                                    :class="{'is-invalid': newRoleForm.errors.name}" placeholder="Contoh: Finance Manager" required />
                                <div v-if="newRoleForm.errors.name" class="invalid-feedback">{{ newRoleForm.errors.name }}</div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-fill" :disabled="newRoleForm.processing">
                                        <i class="bx bx-save me-1"></i> Simpan
                                    </button>
                                    <button type="button" class="btn btn-light" @click="showCreateRoleModal = false">Batal</button>
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
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
}
.modal-dialog-custom { width: 100%; max-width: 420px; animation: slideUp 0.3s ease; }
@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>
