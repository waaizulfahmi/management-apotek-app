<script setup>
import LegacyLayout from '@/Layouts/LegacyLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    auditLogs: Object,
    settings: Object,
});

const settingForm = useForm({
    pharmacy_name: props.settings?.pharmacy_name || 'Apotek Medika Sore',
    pharmacy_address: props.settings?.pharmacy_address || 'Jl. Raya Farmasi No. 10, Jakarta',
    pharmacy_phone: props.settings?.pharmacy_phone || '021-5551234',
    pharmacist_name: props.settings?.pharmacist_name || 'apt. Budi Santoso, S.Farm',
    pharmacist_license: props.settings?.pharmacist_license || 'SIPA/503/001/2026',
    tax_percent: props.settings?.tax_percent || '0',
    expired_warning_days: props.settings?.expired_warning_days || '60',
});

const submitSettings = () => {
    settingForm.post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => alert('Pengaturan Apotek berhasil diperbarui!')
    });
};
</script>

<template>
    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                <h2 class="fw-bold text-dark mb-4"><i class="bx bx-cog text-primary me-2"></i>Pengaturan Sistem & Audit Log Activity</h2>

                <div class="row">
                    <!-- Pharmacy Profile Settings -->
                    <div class="col-md-5 mb-4">
                        <div class="card border-0 shadow-sm p-3 rounded-3 bg-white">
                            <h5 class="fw-bold mb-3 border-bottom pb-2">Profil Apotek & Struk</h5>
                            <form @submit.prevent="submitSettings">
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Nama Apotek</label>
                                    <input type="text" class="form-control" v-model="settingForm.pharmacy_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Alamat Apotek</label>
                                    <textarea class="form-control" v-model="settingForm.pharmacy_address" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">No. Telepon Apotek</label>
                                    <input type="text" class="form-control" v-model="settingForm.pharmacy_phone" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Nama Apoteker Penanggung Jawab</label>
                                    <input type="text" class="form-control" v-model="settingForm.pharmacist_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">No. SIPA / Surat Izin Apoteker</label>
                                    <input type="text" class="form-control" v-model="settingForm.pharmacist_license" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Hari Warning Expired Obat (Default)</label>
                                    <input type="number" class="form-control" v-model="settingForm.expired_warning_days" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100" :disabled="settingForm.processing">Simpan Pengaturan</button>
                            </form>
                        </div>
                    </div>

                    <!-- Audit Logs Trail -->
                    <div class="col-md-7 mb-4">
                        <div class="card border-0 shadow-sm p-3 rounded-3 bg-white">
                            <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bx bx-history me-2 text-info"></i>Audit Log Aktivitas Pengguna</h5>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm align-middle small">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Waktu</th>
                                            <th>User</th>
                                            <th>Aksi</th>
                                            <th>Modul</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="log in auditLogs.data" :key="log.id">
                                            <td>{{ log.created_at }}</td>
                                            <td><span class="fw-bold">{{ log.user_name || 'System' }}</span></td>
                                            <td><span class="badge bg-primary">{{ log.action }}</span></td>
                                            <td>{{ log.module }}</td>
                                            <td><code>{{ log.ip_address || '127.0.0.1' }}</code></td>
                                        </tr>
                                        <tr v-if="!auditLogs.data || auditLogs.data.length === 0">
                                            <td colspan="5" class="text-center py-3 text-muted">Belum ada audit log recorded.</td>
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
