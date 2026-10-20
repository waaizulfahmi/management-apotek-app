<script setup>
import { computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: Number,
        default: 403,
    },
    message: {
        type: String,
        default: '',
    },
});

const page = usePage();

const pharmacyName = computed(() => {
    return page.props.app_settings?.pharmacy_name || 'Apotek Saya';
});

const pharmacyLogo = computed(() => {
    return page.props.app_settings?.pharmacy_logo || null;
});

const pharmacyPhone = computed(() => {
    return page.props.app_settings?.pharmacy_phone || null;
});

const user = computed(() => {
    return page.props.auth?.user || null;
});

const userRole = computed(() => {
    return user.value?.role || null;
});

const dashboardRoute = computed(() => {
    if (!user.value) return '/';
    if (userRole.value === 'admin' || userRole.value === 'superadmin') {
        return route('admin.dashboard');
    } else if (userRole.value === 'kasir') {
        return route('pos.index');
    } else if (userRole.value === 'owner') {
        return route('owner.dashboard');
    }
    return route('dashboard');
});

const is403 = computed(() => props.status === 403);
const is404 = computed(() => props.status === 404);

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit(dashboardRoute.value);
    }
};

const reloadPage = () => {
    window.location.reload();
};
</script>

<template>
    <Head :title="`${status} - ${is403 ? 'Akses Dibatasi' : (is404 ? 'Halaman Tidak Ditemukan' : 'Terjadi Kesalahan')} | ${pharmacyName}`" />

    <div class="fullscreen-error-wrapper">
        <!-- Ambient Background Glowing Orbs -->
        <div class="ambient-glow glow-1" :class="is403 ? 'glow-amber' : 'glow-blue'"></div>
        <div class="ambient-glow glow-2" :class="is403 ? 'glow-red' : 'glow-cyan'"></div>
        <div class="ambient-glow glow-3" :class="is403 ? 'glow-indigo' : 'glow-purple'"></div>
        <div class="bg-grid"></div>

        <!-- Main Glassmorphic Card -->
        <div class="error-card">
            <!-- Pharmacy Brand Header -->
            <div class="brand-badge">
                <div class="brand-logo-frame" :class="is403 ? 'bg-amber-frame' : 'bg-blue-frame'">
                    <img v-if="pharmacyLogo" :src="pharmacyLogo" :alt="pharmacyName">
                    <i v-else-if="is403" class="bx bx-shield-quarter"></i>
                    <i v-else class="bx bx-plus-medical"></i>
                </div>
                <span class="brand-name">{{ pharmacyName }}</span>
            </div>

            <!-- Big Status Code -->
            <div class="hero-number-container">
                <div class="hero-number" :class="is403 ? 'gradient-403' : 'gradient-404'">
                    {{ status }}
                </div>
            </div>

            <!-- Illustrated Centerpiece -->
            <div class="hero-visual">
                <div class="orbital-ring" :class="is403 ? 'ring-amber' : 'ring-cyan'"></div>
                <div class="orbital-ring-inner" :class="is403 ? 'ring-red' : 'ring-purple'"></div>
                <div class="icon-core" :class="is403 ? 'core-amber' : 'core-blue'">
                    <i v-if="is403" class="bx bxs-lock-alt"></i>
                    <i v-else-if="is404" class="bx bx-compass"></i>
                    <i v-else class="bx bx-error"></i>
                </div>
            </div>

            <!-- Status Pill -->
            <div>
                <span class="status-pill" :class="is403 ? 'status-pill-amber' : 'status-pill-red'">
                    <span class="pulse-dot" :class="is403 ? 'dot-amber' : 'dot-red'"></span>
                    <span v-if="is403">Akses Terbatas &bull; Unauthorized</span>
                    <span v-else-if="is404">Halaman Tidak Ditemukan &bull; 404</span>
                    <span v-else>Terjadi Gangguan Server</span>
                </span>
            </div>

            <!-- Headings -->
            <h1 class="error-title" v-if="is403">Akses Ditolak: Hak Akses Dibatasi</h1>
            <h1 class="error-title" v-else-if="is404">Oops! Halaman Tidak Ada di Rak Apotek</h1>
            <h1 class="error-title" v-else>Terjadi Gangguan Sistem</h1>

            <p class="error-desc" v-if="is403">
                Maaf, akun Anda tidak memiliki izin atau wewenang yang diperlukan untuk mengakses halaman, fitur, atau tindakan ini.
            </p>
            <p class="error-desc" v-else-if="is404">
                Halaman atau tautan yang Anda tuju mungkin telah dipindahkan, diubah jalurnya, atau alamat URL yang Anda masukkan salah.
            </p>
            <p class="error-desc" v-else>
                Server sedang mengalami kendala teknis sementara. Mohon coba beberapa saat lagi.
            </p>

            <!-- Custom Reason Alert if provided -->
            <div v-if="message && message !== 'Unauthorized access.' && message !== 'This action is unauthorized.'" class="custom-reason-alert">
                <i class="bx bx-error-alt"></i>
                <span>{{ message }}</span>
            </div>

            <!-- User Auth Diagnostic Card (For 403) -->
            <div v-if="is403" class="user-auth-card">
                <div class="user-auth-info">
                    <div class="user-avatar-circle">
                        <i class="bx bx-user"></i>
                    </div>
                    <div class="user-auth-text">
                        <template v-if="user">
                            <strong>{{ user.name }}</strong>
                            <span>{{ user.email }}</span>
                        </template>
                        <template v-else>
                            <strong>Tamu (Belum Login)</strong>
                            <span>Silakan login terlebih dahulu</span>
                        </template>
                    </div>
                </div>
                <div>
                    <span v-if="user" class="role-pill">Peran: {{ (userRole || 'Pengguna').toUpperCase() }}</span>
                    <Link v-else :href="route('login')" class="role-pill text-decoration-none">Login Sekarang</Link>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-group">
                <Link :href="dashboardRoute" class="btn-action btn-primary-action" :class="is403 ? 'btn-amber' : 'btn-blue'">
                    <i class="bx bx-home-alt fs-5"></i> Kembali ke Menu Utama
                </Link>
                <button type="button" @click="goBack" class="btn-action btn-secondary-action">
                    <i class="bx bx-arrow-back fs-5"></i> Halaman Sebelumnya
                </button>
                <button v-if="!is403" type="button" @click="reloadPage" class="btn-action btn-secondary-action">
                    <i class="bx bx-refresh fs-5"></i> Muat Ulang
                </button>
                <Link v-if="is403 && user" :href="route('logout')" method="post" as="button" class="btn-action btn-secondary-action">
                    <i class="bx bx-log-out fs-5"></i> Ganti Akun
                </Link>
            </div>

            <!-- Quick Navigation Shortcuts (For 404) -->
            <div v-if="is404" class="quick-nav-section">
                <div class="quick-nav-header">
                    <i class="bx bx-compass"></i> Akses Menu Cepat
                </div>
                <div class="quick-grid">
                    <Link :href="route('pos.index')" class="quick-card">
                        <div class="quick-card-icon">
                            <i class="bx bx-shopping-bag"></i>
                        </div>
                        <div class="quick-card-info">
                            <h6>Kasir / POS</h6>
                            <p>Buka transaksi kasir</p>
                        </div>
                    </Link>

                    <Link :href="route('sales.cashier.index')" class="quick-card">
                        <div class="quick-card-icon">
                            <i class="bx bx-user-check"></i>
                        </div>
                        <div class="quick-card-info">
                            <h6>Laporan Kasir</h6>
                            <p>Penjualan shift & kasir</p>
                        </div>
                    </Link>

                    <Link :href="route('sales.history.index')" class="quick-card">
                        <div class="quick-card-icon">
                            <i class="bx bx-history"></i>
                        </div>
                        <div class="quick-card-info">
                            <h6>Riwayat Faktur</h6>
                            <p>Daftar transaksi nota</p>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Security Hint & Guidance (For 403) -->
            <div v-if="is403" class="security-hint-box">
                <i class="bx bx-shield-quarter"></i>
                <div class="security-hint-content">
                    <h6>Perlu Hak Akses ke Halaman Ini?</h6>
                    <p>
                        Jika Anda merasa ini adalah kekeliruan atau Anda membutuhkan akses untuk tugas operasional tertentu, silakan hubungi <strong>Super Administrator</strong> atau penanggung jawab outlet untuk memperbarui hak akses peran Anda.
                    </p>
                </div>
            </div>

            <!-- Footer Info -->
            <div class="footer-info">
                <span>&copy; {{ new Date().getFullYear() }} {{ pharmacyName }}. Keamanan Sistem Terjamin.</span>
                <span v-if="pharmacyPhone">Bantuan: <a :href="`tel:${pharmacyPhone}`">{{ pharmacyPhone }}</a></span>
                <span v-else>Sistem Manajemen Farmasi & Apotek</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fullscreen-error-wrapper {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    min-height: 100vh;
    background-color: #080d1a;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 16px;
    z-index: 999999;
    overflow-y: auto;
    font-family: 'Outfit', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* Ambient Glowing Background Orbs */
.ambient-glow {
    position: fixed;
    border-radius: 50%;
    filter: blur(140px);
    pointer-events: none;
    z-index: 1;
    opacity: 0.42;
}

.glow-1 {
    width: 520px;
    height: 520px;
    top: -15%;
    left: 18%;
    animation: floatGlow 12s ease-in-out infinite alternate;
}

.glow-2 {
    width: 440px;
    height: 440px;
    bottom: -12%;
    right: 12%;
    animation: floatGlow 10s ease-in-out infinite alternate-reverse;
}

.glow-3 {
    width: 360px;
    height: 360px;
    top: 45%;
    left: -8%;
    animation: floatGlow 14s ease-in-out infinite alternate;
}

.glow-amber { background: #d97706; }
.glow-red { background: #dc2626; }
.glow-indigo { background: #4338ca; }
.glow-blue { background: #2563eb; }
.glow-cyan { background: #06b6d4; }
.glow-purple { background: #6366f1; }

@keyframes floatGlow {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(35px, 25px) scale(1.1); }
}

/* Dot Grid Pattern */
.bg-grid {
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
    background-size: 32px 32px;
    pointer-events: none;
    z-index: 2;
}

/* Main Error Card */
.error-card {
    position: relative;
    z-index: 10;
    background: rgba(15, 23, 42, 0.88);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 
        0 30px 70px -15px rgba(0, 0, 0, 0.85),
        0 0 0 1px rgba(255, 255, 255, 0.05),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(28px);
    -webkit-backdrop-filter: blur(28px);
    border-radius: 32px;
    max-width: 680px;
    width: 100%;
    padding: 48px 38px;
    text-align: center;
    margin: auto;
    animation: cardAppear 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes cardAppear {
    from {
        opacity: 0;
        transform: scale(0.94) translateY(24px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Brand Badge */
.brand-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 6px 16px 6px 8px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 9999px;
    margin-bottom: 24px;
}

.brand-logo-frame {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    color: #fff;
}

.bg-amber-frame {
    background: linear-gradient(135deg, #d97706, #ea580c);
    box-shadow: 0 2px 8px rgba(217, 119, 6, 0.4);
}

.bg-blue-frame {
    background: linear-gradient(135deg, #2563eb, #0284c7);
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4);
}

.brand-logo-frame img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.brand-name {
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #e2e8f0;
}

/* Hero Numbers */
.hero-number-container {
    position: relative;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-number {
    font-size: clamp(5.5rem, 16vw, 8rem);
    font-weight: 900;
    line-height: 1;
    letter-spacing: -0.05em;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    user-select: none;
    position: relative;
}

.gradient-403 {
    background: linear-gradient(135deg, #ffffff 0%, #fde68a 35%, #f59e0b 65%, #ef4444 100%);
    text-shadow: 0 10px 30px rgba(245, 158, 11, 0.25);
}

.gradient-404 {
    background: linear-gradient(135deg, #ffffff 0%, #93c5fd 40%, #38bdf8 70%, #818cf8 100%);
    text-shadow: 0 10px 30px rgba(56, 189, 248, 0.25);
}

/* Centerpiece Orbit */
.hero-visual {
    position: relative;
    width: 110px;
    height: 110px;
    margin: -25px auto 16px auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

.orbital-ring {
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    animation: spinOrbit 22s linear infinite;
}

.orbital-ring-inner {
    position: absolute;
    width: 80%;
    height: 80%;
    border-radius: 50%;
    animation: spinOrbitReverse 16s linear infinite;
}

.ring-amber { border: 2px dashed rgba(245, 158, 11, 0.4); }
.ring-red { border: 1px solid rgba(239, 68, 68, 0.4); }
.ring-cyan { border: 2px dashed rgba(56, 189, 248, 0.35); }
.ring-purple { border: 1px solid rgba(129, 140, 248, 0.4); }

.icon-core {
    position: relative;
    z-index: 2;
    width: 66px;
    height: 66px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: floatCore 4s ease-in-out infinite;
}

.core-amber {
    background: linear-gradient(135deg, #78350f 0%, #1c1917 100%);
    border: 2px solid rgba(245, 158, 11, 0.5);
    box-shadow: 0 10px 25px rgba(217, 119, 6, 0.4);
}
.core-amber i {
    font-size: 2.1rem;
    color: #fbbf24;
    filter: drop-shadow(0 2px 6px rgba(245, 158, 11, 0.5));
}

.core-blue {
    background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
    border: 2px solid rgba(56, 189, 248, 0.45);
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.4);
}
.core-blue i {
    font-size: 2.1rem;
    color: #38bdf8;
    filter: drop-shadow(0 2px 6px rgba(56, 189, 248, 0.5));
}

@keyframes spinOrbit {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes spinOrbitReverse {
    from { transform: rotate(360deg); }
    to { transform: rotate(0deg); }
}

@keyframes floatCore {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
}

/* Status Pill */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 14px;
    border-radius: 9999px;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    margin-bottom: 14px;
    text-transform: uppercase;
}

.status-pill-amber {
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.35);
    color: #fbbf24;
}

.status-pill-red {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.28);
    color: #f87171;
}

.pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    animation: pulseGlow 1.8s infinite;
}

.dot-amber {
    background-color: #f59e0b;
    box-shadow: 0 0 10px #f59e0b;
}

.dot-red {
    background-color: #ef4444;
    box-shadow: 0 0 10px #ef4444;
}

@keyframes pulseGlow {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: 0.5; }
}

.error-title {
    font-size: clamp(1.45rem, 4vw, 1.95rem);
    font-weight: 800;
    line-height: 1.25;
    letter-spacing: -0.025em;
    color: #ffffff;
    margin-bottom: 12px;
}

.error-desc {
    font-size: 0.95rem;
    line-height: 1.6;
    color: #94a3b8;
    max-width: 530px;
    margin: 0 auto 20px auto;
}

.custom-reason-alert {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.25);
    border-radius: 12px;
    padding: 10px 16px;
    margin: 0 auto 20px auto;
    max-width: 530px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #fca5a5;
    font-size: 0.88rem;
    font-weight: 500;
}

/* User Identity Card */
.user-auth-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 12px 18px;
    margin: 0 auto 26px auto;
    max-width: 530px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    text-align: left;
}

.user-auth-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar-circle {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}

.user-auth-text {
    font-size: 0.85rem;
}

.user-auth-text strong {
    color: #f1f5f9;
}

.user-auth-text span {
    color: #94a3b8;
    font-size: 0.78rem;
    display: block;
}

.role-pill {
    background: rgba(245, 158, 11, 0.18);
    border: 1px solid rgba(245, 158, 11, 0.35);
    color: #fde68a;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 3px 10px;
    border-radius: 9999px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Action Group */
.action-group {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 28px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 14px;
    font-family: 'Outfit', sans-serif;
    font-size: 0.92rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    border: none;
}

.btn-amber {
    background: linear-gradient(135deg, #d97706 0%, #ea580c 100%);
    color: #ffffff;
    box-shadow: 0 6px 20px rgba(217, 119, 6, 0.4);
}

.btn-amber:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(217, 119, 6, 0.55);
    color: #ffffff;
}

.btn-blue {
    background: linear-gradient(135deg, #2563eb 0%, #0284c7 100%);
    color: #ffffff;
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
}

.btn-blue:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.55);
    color: #ffffff;
}

.btn-action:active {
    transform: scale(0.97);
}

.btn-secondary-action {
    background: rgba(255, 255, 255, 0.06);
    color: #e2e8f0;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.btn-secondary-action:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.25);
    color: #ffffff;
    transform: translateY(-2px);
}

/* Security Hint Box */
.security-hint-box {
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 22px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    text-align: left;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 16px;
    padding: 14px 18px;
    margin-bottom: 20px;
}

.security-hint-box i {
    color: #fbbf24;
    font-size: 1.4rem;
    flex-shrink: 0;
    margin-top: 2px;
}

.security-hint-content h6 {
    font-size: 0.85rem;
    font-weight: 700;
    color: #f1f5f9;
    margin-bottom: 3px;
}

.security-hint-content p {
    font-size: 0.78rem;
    color: #94a3b8;
    line-height: 1.4;
    margin: 0;
}

/* Quick Navigation Grid */
.quick-nav-section {
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 24px;
    text-align: left;
    margin-bottom: 20px;
}

.quick-nav-header {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.quick-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.quick-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 14px;
    padding: 12px 14px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.2s ease;
}

.quick-card:hover {
    background: rgba(37, 99, 235, 0.1);
    border-color: rgba(56, 189, 248, 0.35);
    transform: translateY(-2px);
}

.quick-card-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: #38bdf8;
    flex-shrink: 0;
}

.quick-card-info h6 {
    font-size: 0.85rem;
    font-weight: 700;
    color: #f1f5f9;
    margin-bottom: 2px;
}

.quick-card-info p {
    font-size: 0.72rem;
    color: #94a3b8;
    line-height: 1.2;
    margin: 0;
}

/* Footer */
.footer-info {
    font-size: 0.78rem;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    padding-top: 18px;
}

.footer-info a {
    color: #38bdf8;
    text-decoration: none;
}

@media (max-width: 620px) {
    .quick-grid {
        grid-template-columns: 1fr;
    }
    .error-card {
        padding: 34px 20px;
        border-radius: 24px;
    }
    .action-group {
        flex-direction: column;
        width: 100%;
    }
    .btn-action {
        width: 100%;
    }
    .user-auth-card {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
