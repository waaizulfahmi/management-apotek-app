<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    logs: Object,
    modules: Array,
    actions: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const filterModule = ref(props.filters?.module || '');
const filterAction = ref(props.filters?.action || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

let debounce = null;
const applyFilters = () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('admin.audit-logs.index'), {
            search: search.value || undefined,
            module: filterModule.value || undefined,
            action: filterAction.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        }, { preserveState: true, replace: true });
    }, 350);
};

watch([search, filterModule, filterAction, dateFrom, dateTo], applyFilters);

const clearFilters = () => {
    search.value = '';
    filterModule.value = '';
    filterAction.value = '';
    dateFrom.value = '';
    dateTo.value = '';
};

const formatDateTime = (val) => {
    if (!val) return '-';
    return new Date(val).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const actionBadgeClass = (action) => {
    if (action.includes('CREATE')) return 'bg-success';
    if (action.includes('EDIT') || action.includes('UPDATE')) return 'bg-info';
    if (action.includes('DELETE') || action.includes('SUSPEND')) return 'bg-danger';
    if (action.includes('RESET') || action.includes('TOGGLE')) return 'bg-warning text-dark';
    if (action.includes('LOGIN') || action.includes('APPROVE')) return 'bg-primary';
    if (action.includes('REJECT')) return 'bg-danger';
    return 'bg-secondary';
};
</script>

<template>
    <Head title="Audit Log" />
    <LegacyLayout>
        <div class="content p-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1"><i class="bx bx-history me-2 text-primary"></i>Audit Log</h4>
                    <p class="text-muted small mb-0">Riwayat aktivitas seluruh user di sistem apotek</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body py-3 px-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3">
                            <label class="form-label small fw-semibold text-muted">Cari</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bx bx-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 bg-light" placeholder="User, action, modul..." v-model="search" />
                            </div>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label small fw-semibold text-muted">Module</label>
                            <select class="form-select bg-light" v-model="filterModule">
                                <option value="">Semua</option>
                                <option v-for="m in modules" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label small fw-semibold text-muted">Action</label>
                            <select class="form-select bg-light" v-model="filterAction">
                                <option value="">Semua</option>
                                <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label small fw-semibold text-muted">Dari Tanggal</label>
                            <input type="date" class="form-control bg-light" v-model="dateFrom" />
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label small fw-semibold text-muted">Sampai Tanggal</label>
                            <input type="date" class="form-control bg-light" v-model="dateTo" />
                        </div>
                        <div class="col-lg-1">
                            <button class="btn btn-outline-secondary w-100" @click="clearFilters">
                                <i class="bx bx-reset"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audit Log Table -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 small fw-bold">User</th>
                                    <th class="small fw-bold">Action</th>
                                    <th class="small fw-bold">Module</th>
                                    <th class="small fw-bold">Tanggal & Waktu</th>
                                    <th class="small fw-bold">IP Address</th>
                                    <th class="small fw-bold">Data Sebelum</th>
                                    <th class="small fw-bold">Data Sesudah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="log in logs.data" :key="log.id">
                                    <td class="ps-4">
                                        <div class="fw-semibold small">{{ log.user?.name || 'System' }}</div>
                                        <small class="text-muted" v-if="log.user">{{ log.user.email }}</small>
                                    </td>
                                    <td><span class="badge small" :class="actionBadgeClass(log.action)">{{ log.action }}</span></td>
                                    <td class="small">{{ log.module }}</td>
                                    <td class="small text-muted">{{ formatDateTime(log.created_at) }}</td>
                                    <td class="small text-muted font-monospace">{{ log.ip_address || '-' }}</td>
                                    <td>
                                        <small v-if="log.old_values" class="text-muted d-block" style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" :title="JSON.stringify(log.old_values)">
                                            {{ JSON.stringify(log.old_values) }}
                                        </small>
                                        <span v-else class="text-muted small">-</span>
                                    </td>
                                    <td>
                                        <small v-if="log.new_values" class="text-muted d-block" style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" :title="JSON.stringify(log.new_values)">
                                            {{ JSON.stringify(log.new_values) }}
                                        </small>
                                        <span v-else class="text-muted small">-</span>
                                    </td>
                                </tr>
                                <tr v-if="!logs.data?.length">
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="bx bx-ghost fs-1 d-block mb-2 text-secondary"></i>
                                        Belum ada aktivitas yang tercatat.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="card-footer bg-white border-top-0 px-4 py-3">
                    <nav class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">{{ logs.from }}-{{ logs.to }} dari {{ logs.total }}</small>
                        <ul class="pagination pagination-sm mb-0">
                            <li v-for="link in logs.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                                <Link v-if="link.url" :href="link.url" class="page-link" v-html="link.label" preserve-state />
                                <span v-else class="page-link" v-html="link.label" />
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
