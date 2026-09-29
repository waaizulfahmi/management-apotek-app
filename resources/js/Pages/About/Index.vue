<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import LegacyLayout from '@/Layouts/LegacyLayout.vue';

const props = defineProps({
    developerInfo: Object,
    changelogs: Array,
    systemEnv: Object,
});

const activeTab = ref('changelog'); // 'changelog' or 'sysinfo'
</script>

<template>
    <Head title="Tentang Aplikasi & Log Update Sistem" />

    <LegacyLayout>
        <section class="mt-4 me-4 ms-4">
            <div class="content mt-4" style="margin-left: 260px; padding: 20px;">
                
                <!-- ===== HERO HEADER CARD ===== -->
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4 text-white position-relative"
                     style="background: linear-gradient(135deg, #0f172a 0%, #1e3c72 50%, #2a5298 100%);">
                    
                    <div class="card-body p-4 p-md-5 position-relative z-1">
                        <div class="row align-items-center g-4">
                            <div class="col-lg-8">
                                <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                                    <span class="badge bg-warning text-dark fw-bold px-3 py-1.5 rounded-pill shadow-xs style-xs">
                                        <i class="bx bx-purchase-tag me-1"></i>{{ developerInfo.version }} — {{ developerInfo.release_date }}
                                    </span>
                                    <span class="badge bg-white bg-opacity-20 text-white border border-white border-opacity-25 px-3 py-1.5 rounded-pill style-xs">
                                        <i class="bx bx-check-shield me-1 text-success"></i>System Healthy & Active
                                    </span>
                                    <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-30 px-3 py-1.5 rounded-pill style-xs">
                                        Enterprise Edition
                                    </span>
                                </div>

                                <h1 class="fw-extrabold text-white display-6 mb-2 font-outfit">
                                    {{ developerInfo.app_title || 'Apotek Medika System' }}
                                </h1>
                                <p class="text-white-50 fs-6 mb-4 max-w-2xl leading-relaxed">
                                    Sistem Manajemen Apotek Terintegrasi Multi-Outlet, Point of Sale (POS) Real-Time, Pengelolaan Resep Dokter, Keuangan Laba/Rugi, dan Stok Opname Berbasis Valuasi HPP.
                                </p>

                                <div class="d-flex gap-3 align-items-center flex-wrap">
                                    <button @click="activeTab = 'changelog'"
                                            class="btn px-4 py-2 rounded-pill fw-bold shadow-sm transition-all"
                                            :class="activeTab === 'changelog' ? 'btn-warning text-dark' : 'btn-outline-light'">
                                        <i class="bx bx-history me-1"></i> Log Update Sistem
                                    </button>
                                    <button @click="activeTab = 'sysinfo'"
                                            class="btn px-4 py-2 rounded-pill fw-bold shadow-sm transition-all"
                                            :class="activeTab === 'sysinfo' ? 'btn-warning text-dark' : 'btn-outline-light'">
                                        <i class="bx bx-cog me-1"></i> Informasi Server & Spesifikasi
                                    </button>
                                </div>
                            </div>

                            <div class="col-lg-4 text-center d-none d-lg-block">
                                <div class="rounded-circle bg-white bg-opacity-10 backdrop-blur p-4 d-inline-flex align-items-center justify-content-center shadow-lg border border-white border-opacity-20"
                                     style="width: 140px; height: 140px;">
                                    <i class="bx bx-plus-medical text-warning display-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- ===== LEFT COLUMN: DEVELOPER & TECH STACK ===== -->
                    <div class="col-lg-4">
                        <!-- Developer Card -->
                        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
                            <div class="text-center mb-3">
                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center mb-3 p-3"
                                     style="width: 76px; height: 76px;">
                                    <i class="bx bx-code-alt fs-1"></i>
                                </div>
                                <h5 class="fw-bold text-dark mb-0 font-outfit">{{ developerInfo.developer }}</h5>
                                <small class="text-muted d-block mt-1">{{ developerInfo.role }}</small>
                                <span class="badge bg-light text-primary border border-primary border-opacity-20 px-3 py-1 rounded-pill mt-2 style-xs">
                                    {{ developerInfo.organization }}
                                </span>
                            </div>

                            <hr class="my-3 opacity-25">

                            <div class="vstack gap-2.5">
                                <div class="d-flex align-items-center justify-content-between text-muted small">
                                    <span><i class="bx bx-envelope me-2 text-primary"></i>Kontak Support</span>
                                    <strong class="text-dark">{{ developerInfo.contact_email }}</strong>
                                </div>
                                <div class="d-flex align-items-center justify-content-between text-muted small">
                                    <span><i class="bx bx-shield-quarter me-2 text-success"></i>Lisensi</span>
                                    <strong class="text-dark">{{ developerInfo.license }}</strong>
                                </div>
                                <div class="d-flex align-items-center justify-content-between text-muted small">
                                    <span><i class="bx bx-calendar me-2 text-warning"></i>Tgl Rilis Utama</span>
                                    <strong class="text-dark">{{ developerInfo.release_date }}</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Tech Stack Card -->
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                            <h6 class="fw-bold text-dark mb-3 font-outfit d-flex align-items-center gap-2">
                                <i class="bx bx-layer text-primary"></i> Teknologi & Arsitektur
                            </h6>
                            <div class="vstack gap-3">
                                <div v-for="(tech, idx) in developerInfo.tech_stack" :key="idx"
                                     class="d-flex align-items-center justify-content-between p-2.5 rounded-3 bg-light">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <i class="bx fs-4" :class="tech.icon"></i>
                                        <div>
                                            <div class="fw-bold small text-dark">{{ tech.name }}</div>
                                        </div>
                                    </div>
                                    <span class="badge bg-white text-secondary border shadow-xs fw-semibold style-xs">
                                        {{ tech.version }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== RIGHT COLUMN: MAIN CONTENT (CHANGELOG / SYSINFO) ===== -->
                    <div class="col-lg-8">
                        
                        <!-- TAB 1: CHANGELOG TIMELINE -->
                        <div v-if="activeTab === 'changelog'" class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                            <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                                <div>
                                    <h5 class="fw-bold text-dark mb-1 font-outfit">
                                        <i class="bx bx-history text-primary me-2"></i>Log Update & Catatan Rilis (Changelog)
                                    </h5>
                                    <p class="text-muted small mb-0">Riwayat pembaruan sistem, penambahan fitur baru, dan perbaikan bug.</p>
                                </div>
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold style-xs">
                                    Total {{ changelogs.length }} Versi
                                </span>
                            </div>

                            <!-- Timeline list -->
                            <div class="timeline-wrapper ps-2">
                                <div v-for="(log, idx) in changelogs" :key="idx" class="timeline-item mb-4 pb-3 border-start border-2 border-primary position-relative ps-4 ms-2">
                                    <!-- Timeline Dot -->
                                    <div class="position-absolute top-0 start-0 translate-middle-x rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-xs"
                                         style="width: 24px; height: 24px; left: -1px; top: 10px !important;">
                                        <i class="bx bx-git-commit style-xs"></i>
                                    </div>

                                    <div class="bg-light p-3.5 rounded-4 border border-opacity-50">
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <h5 class="fw-bold text-primary mb-0 font-outfit">{{ log.version }}</h5>
                                                <span class="badge px-2.5 py-1 rounded-pill style-xs" :class="log.badge_color">
                                                    {{ log.badge }}
                                                </span>
                                            </div>
                                            <span class="text-muted small fw-semibold">
                                                <i class="bx bx-time me-1"></i>{{ log.date }}
                                            </span>
                                        </div>

                                        <p class="fw-bold text-dark small mb-2.5">{{ log.summary }}</p>

                                        <ul class="list-unstyled mb-0 vstack gap-1.5">
                                            <li v-for="(item, iIdx) in log.highlights" :key="iIdx" class="d-flex align-items-start gap-2 small text-secondary">
                                                <i class="bx bx-check-circle text-success fs-6 flex-shrink-0 mt-0.5"></i>
                                                <span>{{ item }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: SYSTEM ENVIRONMENT & DIAGNOSTICS -->
                        <div v-else-if="activeTab === 'sysinfo'" class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                            <div class="mb-4 border-bottom pb-3">
                                <h5 class="fw-bold text-dark mb-1 font-outfit">
                                    <i class="bx bx-server text-primary me-2"></i>Informasi Lingkungan Server & Database
                                </h5>
                                <p class="text-muted small mb-0">Spesifikasi runtime PHP, kerangka kerja Laravel, dan konfigurasi server.</p>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <small class="text-muted text-uppercase fw-bold style-xs d-block mb-1">Versi PHP</small>
                                        <div class="fw-bold text-dark fs-5 font-monospace">{{ systemEnv.php_version }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <small class="text-muted text-uppercase fw-bold style-xs d-block mb-1">Versi Laravel Framework</small>
                                        <div class="fw-bold text-primary fs-5 font-monospace">{{ systemEnv.laravel_version }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <small class="text-muted text-uppercase fw-bold style-xs d-block mb-1">Environment State</small>
                                        <div class="fw-bold text-dark fs-5 text-capitalize">{{ systemEnv.environment }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <small class="text-muted text-uppercase fw-bold style-xs d-block mb-1">Mode Debug</small>
                                        <div class="fw-bold text-warning fs-5">{{ systemEnv.debug_mode }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <small class="text-muted text-uppercase fw-bold style-xs d-block mb-1">Koneksi Database Utama</small>
                                        <div class="fw-bold text-dark fs-5 text-uppercase">{{ systemEnv.database }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <small class="text-muted text-uppercase fw-bold style-xs d-block mb-1">Timezone & Waktu Server</small>
                                        <div class="fw-bold text-dark small font-monospace">{{ systemEnv.server_time }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </section>
    </LegacyLayout>
</template>
