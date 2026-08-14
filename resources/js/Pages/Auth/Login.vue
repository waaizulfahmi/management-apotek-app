<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const showPassword = ref(false);
const num1 = ref(0);
const num2 = ref(0);
const userCaptcha = ref('');
const captchaError = ref('');

const generateCaptcha = () => {
    num1.value = Math.floor(Math.random() * 9) + 1;
    num2.value = Math.floor(Math.random() * 9) + 1;
    userCaptcha.value = '';
    captchaError.value = '';
};

onMounted(() => {
    generateCaptcha();
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    captchaError.value = '';
    const expected = num1.value + num2.value;

    if (parseInt(userCaptcha.value) !== expected) {
        captchaError.value = `Jawaban verifikasi captcha salah! Silakan hitung ulang ${num1.value} + ${num2.value}.`;
        generateCaptcha();
        return;
    }

    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
            generateCaptcha();
        },
    });
};
</script>

<template>
    <Head title="Login - Apotek Medika Sore" />

    <div class="login-wrapper min-vh-100 d-flex align-items-center justify-content-center p-3 p-md-4">
        <!-- Ambient Glowing Orbs Background -->
        <div class="glow-orb orb-1"></div>
        <div class="glow-orb orb-2"></div>
        <div class="glow-orb orb-3"></div>

        <div class="container" style="max-width: 1020px; position: relative; z-index: 10;">
            <div class="row g-0 rounded-4 shadow-2xl overflow-hidden border border-white border-opacity-10 login-card-glass">
                
                <!-- Left Pane: Hero & Brand -->
                <div class="col-lg-6 hero-pane p-4 p-md-5 d-flex flex-column justify-content-between text-white position-relative">
                    <div class="position-relative z-1">
                        <!-- Brand Logo Header -->
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="brand-icon-box rounded-3 p-3 bg-white bg-opacity-15 backdrop-blur d-flex align-items-center justify-content-center shadow-lg" style="width: 58px; height: 58px;">
                                <img v-if="$page.props.app_settings?.pharmacy_logo" :src="$page.props.app_settings.pharmacy_logo" alt="Logo Apotek" style="max-height: 40px; max-width: 40px; object-fit: contain;">
                                <i v-else class="bx bx-plus-medical text-warning fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-white mb-0 tracking-wide text-uppercase">{{ $page.props.app_settings?.pharmacy_name || 'APOTEK MEDIKA SORA' }}</h3>
                                <small class="text-white-50 text-uppercase tracking-wider">Pharmacy Management System</small>
                            </div>
                        </div>

                        <!-- Hero Main Headline -->
                        <div class="mt-4 pt-2 mb-4">
                            <span class="badge bg-warning bg-opacity-20 text-warning px-3 py-1.5 rounded-pill mb-3 border border-warning border-opacity-30">
                                <i class="bx bx-sparkles me-1"></i> System Ver 2.0
                            </span>
                            <h2 class="fw-extrabold display-6 text-white mb-3 leading-tight">
                                Kelola Apotek & Membership Pelanggan Lebih Cerdas
                            </h2>
                            <p class="text-white-50 lead fs-6 mb-0">
                                Platform terpadu pengadaan obat, kasir POS presisi, FEFO batch tracking, tiering loyalty member, dan laporan keuangan otomatis.
                            </p>
                        </div>
                    </div>

                    <!-- Feature Badges Pills -->
                    <div class="position-relative z-1 mt-4">
                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <div class="feature-pill p-2.5 rounded-3 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-10 d-flex align-items-center">
                                    <i class="bx bx-cart text-warning me-2 fs-5"></i>
                                    <span class="small fw-semibold">Kasir POS Presisi</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="feature-pill p-2.5 rounded-3 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-10 d-flex align-items-center">
                                    <i class="bx bx-shield-quarter text-warning me-2 fs-5"></i>
                                    <span class="small fw-semibold">Warning Alergi Obat</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="feature-pill p-2.5 rounded-3 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-10 d-flex align-items-center">
                                    <i class="bx bx-award text-warning me-2 fs-5"></i>
                                    <span class="small fw-semibold">Tierlist Member</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="feature-pill p-2.5 rounded-3 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-10 d-flex align-items-center">
                                    <i class="bx bx-bar-chart-alt-2 text-warning me-2 fs-5"></i>
                                    <span class="small fw-semibold">Laporan Laba/Rugi</span>
                                </div>
                            </div>
                        </div>

                        <div class="small text-white-50 border-top border-white border-opacity-15 pt-3 d-flex justify-content-between align-items-center">
                            <span>&copy; 2026 Apotek Medika Sora. All rights reserved.</span>
                            <span class="badge bg-white bg-opacity-10 font-monospace">v2.4.0</span>
                        </div>
                    </div>
                </div>

                <!-- Right Pane: Login Form -->
                <div class="col-lg-6 form-pane p-4 p-md-5 bg-white bg-opacity-95 backdrop-blur d-flex flex-column justify-content-center">
                    <div class="mb-4">
                        <h3 class="fw-bold text-dark mb-1">Selamat Datang Kembali 👋</h3>
                        <p class="text-muted small mb-0">Silakan masuk ke akun Anda untuk mengelola sistem apotek</p>
                    </div>

                    <!-- Alert Status -->
                    <div v-if="status" class="alert alert-success border-0 rounded-3 small mb-4 shadow-xs">
                        <i class="bx bx-check-circle me-1"></i>{{ status }}
                    </div>

                    <form @submit.prevent="submit">
                        <!-- Email / Username Input -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Alamat Email / Username</label>
                            <div class="input-group shadow-xs rounded-3 overflow-hidden">
                                <span class="input-group-text bg-light border-0 text-primary px-3">
                                    <i class="bx bx-user fs-5"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control border-0 bg-light py-2.5"
                                    :class="{ 'is-invalid': form.errors.email }"
                                    v-model="form.email"
                                    required
                                    autofocus
                                    placeholder="Username atau Email..."
                                />
                            </div>
                            <div v-if="form.errors.email" class="invalid-feedback d-block text-danger small mt-1">
                                {{ form.errors.email }}
                            </div>
                        </div>

                        <!-- Password Input -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-dark mb-0">Kata Sandi</label>
                                <Link
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="small text-primary text-decoration-none fw-semibold"
                                >
                                    Lupa Password?
                                </Link>
                            </div>
                            <div class="input-group shadow-xs rounded-3 overflow-hidden">
                                <span class="input-group-text bg-light border-0 text-primary px-3">
                                    <i class="bx bx-lock-alt fs-5"></i>
                                </span>
                                <input
                                    :type="showPassword ? 'text' : 'password'"
                                    class="form-control border-0 bg-light py-2.5"
                                    :class="{ 'is-invalid': form.errors.password }"
                                    v-model="form.password"
                                    required
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    class="input-group-text bg-light border-0 text-muted px-3"
                                    @click="showPassword = !showPassword"
                                >
                                    <i class="bx" :class="showPassword ? 'bx-show text-primary' : 'bx-hide'"></i>
                                </button>
                            </div>
                            <div v-if="form.errors.password" class="invalid-feedback d-block text-danger small mt-1">
                                {{ form.errors.password }}
                            </div>
                        </div>

                        <!-- Math CAPTCHA Input -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-dark mb-0">
                                    <i class="bx bx-shield-quarter me-1 text-primary"></i>Verifikasi Keamanan (CAPTCHA)
                                </label>
                                <button type="button" class="btn btn-link p-0 text-decoration-none small text-primary fw-semibold" @click="generateCaptcha" title="Acak Ulang Soal">
                                    <i class="bx bx-refresh me-1"></i> Acak Soal
                                </button>
                            </div>
                            <div class="input-group shadow-xs rounded-3 overflow-hidden">
                                <span class="input-group-text bg-primary bg-opacity-10 border-0 fw-extrabold text-primary px-3 font-monospace">
                                    {{ num1 }} + {{ num2 }} = ?
                                </span>
                                <input
                                    type="number"
                                    class="form-control border-0 bg-light py-2.5 font-monospace fw-bold text-dark"
                                    :class="{ 'is-invalid': captchaError }"
                                    v-model="userCaptcha"
                                    required
                                    placeholder="Hasil Penjumlahan..."
                                />
                            </div>
                            <div v-if="captchaError" class="invalid-feedback d-block text-danger small mt-1">
                                <i class="bx bx-x-circle me-1"></i>{{ captchaError }}
                            </div>
                        </div>

                        <!-- Remember Me -->
                        <div class="mb-4 d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="rememberMe"
                                    v-model="form.remember"
                                />
                                <label class="form-check-label small text-muted cursor-pointer fw-semibold ms-1" for="rememberMe">
                                    Ingat saya di perangkat ini
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            class="btn btn-primary w-100 py-3 rounded-3 fw-bold shadow-md d-flex align-items-center justify-content-center gap-2 btn-login"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing" class="spinner-border spinner-border-sm"></span>
                            <span v-else class="fs-6"><i class="bx bx-log-in me-1 fs-5 align-middle"></i> Masuk Ke Sistem</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Main Background with Deep Dark Slate & Blue Ambient Glow */
