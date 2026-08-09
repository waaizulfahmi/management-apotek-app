<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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
        }
    });
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
                        <p class="text-muted small mb-0">Daftar pelanggan terdaftar, tier membership, poin, dan histori pengeluaran</p>
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
                                <input type="text" class="form-control border-start-0 bg-light" placeholder="Cari nama, kode member, no HP..." v-model="search" @keyup.enter="handleSearch">
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
                                        <th>Poin Available</th>
                                        <th>Total Belanja</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in customers.data" :key="c.id">
                                        <td class="ps-4 fw-bold text-primary">{{ c.code }}</td>
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
                                        <td class="fw-bold text-success">{{ c.points.toLocaleString() }} Pts</td>
                                        <td class="fw-bold text-dark">{{ formatRupiah(c.total_spending) }}</td>
                                        <td>
                                            <span class="badge" :class="c.status === 'ACTIVE' ? 'bg-success' : 'bg-secondary'">
                                                {{ c.status || 'ACTIVE' }}
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <Link :href="route('membership.members.show', c.id)" class="btn btn-sm btn-outline-primary rounded-pill">
                                                <i class="bx bx-show me-1"></i>Detail Profile
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="!customers.data || customers.data.length === 0">
                                        <td colspan="8" class="text-center py-5 text-muted">Belum ada data member ditemukan.</td>
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
    </LegacyLayout>
</template>
