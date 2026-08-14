<script setup>
import { ref, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';

import Swal from 'sweetalert2';

const props = defineProps({
    opnames: Object,
    metrics: Object,
    filters: Object,
});

const searchFilter = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const isLoading = ref(false);

let debounceTimer = null;
const debouncedSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        isLoading.value = true;
        router.get(
            route('opname.index'),
            {
                search: searchFilter.value,
                status: statusFilter.value,
                start_date: startDate.value,
                end_date: endDate.value,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onFinish: () => { isLoading.value = false; }
            }
        );
    }, 300);
};

watch([searchFilter, statusFilter, startDate, endDate], () => {
    debouncedSearch();
});

const createForm = useForm({
    notes: 'Stok Opname Manual Routine',
});

const submitCreateSO = () => {
    Swal.fire({
        title: 'Buat Stok Opname Baru?',
        text: 'Sistem akan mengambil snapshot stok obat saat ini sebagai stok sistem.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buat Sesi Baru!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3b6bff',
        cancelButtonColor: '#64748b',
        customClass: { popup: 'rounded-4 shadow-lg border-0' }
    }).then((res) => {
        if (res.isConfirmed) {
            createForm.post(route('opname.store'));
        }
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
};

const deleteSO = (so) => {
    Swal.fire({
        title: 'Hapus Dokumen Stok Opname?',
        text: `Dokumen ${so.opname_number} dan seluruh item hasil opname akan dihapus secara permanen!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus Sekarang!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        customClass: { popup: 'rounded-4 shadow-lg border-0' }
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(route('opname.destroy', so.id));
        }
    });
};

const getStatusBadge = (status) => {
    const st = (status || '').toUpperCase();
    switch(st) {
        case 'DRAFT':
        case 'COUNTING': return 'bg-warning text-dark';
        case 'COMPLETED':
        case 'APPROVED': return 'bg-success text-white';
        case 'CANCELLED': return 'bg-danger text-white';
        default: return 'bg-secondary text-white';
    }
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">
                            <i class="bx bx-task text-primary me-2"></i>Riwayat Stok Opname (SO) Manual
                        </h2>
                        <p class="text-muted small mb-0">Kelola penginputan stok fisik manual, perhitungan selisih stok, dan penyesuaian otomatis ke stok sistem.</p>
                    </div>

                    <button @click="submitCreateSO" :disabled="createForm.processing" class="btn btn-primary fw-bold shadow-sm">
                        <i class="bx bx-plus-circle me-1"></i> Buat Stok Opname Baru
                    </button>
                </div>

                <!-- Dashboard Summary Metrics -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 12px; border-left: 5px solid #3b6bff !important;">
                            <small class="text-muted d-block fw-bold text-uppercase">Total Sesi Opname</small>
                            <strong class="text-dark fs-4">{{ metrics?.total || 0 }} Dokumen</strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 12px; border-left: 5px solid #f59e0b !important;">
                            <small class="text-muted d-block fw-bold text-uppercase">SO Aktif (DRAFT)</small>
                            <strong class="text-warning fs-4">{{ metrics?.draft || 0 }} Sesi Belum Final</strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 12px; border-left: 5px solid #10b981 !important;">
                            <small class="text-muted d-block fw-bold text-uppercase">SO Selesai (COMPLETED)</small>
                            <strong class="text-success fs-4">{{ metrics?.completed || 0 }} Sesi Final</strong>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm p-3 mb-4" style="border-radius: 12px;">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" v-model="searchFilter" class="form-control" placeholder="Cari No. SO (Contoh: SO-2026...)...">
                        </div>
                        <div class="col-md-3">
                            <select v-model="statusFilter" class="form-select">
                                <option value="">-- Semua Status --</option>
                                <option value="DRAFT">DRAFT (Proses Input)</option>
                                <option value="COMPLETED">COMPLETED (Final)</option>
                                <option value="CANCELLED">CANCELLED (Batal)</option>
                            </select>
                        </div>
                        <div class="col-md-5 d-flex gap-2 align-items-center">
                            <input type="date" v-model="startDate" class="form-control">
                            <span class="text-muted">s/d</span>
                            <input type="date" v-model="endDate" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- History Table -->
                <div class="position-relative border bg-white border-secondary border-opacity-75 p-3 mb-2 rounded-3 overflow-hidden">
                    <!-- Lazy Loading Indicator -->
                    <div v-if="isLoading" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-25" style="z-index: 10; backdrop-filter: blur(1px);">
                        <div class="d-flex align-items-center gap-2 px-3 py-2 bg-dark text-white rounded-3 shadow">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span class="small fw-semibold">Memuat Data...</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>No SO</th>
                                    <th>Tanggal</th>
                                    <th>Petugas / User</th>
                                    <th class="text-center">Total Item</th>
                                    <th class="text-center">Sesuai / Kurang / Lebih</th>
                                    <th class="text-end">Nilai Sistem</th>
                                    <th class="text-end">Nilai Fisik</th>
                                    <th class="text-end">Selisih Nilai</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="so in opnames.data" :key="so.id">
                                    <td class="fw-bold text-primary">{{ so.opname_number }}</td>
                                    <td>{{ formatDate(so.opname_date) }}</td>
                                    <td>{{ so.user?.name || '-' }}</td>
                                    <td class="text-center fw-bold">{{ so.total_items }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-success me-1" title="Sesuai">{{ so.items_matched }}</span>
                                        <span class="badge bg-danger me-1" title="Kurang">{{ so.items_deficit }}</span>
                                        <span class="badge bg-primary" title="Lebih">{{ so.items_surplus }}</span>
                                    </td>
                                    <td class="text-end text-muted">{{ formatCurrency(so.system_total_value) }}</td>
                                    <td class="text-end fw-bold text-dark">{{ formatCurrency(so.physical_total_value) }}</td>
                                    <td class="text-end" :class="so.difference_value === 0 ? 'text-success fw-bold' : (so.difference_value < 0 ? 'text-danger fw-bold' : 'text-primary fw-bold')">
                                        {{ formatCurrency(so.difference_value) }}
                                    </td>
                                    <td class="text-center">
                                        <span :class="['badge', getStatusBadge(so.status)]">{{ so.status }}</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <Link :href="route('opname.show', so.id)" :class="['btn btn-sm', so.status === 'DRAFT' ? 'btn-warning fw-bold' : 'btn-outline-primary']">
                                                <i class="bx" :class="so.status === 'DRAFT' ? 'bx-edit' : 'bx-show'"></i>
                                                {{ so.status === 'DRAFT' ? ' Input' : ' Detail' }}
                                            </Link>
                                            <button @click="deleteSO(so)" class="btn btn-sm btn-outline-danger" title="Hapus SO">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!opnames.data || opnames.data.length === 0">
                                    <td colspan="10" class="text-center py-4 text-muted">Belum ada riwayat dokumen Stok Opname.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links -->
                    <Pagination
                        :links="opnames.links"
                        :from="opnames.from"
                        :to="opnames.to"
                        :total="opnames.total"
                    />
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>