.login-wrapper {
    background: radial-gradient(circle at 50% 20%, #1e1b4b 0%, #0f172a 100%);
    position: relative;
    overflow: hidden;
    font-family: 'Outfit', 'Inter', -apple-system, sans-serif;
}

/* Glowing Ambient Orbs */
.glow-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(120px);
    opacity: 0.5;
    pointer-events: none;
}
.orb-1 {
    width: 480px;
    height: 480px;
    background: #3b82f6;
    top: -12%;
    left: -10%;
    animation: floatOrb 12s infinite alternate ease-in-out;
}
.orb-2 {
    width: 420px;
    height: 420px;
    background: #8b5cf6;
    bottom: -12%;
    right: -10%;
    animation: floatOrb 15s infinite alternate-reverse ease-in-out;
}
.orb-3 {
    width: 320px;
    height: 320px;
    background: #06b6d4;
    top: 45%;
    left: 38%;
    animation: floatOrb 10s infinite alternate ease-in-out;
}

@keyframes floatOrb {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(45px, 35px) scale(1.1); }
}

/* Glassmorphism Card Container */
.login-card-glass {
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
}

/* Hero Left Pane Gradient Overlay */
.hero-pane {
    background: linear-gradient(135deg, rgba(30, 58, 138, 0.92) 0%, rgba(15, 23, 42, 0.96) 100%);
    position: relative;
    border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.brand-icon-box {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
}

.backdrop-blur {
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

/* Feature Pills */
.feature-pill {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.feature-pill:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    transform: translateY(-2px);
}

/* Form Styling */
.form-pane {
    box-shadow: -10px 0 35px rgba(0, 0, 0, 0.2);
}

.form-control:focus {
    box-shadow: none;
    background-color: #ffffff !important;
}

.input-group:focus-within {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25) !important;
    border-color: #3b82f6 !important;
}

.btn-login {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-login:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(37, 99, 235, 0.4) !important;
}

.cursor-pointer {
    cursor: pointer;
}
</style>
