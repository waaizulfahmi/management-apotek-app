<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const fillDemo = (email, password) => {
    form.email = email;
    form.password = password;
};
</script>

<template>
    <Head title="Login - Apotek Medika Sore" />

    <div class="login-wrapper min-vh-100 d-flex align-items-center justify-content-center p-3 p-md-4">
        <!-- Ambient Glowing Orbs Background -->
        <div class="glow-orb orb-1"></div>
        <div class="glow-orb orb-2"></div>
        <div class="glow-orb orb-3"></div>

        <div class="container" style="max-width: 1040px; position: relative; z-index: 10;">
            <div class="row g-0 rounded-4 shadow-lg overflow-hidden border border-white border-opacity-10 login-card-glass">
                
                <!-- Left Pane: Hero & Brand -->
                <div class="col-lg-6 hero-pane p-4 p-md-5 d-flex flex-column justify-content-between text-white position-relative">
                    <div class="hero-overlay"></div>
                    <div class="position-relative z-1">
                        <!-- Brand Header -->
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="brand-icon-box rounded-3 p-3 bg-white bg-opacity-15 backdrop-blur d-flex align-items-center justify-content-center">
                                <i class="bx bx-plus-medical text-warning fs-2"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold text-white mb-0 tracking-wide">APOTEK MEDIKA SORE</h3>
                                <small class="text-white-50 text-uppercase tracking-wider">Pharmacy Management System</small>
                            </div>
                        </div>

                        <!-- Hero Main Headline -->
                        <div class="mt-4 pt-2 mb-4">
                            <h2 class="fw-extrabold display-6 text-white mb-3 leading-tight">
                                Kelola Apotek & Membership Pelanggan Lebih Cerdas
                            </h2>
                            <p class="text-white-50 lead fs-6">
                                Platform terpadu pengelolaan stok obat, transaksi Kasir POS, tiering loyalty member, dan laporan keuangan laba/rugi otomatis.
                            </p>
                        </div>
                    </div>

                    <!-- Feature Badges Pills -->
                    <div class="position-relative z-1 mt-4">
                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <div class="feature-pill p-2.5 rounded-3 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-10">
                                    <i class="bx bx-check-circle text-warning me-1.5"></i>
                                    <span class="small fw-semibold">Kasir POS Presisi</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="feature-pill p-2.5 rounded-3 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-10">
                                    <i class="bx bx-check-circle text-warning me-1.5"></i>
                                    <span class="small fw-semibold">Warning Alergi Obat</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="feature-pill p-2.5 rounded-3 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-10">
                                    <i class="bx bx-check-circle text-warning me-1.5"></i>
                                    <span class="small fw-semibold">Tierlist Member</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="feature-pill p-2.5 rounded-3 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-10">
                                    <i class="bx bx-check-circle text-warning me-1.5"></i>
                                    <span class="small fw-semibold">Laporan Laba/Rugi</span>
                                </div>
                            </div>
                        </div>

                        <div class="small text-white-50 border-top border-white border-opacity-15 pt-3">
                            &copy; 2026 Apotek Medika Sore. All rights reserved.
                        </div>
                    </div>
                </div>

                <!-- Right Pane: Login Form -->
                <div class="col-lg-6 form-pane p-4 p-md-5 bg-white bg-opacity-95 backdrop-blur d-flex flex-column justify-content-center">
                    <div class="mb-4">
                        <h3 class="fw-bold text-dark mb-1">Selamat Datang Kembali 👋</h3>
                        <p class="text-muted small">Silakan masuk ke akun Anda untuk mengakses sistem apotek</p>
                    </div>

                    <!-- Alert Status -->
                    <div v-if="status" class="alert alert-success border-0 rounded-3 small mb-4">
                        <i class="bx bx-check-circle me-1"></i>{{ status }}
                    </div>

                    <form @submit.prevent="submit">
                        <!-- Email Input -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">Alamat Email / Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted px-3">
                                    <i class="bx bx-envelope fs-5"></i>
                                </span>
                                <input
                                    type="email"
                                    class="form-control border-start-0 bg-light py-2.5"
                                    :class="{ 'is-invalid': form.errors.email }"
                                    v-model="form.email"
                                    required
                                    autofocus
                                    placeholder="nama@apotekmedika.com"
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
                                    class="small text-primary text-decoration-none"
                                >
                                    Lupa Password?
                                </Link>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted px-3">
                                    <i class="bx bx-lock-alt fs-5"></i>
                                </span>
                                <input
                                    :type="showPassword ? 'text' : 'password'"
                                    class="form-control border-start-0 border-end-0 bg-light py-2.5"
                                    :class="{ 'is-invalid': form.errors.password }"
                                    v-model="form.password"
                                    required
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    class="input-group-text bg-light border-start-0 text-muted px-3"
                                    @click="showPassword = !showPassword"
                                >
                                    <i class="bx" :class="showPassword ? 'bx-show' : 'bx-hide'"></i>
                                </button>
                            </div>
                            <div v-if="form.errors.password" class="invalid-feedback d-block text-danger small mt-1">
                                {{ form.errors.password }}
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
                                <label class="form-check-input-label small text-muted cursor-pointer" for="rememberMe">
                                    Ingat saya di perangkat ini
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            class="btn btn-primary w-100 py-3 rounded-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2 btn-login"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing" class="spinner-border spinner-border-sm"></span>
                            <span v-else><i class="bx bx-log-in me-1 fs-5"></i> Masuk Ke Sistem</span>
                        </button>
                    </form>

                    <!-- Quick Demo Credentials Fill -->
                    <div class="mt-4 pt-3 border-top border-light">
                        <small class="text-muted d-block mb-2 text-center">Akses Demo Cepat (Klik untuk Mengisi):</small>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary w-50 rounded-pill" @click="fillDemo('admin@gmail.com', 'password')">
                                🔑 Admin / Owner
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary w-50 rounded-pill" @click="fillDemo('kasir@gmail.com', 'password')">
                                💊 Kasir Apotek
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Main Background with Dark Violet/Slate Gradient */
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
    filter: blur(100px);
    opacity: 0.45;
    pointer-events: none;
}
.orb-1 {
    width: 450px;
    height: 450px;
    background: #3b82f6;
    top: -10%;
    left: -10%;
    animation: floatOrb 12s infinite alternate ease-in-out;
}
.orb-2 {
    width: 400px;
    height: 400px;
    background: #8b5cf6;
    bottom: -10%;
    right: -10%;
    animation: floatOrb 15s infinite alternate-reverse ease-in-out;
}
.orb-3 {
    width: 300px;
    height: 300px;
    background: #06b6d4;
    top: 50%;
    left: 40%;
    animation: floatOrb 10s infinite alternate ease-in-out;
}

@keyframes floatOrb {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(40px, 30px) scale(1.1); }
}

/* Glassmorphism Card Container */
.login-card-glass {
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

/* Hero Left Pane Gradient Overlay */
.hero-pane {
    background: linear-gradient(135deg, rgba(30, 58, 138, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
    position: relative;
    border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.brand-icon-box {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.backdrop-blur {
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

/* Feature Pills */
.feature-pill {
    transition: all 0.2s ease;
}
.feature-pill:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    transform: translateY(-2px);
}

/* Form Styling */
.form-pane {
    box-shadow: -10px 0 30px rgba(0, 0, 0, 0.15);
}

.form-control:focus {
    box-shadow: none;
    border-color: #3b82f6;
}

.btn-login {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-login:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.35) !important;
}

/* Custom Scroll & Input Touch */
.cursor-pointer {
    cursor: pointer;
}
</style>
