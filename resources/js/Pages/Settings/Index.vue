<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    auditLogs: Object,
    settings: Object,
});

const logoPreview = ref(props.settings?.pharmacy_logo || null);

const settingForm = useForm({
    pharmacy_name: props.settings?.pharmacy_name || 'Apotek Medika Sore',
    pharmacy_address: props.settings?.pharmacy_address || 'Jl. Raya Farmasi No. 10, Jakarta',
    pharmacy_phone: props.settings?.pharmacy_phone || '021-5551234',
    pharmacist_name: props.settings?.pharmacist_name || 'apt. Budi Santoso, S.Farm',
    pharmacist_license: props.settings?.pharmacist_license || 'SIPA/503/001/2026',
    tax_percent: props.settings?.tax_percent || '0',
    expired_warning_days: props.settings?.expired_warning_days || '60',
    pharmacy_logo: null,
});

const handleLogoChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 2 * 1024 * 1024) {
            Swal.fire({
                title: 'Ukuran Berkas Terlalu Besar!',
                text: 'Ukuran foto/logo maksimal 2 MB.',
                icon: 'warning',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }
        settingForm.pharmacy_logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const submitSettings = () => {
    settingForm.post(route('settings.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            Swal.fire({
                title: 'Berhasil Disimpan!',
                text: 'Pengaturan identitas apotek dan logo berhasil diperbarui secara menyeluruh!',
                icon: 'success',
                confirmButtonColor: '#10b981'
            });
        }
    });
};

const resetLogo = () => {
    Swal.fire({
        title: 'Reset Logo Apotek?',
        text: 'Logo akan dikembalikan ke logo bawaan sistem.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Reset Logo',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b'
    }).then((res) => {
        if (res.isConfirmed) {
            router.post(route('settings.reset-logo'), {}, {
                onSuccess: () => {
                    settingForm.pharmacy_logo = null;
                    logoPreview.value = null;
                }
            });
        }
    });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><i class="bx bx-cog text-primary me-2"></i>Pengaturan Sistem & Branding Apotek</h2>
                        <p class="text-muted small mb-0">Kelola logo apotek, profil usaha, surat izin apoteker, serta rekam jejak Audit Log aktivitas pengguna</p>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Left Column: Logo Upload & Pharmacy Profile Form -->
                    <div class="col-lg-5">
                        <form @submit.prevent="submitSettings" enctype="multipart/form-data">
                            
                            <!-- Logo Upload Card -->
                            <div class="card border-0 shadow-sm p-4 mb-4 rounded-3 bg-white">
                                <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                    <i class="bx bx-image text-primary me-2"></i>Logo Resmi Apotek
                                </h5>

                                <div class="text-center mb-3">
                                    <div class="position-relative d-inline-block">
                                        <div class="logo-preview-box rounded-3 border p-3 bg-light d-flex align-items-center justify-content-center shadow-xs" style="width: 140px; height: 140px; margin: 0 auto;">
                                            <img v-if="logoPreview" :src="logoPreview" alt="Logo Apotek" class="img-fluid" style="max-height: 110px; max-width: 110px; object-fit: contain;">
                                            <div v-else class="text-muted text-center">
                                                <i class="bx bx-plus-medical text-primary display-4 d-block mb-1"></i>
                                                <small class="d-block text-muted" style="font-size: 0.7rem;">Logo Default</small>
                                            </div>
                                        </div>
                                        <span v-if="logoPreview" class="badge bg-success position-absolute top-0 start-100 translate-middle shadow-xs">
                                            <i class="bx bx-check"></i> Logo Kustom
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark mb-1">Unggah Logo Baru</label>
                                    <input type="file" class="form-control" @change="handleLogoChange" accept="image/png, image/jpeg, image/jpg, image/svg+xml, image/webp">
                                    <small class="text-muted d-block mt-1">Format: PNG, JPG, WEBP, atau SVG. Ukuran Maksimal: <strong>2 MB</strong>.</small>
                                </div>

                                <button v-if="props.settings?.pharmacy_logo" type="button" @click="resetLogo" class="btn btn-outline-danger btn-sm w-100 fw-semibold">
                                    <i class="bx bx-trash me-1"></i> Reset ke Logo Default
                                </button>
                            </div>

                            <!-- Pharmacy Identity Card -->
                            <div class="card border-0 shadow-sm p-4 rounded-3 bg-white">
                                <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                    <i class="bx bx-store-alt text-primary me-2"></i>Profil Apotek & Cetak Struk
                                </h5>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">Nama Apotek</label>
                                    <input type="text" class="form-control" v-model="settingForm.pharmacy_name" required placeholder="Contoh: Apotek Medika Sora">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">Alamat Apotek</label>
                                    <textarea class="form-control" rows="2" v-model="settingForm.pharmacy_address" required placeholder="Alamat lengkap apotek..."></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">No. Telepon / WhatsApp Apotek</label>
                                    <input type="text" class="form-control" v-model="settingForm.pharmacy_phone" required placeholder="021-5551234">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">Nama Apoteker Penanggung Jawab</label>
                                    <input type="text" class="form-control" v-model="settingForm.pharmacist_name" required placeholder="apt. Nama Apoteker, S.Farm">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">No. SIPA / Surat Izin Apoteker</label>
                                    <input type="text" class="form-control" v-model="settingForm.pharmacist_license" required placeholder="SIPA/503/xxx/2026">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-dark">Batas Warning Expired Obat (Hari)</label>
                                    <input type="number" class="form-control" v-model.number="settingForm.expired_warning_days" required min="1">
                                    <small class="text-muted">Obat yang kadaluarsa dalam rentang hari ini akan memicu peringatan FEFO.</small>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold shadow-sm" :disabled="settingForm.processing">
                                    <i class="bx bx-save me-1"></i> Simpan Pengaturan & Logo
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Right Column: Audit Logs Trail -->
                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm p-4 rounded-3 bg-white h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                <h5 class="fw-bold text-dark mb-0">
                                    <i class="bx bx-history me-2 text-info"></i>Audit Log Aktivitas Pengguna
                                </h5>
                                <span class="badge bg-light text-dark border">Rekam Jejak Keamanan</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle small">
                                    <thead class="bg-primary text-white">
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Pengguna</th>
                                            <th>Aksi</th>
                                            <th>Modul</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="log in auditLogs.data" :key="log.id">
                                            <td class="text-muted">{{ log.created_at }}</td>
                                            <td><span class="fw-bold text-dark">{{ log.user_name || 'System' }}</span></td>
                                            <td><span class="badge bg-info text-dark font-monospace">{{ log.action }}</span></td>
                                            <td class="fw-semibold text-secondary">{{ log.module }}</td>
                                            <td><code class="text-dark bg-light px-2 py-1 rounded">{{ log.ip_address || '127.0.0.1' }}</code></td>
                                        </tr>
                                        <tr v-if="!auditLogs.data || auditLogs.data.length === 0">
                                            <td colspan="5" class="text-center py-4 text-muted">Belum ada audit log yang tercatat.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LegacyLayout>
</template>

<style scoped>
.logo-preview-box {
    transition: all 0.3s ease;
}
.logo-preview-box:hover {
    border-color: #3b82f6 !important;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2) !important;
}
</style>
