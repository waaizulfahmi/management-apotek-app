<script setup>
import { ref } from 'vue';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { useForm, router, Link } from '@inertiajs/vue3';

const props = defineProps({
    outlets: Array,
    users: Array,
    activeOutletId: Number,
    filters: Object,
});

const searchFilter = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'ALL');

const filterData = () => {
    router.get(route('outlets.index'), {
        search: searchFilter.value,
        status: statusFilter.value,
    }, { preserveState: true, replace: true });
};

// Create Form
const createForm = useForm({
    code: 'OUT-' + strPad(Math.floor(1 + Math.random() * 999), 3),
    name: '',
    legal_name: '',
    address: '',
    province: '',
    city: '',
    district: '',
    postal_code: '',
    phone: '',
    email: '',
    pic_name: '',
    status: 'ACTIVE',
    user_ids: [],
});

function strPad(n, width) {
    n = n + '';
    return n.length >= width ? n : new Array(width - n.length + 1).join('0') + n;
}

const submitCreate = () => {
    createForm.post(route('outlets.store'), {
        onSuccess: () => {
            createForm.reset();
            createForm.code = 'OUT-' + strPad(Math.floor(1 + Math.random() * 999), 3);
            const modalEl = document.getElementById('tambahOutletModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    });
};

// Edit Form
const editOutletData = ref(null);
const editForm = useForm({
    code: '',
    name: '',
    legal_name: '',
    address: '',
    province: '',
    city: '',
    district: '',
    postal_code: '',
    phone: '',
    email: '',
    pic_name: '',
    status: 'ACTIVE',
    user_ids: [],
});

const openEditModal = (outlet) => {
    editOutletData.value = outlet;
    editForm.code = outlet.code;
    editForm.name = outlet.name;
    editForm.legal_name = outlet.legal_name || '';
    editForm.address = outlet.address || '';
    editForm.province = outlet.province || '';
    editForm.city = outlet.city || '';
    editForm.district = outlet.district || '';
    editForm.postal_code = outlet.postal_code || '';
    editForm.phone = outlet.phone || '';
    editForm.email = outlet.email || '';
    editForm.pic_name = outlet.pic_name || '';
    editForm.status = outlet.status || 'ACTIVE';
    editForm.user_ids = outlet.assigned_user_ids || [];

    const modalEl = document.getElementById('editOutletModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
};

const submitEdit = () => {
    if (!editOutletData.value) return;
    editForm.put(route('outlets.update', editOutletData.value.id), {
        onSuccess: () => {
            const modalEl = document.getElementById('editOutletModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    });
};

// Detail Modal
const selectedDetail = ref(null);
const openDetailModal = (outlet) => {
    selectedDetail.value = outlet;
    const modalEl = document.getElementById('detailOutletModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
};

// Actions
const setMainOutlet = (outlet) => {
    if (confirm(`Jadikan "${outlet.name}" sebagai Outlet Utama?`)) {
        router.post(route('outlets.set-main', outlet.id));
    }
};

const toggleStatus = (outlet) => {
    const actionText = outlet.status === 'ACTIVE' ? 'nonaktifkan' : 'aktifkan';
    if (confirm(`Apakah Anda yakin ingin meng-${actionText} outlet "${outlet.name}"?`)) {
        router.post(route('outlets.toggle-status', outlet.id));
    }
};

const deleteOutlet = (outlet) => {
    if (confirm(`Apakah Anda yakin ingin menghapus outlet "${outlet.name}"?`)) {
        router.delete(route('outlets.destroy', outlet.id));
    }
};

const restoreOutlet = (outlet) => {
    if (confirm(`Pulihkan outlet "${outlet.name}"?`)) {
        router.post(route('outlets.restore', outlet.id));
    }
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">
                            <i class="bx bx-store text-primary me-2"></i>Manajemen Outlet & Cabang Apotek
                        </h2>
                        <p class="text-muted small mb-0">Kelola jaringan outlet apotek, hak akses user, dan outlet utama perusahaan.</p>
                    </div>
                    <button class="btn btn-primary shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#tambahOutletModal">
                        <i class="bx bx-plus me-1"></i> Tambah Outlet Baru
                    </button>
                </div>

                <!-- Filter & Search Toolbar -->
                <div class="card border-0 shadow-xs mb-4 rounded-3">
                    <div class="card-body p-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-secondary border-end-0"><i class="bx bx-search fs-5"></i></span>
                                    <input type="text" class="form-control border-start-0" v-model="searchFilter" @keyup.enter="filterData" placeholder="Cari kode, nama, kota, telepon...">
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <select class="form-select" v-model="statusFilter" @change="filterData">
                                    <option value="ALL">Semua Status (Aktif & Nonaktif)</option>
                                    <option value="ACTIVE">Aktif (ACTIVE)</option>
                                    <option value="INACTIVE">Nonaktif (INACTIVE)</option>
                                    <option value="TRASHED">Data Terhapus (Trash)</option>
                                </select>
                            </div>
                            <div class="col-md-5 text-end">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill font-monospace">
                                    Total: {{ outlets ? outlets.length : 0 }} Outlet
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table Outlets -->
                <div class="border bg-white border-secondary border-opacity-75 p-3 mb-4 rounded-3 overflow-hidden shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>Kode Outlet</th>
                                    <th>Nama Outlet</th>
                                    <th>Penanggung Jawab</th>
                                    <th>Wilayah</th>
                                    <th>Kontak</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 220px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="out in outlets" :key="out.id" :class="{ 'table-warning': out.is_main, 'table-secondary opacity-75': out.deleted_at }">
                                    <td>
                                        <code class="fw-bold fs-6 text-primary">{{ out.code }}</code>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ out.name }}</div>
                                        <div class="small text-muted" v-if="out.legal_name">{{ out.legal_name }}</div>
                                        <span v-if="out.is_main" class="badge bg-warning text-dark me-1 mt-1">
                                            <i class="bx bx-star me-1"></i>Outlet Utama (Pusat)
                                        </span>
                                        <span v-if="out.id === activeOutletId" class="badge bg-success text-white mt-1">
                                            <i class="bx bx-check-circle me-1"></i>Aktif Digunakan
                                        </span>
                                    </td>
                                    <td>
                                        <div v-if="out.pic_name" class="fw-semibold text-secondary">
                                            <i class="bx bx-user me-1"></i>{{ out.pic_name }}
                                        </div>
                                        <span v-else class="text-muted small">-</span>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <span v-if="out.city" class="fw-semibold text-dark">{{ out.city }}</span>
                                            <span v-if="out.province" class="text-muted">, {{ out.province }}</span>
                                            <div v-if="!out.city && !out.province" class="text-muted">-</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small font-monospace">
                                            <div v-if="out.phone"><i class="bx bx-phone me-1 text-primary"></i>{{ out.phone }}</div>
                                            <div v-if="out.email" class="text-muted"><i class="bx bx-envelope me-1"></i>{{ out.email }}</div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span v-if="out.deleted_at" class="badge bg-danger">Terhapus</span>
                                        <span v-else class="badge" :class="out.status === 'ACTIVE' ? 'bg-success' : 'bg-secondary'">
                                            {{ out.status }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" @click="openDetailModal(out)" title="Detail">
                                                <i class="bx bx-info-circle"></i>
                                            </button>

                                            <template v-if="!out.deleted_at">
                                                <button class="btn btn-outline-primary" @click="openEditModal(out)" title="Edit">
                                                    <i class="bx bx-edit"></i>
                                                </button>
                                                <button v-if="!out.is_main" class="btn btn-outline-warning" @click="setMainOutlet(out)" title="Jadikan Outlet Utama">
                                                    <i class="bx bx-star"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary" @click="toggleStatus(out)" :title="out.status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan'">
                                                    <i class="bx" :class="out.status === 'ACTIVE' ? 'bx-block' : 'bx-check-circle'"></i>
                                                </button>
                                                <button v-if="!out.is_main" class="btn btn-outline-danger" @click="deleteOutlet(out)" title="Soft Delete">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </template>

                                            <template v-else>
                                                <button class="btn btn-outline-success" @click="restoreOutlet(out)" title="Pulihkan">
                                                    <i class="bx bx-undo"></i> Restore
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="!outlets || outlets.length === 0">
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bx bx-store fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                        Belum ada data outlet/cabang yang ditemukan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Modal Tambah Outlet -->
        <div class="modal fade" id="tambahOutletModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bx bx-store me-1"></i> Tambah Outlet Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitCreate">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Kode Outlet <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="createForm.code" required placeholder="OUT-001">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">Nama Outlet <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="createForm.name" required placeholder="Contoh: Apotek Medika - Cabang 1">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Nama Legal / Perusahaan</label>
                                    <input type="text" class="form-control" v-model="createForm.legal_name" placeholder="PT / CV (Opsional)">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nama Penanggung Jawab (APJ)</label>
                                    <input type="text" class="form-control" v-model="createForm.pic_name" placeholder="apt. Nama Penanggung Jawab">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Alamat Lengkap</label>
                                    <textarea class="form-control" v-model="createForm.address" rows="2" placeholder="Jl. Raya No. X..."></textarea>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Provinsi</label>
                                    <input type="text" class="form-control" v-model="createForm.province" placeholder="Jawa Barat">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Kabupaten / Kota</label>
                                    <input type="text" class="form-control" v-model="createForm.city" placeholder="Bandung">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Kecamatan</label>
                                    <input type="text" class="form-control" v-model="createForm.district" placeholder="Coblong">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Kode Pos</label>
                                    <input type="text" class="form-control" v-model="createForm.postal_code" placeholder="40132">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">No. Telepon / HP</label>
                                    <input type="text" class="form-control" v-model="createForm.phone" placeholder="081234567890">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Outlet</label>
                                    <input type="email" class="form-control" v-model="createForm.email" placeholder="cabang1@apotek.com">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Status Operational</label>
                                    <select class="form-select" v-model="createForm.status">
                                        <option value="ACTIVE">ACTIVE (Aktif Transaksi)</option>
                                        <option value="INACTIVE">INACTIVE (Nonaktif / Tutup)</option>
                                    </select>
                                </div>

                                <div class="col-12" v-if="users && users.length > 0">
                                    <label class="form-label fw-bold">Akses User ke Outlet Ini</label>
                                    <div class="border rounded p-3 bg-light" style="max-height: 150px; overflow-y: auto;">
                                        <div class="row g-2">
                                            <div v-for="u in users" :key="u.id" class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" :value="u.id" v-model="createForm.user_ids" :id="'user_c_' + u.id">
                                                    <label class="form-check-label small" :for="'user_c_' + u.id">
                                                        {{ u.name }} <span class="badge bg-secondary text-capitalize" style="font-size:0.65rem;">{{ u.role }}</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="createForm.processing">Simpan Outlet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Outlet -->
        <div class="modal fade" id="editOutletModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title fw-bold"><i class="bx bx-edit me-1"></i> Edit Data Outlet</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitEdit">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Kode Outlet <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="editForm.code" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">Nama Outlet <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="editForm.name" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Nama Legal / Perusahaan</label>
                                    <input type="text" class="form-control" v-model="editForm.legal_name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nama Penanggung Jawab (APJ)</label>
                                    <input type="text" class="form-control" v-model="editForm.pic_name">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Alamat Lengkap</label>
                                    <textarea class="form-control" v-model="editForm.address" rows="2"></textarea>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Provinsi</label>
                                    <input type="text" class="form-control" v-model="editForm.province">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Kabupaten / Kota</label>
                                    <input type="text" class="form-control" v-model="editForm.city">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Kecamatan</label>
                                    <input type="text" class="form-control" v-model="editForm.district">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Kode Pos</label>
                                    <input type="text" class="form-control" v-model="editForm.postal_code">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">No. Telepon / HP</label>
                                    <input type="text" class="form-control" v-model="editForm.phone">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Outlet</label>
                                    <input type="email" class="form-control" v-model="editForm.email">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Status Operational</label>
                                    <select class="form-select" v-model="editForm.status">
                                        <option value="ACTIVE">ACTIVE (Aktif Transaksi)</option>
                                        <option value="INACTIVE">INACTIVE (Nonaktif / Tutup)</option>
                                    </select>
                                </div>

                                <div class="col-12" v-if="users && users.length > 0">
                                    <label class="form-label fw-bold">Akses User ke Outlet Ini</label>
                                    <div class="border rounded p-3 bg-light" style="max-height: 150px; overflow-y: auto;">
                                        <div class="row g-2">
                                            <div v-for="u in users" :key="u.id" class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" :value="u.id" v-model="editForm.user_ids" :id="'user_e_' + u.id">
                                                    <label class="form-check-label small" :for="'user_e_' + u.id">
                                                        {{ u.name }} <span class="badge bg-secondary text-capitalize" style="font-size:0.65rem;">{{ u.role }}</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-warning fw-bold" :disabled="editForm.processing">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Detail Outlet -->
        <div class="modal fade" id="detailOutletModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content" v-if="selectedDetail">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title fw-bold"><i class="bx bx-info-circle me-1"></i> Detail Outlet</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <div class="display-6 text-primary fw-bold">{{ selectedDetail.code }}</div>
                            <h4 class="fw-bold text-dark mb-1">{{ selectedDetail.name }}</h4>
                            <span v-if="selectedDetail.is_main" class="badge bg-warning text-dark">Outlet Utama (Pusat)</span>
                        </div>
                        <ul class="list-group list-group-flush border-top">
                            <li class="list-group-item d-flex justify-content-between">
                                <span class="text-muted">Nama Legal:</span>
                                <span class="fw-bold">{{ selectedDetail.legal_name || '-' }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span class="text-muted">Penanggung Jawab:</span>
                                <span class="fw-bold">{{ selectedDetail.pic_name || '-' }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span class="text-muted">Telepon:</span>
                                <span class="fw-bold">{{ selectedDetail.phone || '-' }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span class="text-muted">Email:</span>
                                <span class="fw-bold">{{ selectedDetail.email || '-' }}</span>
                            </li>
                            <li class="list-group-item">
                                <div class="text-muted small mb-1">Alamat:</div>
                                <div class="fw-semibold">{{ selectedDetail.address || '-' }}</div>
                                <div class="small text-muted">
                                    {{ [selectedDetail.district, selectedDetail.city, selectedDetail.province, selectedDetail.postal_code].filter(Boolean).join(', ') }}
                                </div>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span class="text-muted">Status Operasional:</span>
                                <span class="badge" :class="selectedDetail.status === 'ACTIVE' ? 'bg-success' : 'bg-secondary'">
                                    {{ selectedDetail.status }}
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
