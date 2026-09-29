<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { showSuccess, showConfirm } from '@/Utils/swal';

const props = defineProps({
    customers: Object,
    tiers: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const selectedTier = ref(props.filters.tier_id || '');

const handleSearch = () => {
    router.get(route('membership.members.index'), { search: search.value, tier_id: selectedTier.value }, { preserveState: true });
};

// Create Form
const form = useForm({
    name: '',
    phone: '',
    email: '',
    date_of_birth: '',
    gender: 'L',
    address: '',
    allergies: '',
});

const submitMember = () => {
    form.post(route('membership.members.store'), {
        onSuccess: () => {
            form.reset();
            const modalEl = document.getElementById('tambahMemberModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
            showSuccess('Registrasi Berhasil', 'Member baru dan data alergi obat telah disimpan.');
        }
    });
};

// Edit Form & Modal State
const isEditing = ref(false);
const editingMemberId = ref(null);
const editForm = useForm({
    name: '',
    phone: '',
    email: '',
    date_of_birth: '',
    gender: 'L',
    address: '',
    allergies: '',
    status: 'ACTIVE',
});

const editMember = (c) => {
    editingMemberId.value = c.id;
    editForm.name = c.name || '';
    editForm.phone = c.phone || '';
    editForm.email = c.email || '';
    editForm.date_of_birth = c.date_of_birth || '';
    editForm.gender = c.gender || 'L';
    editForm.address = c.address || '';
    editForm.allergies = c.allergies || '';
    editForm.status = c.status || 'ACTIVE';

    isEditing.value = true;
    const modalEl = document.getElementById('editMemberModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
};

const submitEditMember = () => {
    editForm.put(route('membership.members.update', editingMemberId.value), {
        onSuccess: () => {
            const modalEl = document.getElementById('editMemberModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
            showSuccess('Data Berhasil Diperbarui', 'Data member dan riwayat alergi obat berhasil diperbarui!');
        }
    });
};

const confirmDelete = async (c) => {
    const isConfirmed = await showConfirm(
        'Hapus Member?',
        `Apakah Anda yakin ingin menghapus member ${c.name} (${c.code})?`
    );

    if (isConfirmed) {
        router.delete(route('membership.members.destroy', c.id), {
            onSuccess: () => showSuccess('Terhapus', `Member ${c.name} telah dihapus.`)
        });
    }
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-user-check text-primary me-2"></i>Data Member & Loyalty Program</h2>
                        <p class="text-muted small mb-0">Daftar pelanggan terdaftar, tier membership, riwayat alergi obat, poin, dan histori pengeluaran</p>
                    </div>
                    <button class="btn btn-primary shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#tambahMemberModal">
                        <i class="bx bx-plus me-1"></i>+ Tambah Member Baru
                    </button>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bx bx-search"></i></span>
                                <input type="text" class="form-control border-start-0 bg-light" placeholder="Cari nama, kode member, no HP, alergi..." v-model="search" @keyup.enter="handleSearch">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select bg-light" v-model="selectedTier" @change="handleSearch">
                                <option value="">-- Semua Tier Membership --</option>
                                <option v-for="t in tiers" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-secondary w-100" @click="handleSearch">Filter</button>
                        </div>
                    </div>
                </div>

                <!-- Members Table -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Member ID</th>
                                        <th>Nama Pelanggan</th>
                                        <th>No HP</th>
                                        <th>Tier</th>
                                        <th>Alergi Obat</th>
                                        <th>Poin Available</th>
                                        <th>Total Belanja</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in customers.data" :key="c.id">
                                        <td class="ps-4 fw-bold text-primary font-monospace">{{ c.code }}</td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ c.name }}</div>
                                            <small class="text-muted">{{ c.email || 'No Email' }}</small>
                                        </td>
                                        <td>{{ c.phone }}</td>
                                        <td>
                                            <span class="badge text-uppercase px-2 py-1" :style="{ backgroundColor: c.tier?.badge_color || '#6b7280' }">
                                                {{ c.tier?.name || c.membership_level }}
                                            </span>
                                        </td>
                                        <td>
                                            <span v-if="c.allergies" class="badge bg-danger text-white px-2 py-1 shadow-xs" title="Sensitif Alergi Obat">
                                                <i class="bx bx-shield-x me-1"></i>{{ c.allergies }}
                                            </span>
                                            <span v-else class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                                <i class="bx bx-check-circle me-1"></i>Tidak Ada
                                            </span>
                                        </td>
                                        <td class="fw-bold text-success">{{ c.points.toLocaleString() }} Pts</td>
                                        <td class="fw-bold text-dark">{{ formatRupiah(c.total_spending) }}</td>
                                        <td>
                                            <span class="badge" :class="c.status === 'ACTIVE' ? 'bg-success' : 'bg-secondary'">
                                                {{ c.status || 'ACTIVE' }}
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-flex justify-content-end gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill" @click="editMember(c)" title="Edit Member & Alergi Obat">
                                                    <i class="bx bx-edit me-1"></i>Edit
                                                </button>
                                                <Link :href="route('membership.members.show', c.id)" class="btn btn-sm btn-outline-primary rounded-pill">
                                                    <i class="bx bx-show me-1"></i>Detail
                                                </Link>
                                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" @click="confirmDelete(c)" title="Hapus Member">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!customers.data || customers.data.length === 0">
                                        <td colspan="9" class="text-center py-5 text-muted">Belum ada data member ditemukan.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 py-3">
                        <Pagination :links="customers.links" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Modal Tambah Member -->
        <div class="modal fade" id="tambahMemberModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark"><i class="bx bx-user-plus text-primary me-2"></i>Registrasi Member Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitMember">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Nama Lengkap Member <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="form.name" required placeholder="Contoh: Budi Santoso">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Nomor HP (WhatsApp) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="form.phone" required placeholder="08123456789">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark">Email</label>
                                    <input type="email" class="form-control" v-model="form.email" placeholder="budi@gmail.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark">Tanggal Lahir</label>
                                    <input type="date" class="form-control" v-model="form.date_of_birth">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Jenis Kelamin</label>
                                <select class="form-select" v-model="form.gender">
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark text-danger"><i class="bx bx-error-circle me-1"></i>Riwayat / Catatan Alergi Obat (Jika Ada)</label>
                                <input type="text" class="form-control border-danger border-opacity-50" v-model="form.allergies" placeholder="Contoh: Paracetamol, Amoxicillin, Penisilin (Kosongkan jika tidak ada)">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Alamat Tempat Tinggal</label>
                                <textarea class="form-control" rows="2" v-model="form.address" placeholder="Alamat lengkap..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="form.processing">Simpan & Daftarkan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Member & Alergi Obat -->
        <div class="modal fade" id="editMemberModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark"><i class="bx bx-edit text-warning me-2"></i>Edit Data & Alergi Obat Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="submitEditMember">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Nama Lengkap Member <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="editForm.name" required placeholder="Nama lengkap...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Nomor HP (WhatsApp) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="editForm.phone" required placeholder="08123456789">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark">Email</label>
                                    <input type="email" class="form-control" v-model="editForm.email" placeholder="email@gmail.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark">Tanggal Lahir</label>
                                    <input type="date" class="form-control" v-model="editForm.date_of_birth">
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark">Jenis Kelamin</label>
                                    <select class="form-select" v-model="editForm.gender">
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark">Status Membership</label>
                                    <select class="form-select" v-model="editForm.status">
                                        <option value="ACTIVE">ACTIVE</option>
                                        <option value="INACTIVE">INACTIVE</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-danger"><i class="bx bx-shield-x me-1"></i>Riwayat / Catatan Alergi Obat Member</label>
                                <input type="text" class="form-control border-danger border-opacity-50" v-model="editForm.allergies" placeholder="Contoh: Paracetamol, Amoxicillin, Penisilin (Kosongkan jika tidak ada)">
                                <div class="form-text small text-muted">Alergi obat ini akan muncul otomatis sebagai peringatan keselamatan saat kasir memilih member ini di POS.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Alamat Tempat Tinggal</label>
                                <textarea class="form-control" rows="2" v-model="editForm.address" placeholder="Alamat lengkap..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" :disabled="editForm.processing">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </LegacyLayout>
</template>
