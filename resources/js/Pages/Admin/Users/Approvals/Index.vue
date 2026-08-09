<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { showConfirm } from '@/Utils/swal';

const props = defineProps({
    approvals: Object,
    filters: Object,
});

const filterStatus = ref(props.filters?.status || '');
const filterModule = ref(props.filters?.module || '');

const showRejectModal = ref(false);
const rejectId = ref(null);
const rejectForm = useForm({ reason: '' });

let debounce = null;
const applyFilters = () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('admin.approvals.index'), {
            status: filterStatus.value || undefined,
            module: filterModule.value || undefined,
        }, { preserveState: true, replace: true });
    }, 350);
};

watch([filterStatus, filterModule], applyFilters);

const approveRequest = (approval) => {
    showConfirm(
        'Setujui Permintaan?',
        `Setujui permintaan "${approval.description}" dari ${approval.requester?.name}?`,
        () => {
            router.post(route('admin.approvals.approve', approval.id), { reason: '' });
        }
    );
};

const openRejectModal = (approval) => {
    rejectId.value = approval.id;
    rejectForm.reason = '';
    showRejectModal.value = true;
};

const submitReject = () => {
    rejectForm.post(route('admin.approvals.reject', rejectId.value), {
        onSuccess: () => {
            showRejectModal.value = false;
            rejectForm.reset();
        },
    });
};

const formatDateTime = (val) => {
    if (!val) return '-';
    return new Date(val).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const statusBadge = (status) => {
    const map = { 'PENDING': 'bg-warning text-dark', 'APPROVED': 'bg-success', 'REJECTED': 'bg-danger' };
    return map[status] || 'bg-secondary';
};
</script>

<template>
    <Head title="Approval Requests" />
    <LegacyLayout>
        <div class="content p-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1"><i class="bx bx-check-shield me-2 text-primary"></i>Approval Requests</h4>
                    <p class="text-muted small mb-0">Kelola permohonan persetujuan untuk aktivitas penting</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body py-3 px-4">
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <label class="form-label small fw-semibold text-muted">Filter Status</label>
                            <select class="form-select bg-light" v-model="filterStatus">
                                <option value="">Semua Status</option>
                                <option value="PENDING">Pending</option>
                                <option value="APPROVED">Approved</option>
                                <option value="REJECTED">Rejected</option>
                            </select>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label small fw-semibold text-muted">Filter Module</label>
                            <select class="form-select bg-light" v-model="filterModule">
                                <option value="">Semua Module</option>
                                <option value="POS">POS</option>
                                <option value="Stock">Stock</option>
                                <option value="Finance">Finance</option>
                                <option value="Membership">Membership</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approval Table -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 small fw-bold">Pemohon</th>
                                    <th class="small fw-bold">Module</th>
                                    <th class="small fw-bold">Jenis</th>
                                    <th class="small fw-bold">Deskripsi</th>
                                    <th class="small fw-bold">Status</th>
                                    <th class="small fw-bold">Tanggal</th>
                                    <th class="small fw-bold text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="a in approvals.data" :key="a.id">
                                    <td class="ps-4">
                                        <div class="fw-semibold small">{{ a.requester?.name || '-' }}</div>
                                    </td>
                                    <td class="small">{{ a.module }}</td>
                                    <td><span class="badge bg-secondary small">{{ a.action_type }}</span></td>
                                    <td class="small" style="max-width: 200px;">{{ a.description }}</td>
                                    <td><span class="badge rounded-pill small" :class="statusBadge(a.status)">{{ a.status }}</span></td>
                                    <td class="small text-muted">{{ formatDateTime(a.created_at) }}</td>
                                    <td class="text-end pe-4">
                                        <template v-if="a.status === 'PENDING'">
                                            <button class="btn btn-sm btn-success me-1" @click="approveRequest(a)" title="Approve">
                                                <i class="bx bx-check"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" @click="openRejectModal(a)" title="Reject">
                                                <i class="bx bx-x"></i>
                                            </button>
                                        </template>
                                        <template v-else>
                                            <small class="text-muted">
                                                {{ a.approver?.name || '-' }}
                                                <span v-if="a.reason"> — "{{ a.reason }}"</span>
                                            </small>
                                        </template>
                                    </td>
                                </tr>
                                <tr v-if="!approvals.data?.length">
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="bx bx-check-shield fs-1 d-block mb-2 text-secondary"></i>
                                        Tidak ada permintaan approval.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="approvals.last_page > 1" class="card-footer bg-white border-top-0 px-4 py-3">
                    <nav class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">{{ approvals.from }}-{{ approvals.to }} dari {{ approvals.total }}</small>
                        <ul class="pagination pagination-sm mb-0">
                            <li v-for="link in approvals.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                                <Link v-if="link.url" :href="link.url" class="page-link" v-html="link.label" preserve-state />
                                <span v-else class="page-link" v-html="link.label" />
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <!-- Reject Modal -->
            <div v-if="showRejectModal" class="modal-backdrop-custom" @click.self="showRejectModal = false">
                <div class="modal-dialog-custom modal-sm-custom">
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="card-header bg-danger text-white py-3 px-4">
                            <h6 class="fw-bold mb-0"><i class="bx bx-x-circle me-2"></i> Tolak Permintaan</h6>
                        </div>
                        <div class="card-body p-4">
                            <form @submit.prevent="submitReject">
                                <label class="form-label small fw-bold">Alasan Penolakan *</label>
                                <textarea class="form-control mb-3" rows="3" v-model="rejectForm.reason"
                                    :class="{'is-invalid': rejectForm.errors.reason}" placeholder="Tuliskan alasan penolakan..." required></textarea>
                                <div v-if="rejectForm.errors.reason" class="invalid-feedback">{{ rejectForm.errors.reason }}</div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-danger flex-fill" :disabled="rejectForm.processing">
                                        <i class="bx bx-x me-1"></i> Tolak
                                    </button>
                                    <button type="button" class="btn btn-light" @click="showRejectModal = false">Batal</button>
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
.modal-sm-custom { max-width: 420px; }
@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>
