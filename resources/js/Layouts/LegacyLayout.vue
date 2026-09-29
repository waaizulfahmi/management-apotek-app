<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { showConfirm } from '@/Utils/swal';
import Swal from 'sweetalert2';

const page = usePage();
const user = computed(() => page.props.auth.user);
const activeOutlet = computed(() => page.props.active_outlet);
const userOutlets = computed(() => page.props.user_outlets || []);

const switchOutlet = (outletId) => {
    if (activeOutlet.value?.id === outletId) return;
    router.post(route('outlets.switch'), { outlet_id: outletId }, {
        preserveScroll: false,
    });
};

// Global Swal Flash Message Listener
watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        Swal.fire({
            title: 'Berhasil!',
            text: flash.success,
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#10b981',
            customClass: { popup: 'rounded-4 shadow-lg border-0' }
        });
    }
    if (flash?.error) {
        Swal.fire({
            title: 'Gagal!',
            text: flash.error,
            icon: 'error',
            confirmButtonText: 'Tutup',
            confirmButtonColor: '#ef4444',
            customClass: { popup: 'rounded-4 shadow-lg border-0' }
        });
    }
}, { deep: true, immediate: true });

const isCollapsed = ref(false);
const isDarkMode = ref(false);
const currentTime = ref('');
const hasLogoError = ref(false);

watch(() => page.props.app_settings?.pharmacy_logo, () => {
    hasLogoError.value = false;
});

const handleLogoError = () => {
    hasLogoError.value = true;
};

let clockInterval = null;

const updateClock = () => {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    currentTime.value = `${hours}:${minutes}:${seconds} WIB`;
};

// Full Day Name & Date in Indonesian
const currentDateText = computed(() => {
    const now = new Date();
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    const dayName = days[now.getDay()];
    const dateNum = now.getDate();
    const monthName = months[now.getMonth()];
    const year = now.getFullYear();

    return `${dayName}, ${dateNum} ${monthName} ${year}`;
});

const toggleSidebar = () => {
    isCollapsed.value = !isCollapsed.value;
};

// Dynamic Time-based Greeting
const greetingText = computed(() => {
    const hour = new Date().getHours();
    if (hour >= 4 && hour < 11) return 'Selamat Pagi 🌅';
    if (hour >= 11 && hour < 15) return 'Selamat Siang ☀️';
    if (hour >= 15 && hour < 18) return 'Selamat Sore 🌇';
    return 'Selamat Malam 🌙';
});

// Dark Mode Toggle Logic
const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value;
    if (isDarkMode.value) {
        document.body.classList.add('dark-mode');
        localStorage.setItem('theme', 'dark');
    } else {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('theme', 'light');
    }
};

const navWrapper = ref(null);
let unbindNavigate = null;

const handleSidebarScroll = () => {
    if (navWrapper.value) {
        sessionStorage.setItem('sidebar_scroll_pos', navWrapper.value.scrollTop);
    }
};

const restoreSidebarScroll = () => {
    if (navWrapper.value) {
        const savedPos = sessionStorage.getItem('sidebar_scroll_pos');
        if (savedPos !== null) {
            navWrapper.value.scrollTop = parseInt(savedPos, 10);
        } else {
            const activeLink = navWrapper.value.querySelector('.sidebar__link.active');
            if (activeLink) {
                activeLink.scrollIntoView({ block: 'nearest' });
            }
        }
    }
};

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);

    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        isDarkMode.value = true;
    }

    setTimeout(restoreSidebarScroll, 30);

    unbindNavigate = router.on('navigate', () => {
        setTimeout(restoreSidebarScroll, 30);
    });
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
    if (unbindNavigate) unbindNavigate();
});

const cleanOutletName = (name) => {
    if (!name) return '';
    return name.replace(/^apotek\s+/i, '');
};

const logout = () => {
    showConfirm("Konfirmasi Keluar", "Apakah Anda yakin ingin keluar dari aplikasi apotek?", () => {
        router.post(route('logout'));
    });
};
</script>

<template>
    <div class="layout-container">
        <!-- Top Navbar Header -->
        <header class="top-header shadow-xs d-flex justify-content-between align-items-center px-4" :class="{ 'collapsed': isCollapsed }">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-light border-0 shadow-xs" @click="toggleSidebar">
                    <i class="bx bx-menu fs-4 text-secondary"></i>
                </button>
                <div>
                    <h6 class="fw-bold text-dark mb-0">
                        {{ greetingText }}, <span class="text-primary">{{ user?.nama || user?.name || 'User' }}</span>! 👋
                    </h6>
                    <small class="text-muted" style="font-size: 0.75rem;">Aplikasi Manajemen Apotek</small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- Outlet Switcher Dropdown -->
                <div class="dropdown" v-if="userOutlets && userOutlets.length > 0">
                    <button class="btn btn-sm btn-outline-primary rounded-pill shadow-xs px-3 py-1.5 d-flex align-items-center gap-2 dropdown-toggle fw-bold" 
                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bx bx-store-alt fs-5"></i>
                        <span>{{ cleanOutletName(activeOutlet?.name) || 'Pilih Outlet' }}</span>
                        <span v-if="activeOutlet?.is_main" class="badge bg-warning text-dark style-xs ms-1">Pusat</span>
                        <span v-if="activeOutlet?.status === 'INACTIVE'" class="badge bg-danger text-white style-xs ms-1">Nonaktif</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 mt-2 p-2" style="min-width: 260px; z-index: 1050;">
                        <li class="dropdown-header text-uppercase fw-bold text-muted small pb-1 border-bottom mb-1">
                            Pilih Outlet / Cabang
                        </li>
                        <li v-for="out in userOutlets" :key="out.id">
                            <button class="dropdown-item rounded-2 py-2 d-flex align-items-center justify-content-between"
                                    :class="{ 'active bg-primary text-white': activeOutlet?.id === out.id }"
                                    @click="switchOutlet(out.id)">
                                <div class="d-flex align-items-center gap-2 text-truncate">
                                    <i class="bx" :class="out.is_main ? 'bx-building-house' : 'bx-store'"></i>
                                    <span class="fw-semibold text-truncate" style="max-width: 150px;">{{ cleanOutletName(out.name) }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-1 ms-2">
                                    <span v-if="out.is_main" class="badge bg-warning text-dark" style="font-size: 0.65rem;">Pusat</span>
                                    <span v-if="out.status === 'INACTIVE'" class="badge bg-danger text-white" style="font-size: 0.65rem;">Nonaktif</span>
                                    <i v-if="activeOutlet?.id === out.id" class="bx bx-check fs-5 ms-1"></i>
                                </div>
                            </button>
                        </li>
                        <li v-if="user?.role === 'admin' || user?.role === 'owner'" class="border-top mt-2 pt-2">
                            <Link :href="route('outlets.index')" class="dropdown-item text-primary fw-bold text-center rounded-2 py-1.5">
                                <i class="bx bx-cog me-1"></i> Kelola Master Outlet
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- Premium Redesigned Real-Time Clock & Date Widget -->
                <div class="clock-widget d-flex align-items-center gap-2 px-3 py-1.5 rounded-pill border">
                    <div class="d-flex align-items-center gap-1.5 pe-2 border-end border-secondary border-opacity-25">
                        <i class="bx bx-calendar-event text-primary fs-5"></i>
                        <span class="fw-semibold date-text" style="font-size: 0.82rem; letter-spacing: 0.01em;">{{ currentDateText }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 ps-1">
                        <span class="live-pulse-dot" title="Live Real-Time Clock"></span>
                        <i class="bx bx-time-five text-primary fs-5"></i>
                        <span class="fw-bold time-text font-monospace fs-6">{{ currentTime }}</span>
                    </div>
                </div>

                <!-- Dark Mode Toggle Icon Button -->
                <button class="btn btn-sm rounded-circle shadow-xs p-2 d-flex align-items-center justify-content-center border-0" 
                        :class="isDarkMode ? 'btn-warning text-dark' : 'btn-dark'" 
                        @click="toggleDarkMode" 
                        style="width: 38px; height: 38px;"
                        :title="isDarkMode ? 'Beralih ke Mode Terang' : 'Beralih ke Mode Gelap'">
                    <i class="bx fs-5" :class="isDarkMode ? 'bx-sun' : 'bx-moon'"></i>
                </button>

                <!-- User Profile Badge & Logout -->
                <div class="d-flex align-items-center gap-2 border-start ps-3">
                    <div class="text-end me-1 d-none d-md-block">
                        <div class="fw-bold small text-dark">{{ user?.nama || user?.name }}</div>
                        <span class="badge bg-primary text-capitalize" style="font-size: 0.68rem;">{{ user?.role }}</span>
                    </div>

                    <button class="btn btn-sm btn-outline-danger border-0 rounded-circle p-2" @click="logout" title="Keluar / Logout">
                        <i class="bx bx-power-off fs-5"></i>
                    </button>
                </div>
            </div>
        </header>
        <!-- Sidebar -->
        <aside class="sidebar shadow-sm" :class="{ 'collapsed': isCollapsed }">
            <div class="sidebar__header d-flex align-items-center justify-content-between p-3 border-bottom border-white border-opacity-10 mb-3">
                <div class="d-flex align-items-center gap-2 overflow-hidden">
                    <div class="brand-logo-wrapper d-flex align-items-center justify-content-center rounded-3 bg-white bg-opacity-15 p-1.5 flex-shrink-0 shadow-xs" style="width: 38px; height: 38px;">
                        <img v-if="$page.props.app_settings?.pharmacy_logo && !hasLogoError"
                             :src="$page.props.app_settings.pharmacy_logo"
                             @error="handleLogoError"
                             alt="Logo Apotek"
                             style="max-height: 30px; max-width: 30px; object-fit: contain;" />
                        <i v-else class="bx bx-plus-medical text-warning fs-4"></i>
                    </div>

                    <div v-if="!isCollapsed" class="sidebar__brand-info text-truncate">
                        <h6 class="fw-bold text-white mb-0 text-truncate font-outfit" style="font-size: 0.92rem; letter-spacing: 0.02em;">
                            {{ $page.props.app_settings?.pharmacy_name || 'APOTEK MEDIKA' }}
                        </h6>
                        <small class="text-white-50 d-block text-truncate" style="font-size: 0.65rem;">Pharmacy System</small>
                    </div>
                </div>

                <i class="bx bx-menu hamburger text-white fs-3 cursor-pointer m-0 p-1" @click="toggleSidebar" title="Toggle Sidebar"></i>
            </div>

            <div class="nav-links-wrapper" ref="navWrapper" @scroll="handleSidebarScroll">
                <!-- Admin Menus -->
                <template v-if="user?.role === 'admin'">
                    <!-- Category: UTAMA -->
                    <div class="sidebar__category-header">Utama</div>
                    <Link :href="route('admin.dashboard')" class="sidebar__link" :class="{ 'active': route().current('admin.dashboard') }">
                        <i class="bx bx-grid-alt sidebar__icon"></i>
                        <span class="sidebar__text">Dashboard</span>
                    </Link>

                    <!-- Category: TRANSAKSI & PELAYANAN -->
                    <div class="sidebar__category-header">Transaksi & Pelayanan</div>
                    <Link :href="route('pos.index')" class="sidebar__link" :class="{ 'active': route().current('pos.*') }">
                        <i class="bx bx-shopping-bag sidebar__icon"></i>
                        <span class="sidebar__text">POS / Kasir</span>
                    </Link>
                    <Link :href="route('shifts.index')" class="sidebar__link" :class="{ 'active': route().current('shifts.*') }">
                        <i class="bx bx-time-five sidebar__icon"></i>
                        <span class="sidebar__text">Shift Kasir</span>
                    </Link>
                    <Link :href="route('master-shifts.index')" class="sidebar__link" :class="{ 'active': route().current('master-shifts.*') }">
                        <i class="bx bx-calendar-event sidebar__icon"></i>
                        <span class="sidebar__text">Mastering Shift</span>
                    </Link>
                    <Link :href="route('sales.history.index')" class="sidebar__link" :class="{ 'active': route().current('sales.history.*') }">
                        <i class="bx bx-history sidebar__icon"></i>
                        <span class="sidebar__text">Riwayat Penjualan</span>
                    </Link>
                    <Link :href="route('sales.cashier.index')" class="sidebar__link" :class="{ 'active': route().current('sales.cashier.*') }">
                        <i class="bx bx-user-check sidebar__icon"></i>
                        <span class="sidebar__text">Penjualan Per Kasir</span>
                    </Link>
                    <Link :href="route('prescriptions.index')" class="sidebar__link" :class="{ 'active': route().current('prescriptions.*') }">
                        <i class="bx bx-notepad sidebar__icon"></i>
                        <span class="sidebar__text">Resep Dokter</span>
                    </Link>
                    <Link :href="route('purchases.index')" class="sidebar__link" :class="{ 'active': route().current('purchases.*') }">
                        <i class="bx bx-cart-download sidebar__icon"></i>
                        <span class="sidebar__text">Pembelian PO</span>
                    </Link>
                    <Link :href="route('returns.index')" class="sidebar__link" :class="{ 'active': route().current('returns.*') }">
                        <i class="bx bx-repost sidebar__icon"></i>
                        <span class="sidebar__text">Retur Barang</span>
                    </Link>

                    <!-- Category: INVENTARIS OBAT -->
                    <div class="sidebar__category-header">Inventaris Obat</div>
                    <Link :href="route('admin.obat.index')" class="sidebar__link" :class="{ 'active': route().current('admin.obat.*') }">
                        <i class="bx bx-capsule sidebar__icon"></i>
                        <span class="sidebar__text">Kelola Obat</span>
                    </Link>
                    <Link :href="route('inventory.stocks')" class="sidebar__link" :class="{ 'active': route().current('inventory.stocks') }">
                        <i class="bx bx-package sidebar__icon"></i>
                        <span class="sidebar__text">Stok Real Time</span>
                    </Link>
                    <Link :href="route('inventory.stocks-per-outlet')" class="sidebar__link" :class="{ 'active': route().current('inventory.stocks-per-outlet') }">
                        <i class="bx bx-store-alt sidebar__icon"></i>
                        <span class="sidebar__text">Stok Per Outlet</span>
                    </Link>
                    <Link :href="route('inventory.outlet-products.index')" class="sidebar__link" :class="{ 'active': route().current('inventory.outlet-products.*') }">
                        <i class="bx bx-list-check sidebar__icon"></i>
                        <span class="sidebar__text">Produk Per Outlet</span>
                    </Link>
                    <Link :href="route('stock-transfers.index')" class="sidebar__link" :class="{ 'active': route().current('stock-transfers.*') }">
                        <i class="bx bx-transfer-alt sidebar__icon"></i>
                        <span class="sidebar__text">Transfer Stok</span>
                    </Link>
                    <Link :href="route('opname.index')" class="sidebar__link" :class="{ 'active': route().current('opname.*') }">
                        <i class="bx bx-task sidebar__icon"></i>
                        <span class="sidebar__text">Stok Opname</span>
                    </Link>
                    <Link :href="route('inventory.movements')" class="sidebar__link" :class="{ 'active': route().current('inventory.movements') }">
                        <i class="bx bx-transfer sidebar__icon"></i>
                        <span class="sidebar__text">Kartu Stok</span>
                    </Link>

                    <!-- Category: MASTER DATA -->
                    <div class="sidebar__category-header">Master Data</div>
                    <Link :href="route('outlets.index')" class="sidebar__link" :class="{ 'active': route().current('outlets.*') }">
                        <i class="bx bx-store sidebar__icon text-warning"></i>
                        <span class="sidebar__text fw-bold text-warning">Manajemen Outlet</span>
                    </Link>
                    <Link :href="route('admin.units.index')" class="sidebar__link" :class="{ 'active': route().current('admin.units.*') }">
                        <i class="bx bx-purchase-tag-alt sidebar__icon"></i>
                        <span class="sidebar__text">Master Satuan</span>
                    </Link>
                    <Link :href="route('suppliers.index')" class="sidebar__link" :class="{ 'active': route().current('suppliers.*') }">
                        <i class="bx bxs-truck sidebar__icon fs-5"></i>
                        <span class="sidebar__text">Supplier PBF</span>
                    </Link>
                    <Link :href="route('doctors.index')" class="sidebar__link" :class="{ 'active': route().current('doctors.*') }">
                        <i class="bx bx-user-plus sidebar__icon"></i>
                        <span class="sidebar__text">Dokter</span>
                    </Link>
                    <Link :href="route('admin.kasir.index')" class="sidebar__link" :class="{ 'active': route().current('admin.kasir.*') }">
                        <i class="bx bx-user-circle sidebar__icon"></i>
                        <span class="sidebar__text">Kelola Kasir</span>
                    </Link>

                    <!-- Category: MEMBERSHIP & LOYALTY -->
                    <div class="sidebar__category-header">Membership & Loyalty</div>
                    <Link :href="route('membership.dashboard')" class="sidebar__link" :class="{ 'active': route().current('membership.dashboard') }">
                        <i class="bx bx-id-card sidebar__icon"></i>
                        <span class="sidebar__text">Dashboard Member</span>
                    </Link>
                    <Link :href="route('membership.members.index')" class="sidebar__link" :class="{ 'active': route().current('membership.members.*') }">
                        <i class="bx bx-user-check sidebar__icon"></i>
                        <span class="sidebar__text">Data Member</span>
                    </Link>
                    <Link :href="route('membership.tiers.index')" class="sidebar__link" :class="{ 'active': route().current('membership.tiers.*') }">
                        <i class="bx bx-crown sidebar__icon"></i>
                        <span class="sidebar__text">Tierlist Membership</span>
                    </Link>
                    <Link :href="route('membership.points.index')" class="sidebar__link" :class="{ 'active': route().current('membership.points.*') }">
                        <i class="bx bx-history sidebar__icon"></i>
                        <span class="sidebar__text">Riwayat Poin</span>
                    </Link>
                    <Link :href="route('membership.settings.index')" class="sidebar__link" :class="{ 'active': route().current('membership.settings.*') }">
                        <i class="bx bx-slider-alt sidebar__icon"></i>
                        <span class="sidebar__text">Pengaturan Poin</span>
                    </Link>

                    <!-- Category: KEUANGAN & LAPORAN -->
                    <div class="sidebar__category-header">Keuangan & Laporan</div>
                    <Link :href="route('finance.index')" class="sidebar__link" :class="{ 'active': route().current('finance.*') }">
                        <i class="bx bx-dollar-circle sidebar__icon"></i>
                        <span class="sidebar__text">Keuangan Laba/Rugi</span>
                    </Link>
                    <Link :href="route('reports.index')" class="sidebar__link" :class="{ 'active': route().current('reports.*') }">
                        <i class="bx bx-bar-chart-alt-2 sidebar__icon"></i>
                        <span class="sidebar__text">Laporan Alert</span>
                    </Link>

                    <!-- Category: USER & AKSES -->
                    <div class="sidebar__category-header">User & Akses</div>
                    <Link :href="route('admin.users.dashboard')" class="sidebar__link" :class="{ 'active': route().current('admin.users.dashboard') }">
                        <i class="bx bx-group sidebar__icon"></i>
                        <span class="sidebar__text">Dashboard User</span>
                    </Link>
                    <Link :href="route('admin.users.index')" class="sidebar__link" :class="{ 'active': route().current('admin.users.index') || route().current('admin.users.show') }">
                        <i class="bx bx-user-pin sidebar__icon"></i>
                        <span class="sidebar__text">Manajemen User</span>
                    </Link>
                    <Link :href="route('admin.roles.index')" class="sidebar__link" :class="{ 'active': route().current('admin.roles.*') }">
                        <i class="bx bx-shield-quarter sidebar__icon"></i>
                        <span class="sidebar__text">Role & Permission</span>
                    </Link>
                    <Link :href="route('admin.audit-logs.index')" class="sidebar__link" :class="{ 'active': route().current('admin.audit-logs.*') }">
                        <i class="bx bx-history sidebar__icon"></i>
                        <span class="sidebar__text">Audit Log</span>
                    </Link>
                    <Link :href="route('admin.trash.index')" class="sidebar__link" :class="{ 'active': route().current('admin.trash.*') }">
                        <i class="bx bx-trash-alt sidebar__icon text-danger"></i>
                        <span class="sidebar__text fw-bold text-danger">Trash / Data Terhapus</span>
                    </Link>
                    <Link :href="route('admin.approvals.index')" class="sidebar__link" :class="{ 'active': route().current('admin.approvals.*') }">
                        <i class="bx bx-check-shield sidebar__icon"></i>
                        <span class="sidebar__text">Approvals</span>
                    </Link>

                    <!-- Category: SYSTEM -->
                    <div class="sidebar__category-header">Pengaturan</div>
                    <Link :href="route('outlets.index')" class="sidebar__link" :class="{ 'active': route().current('outlets.*') }">
                        <i class="bx bx-store-alt sidebar__icon"></i>
                        <span class="sidebar__text">Manajemen Outlet</span>
                    </Link>
                    <Link :href="route('settings.index')" class="sidebar__link" :class="{ 'active': route().current('settings.*') }">
                        <i class="bx bx-cog sidebar__icon"></i>
                        <span class="sidebar__text">Audit & Settings</span>
                    </Link>
                    <Link :href="route('about.index')" class="sidebar__link" :class="{ 'active': route().current('about.*') }">
                        <i class="bx bx-info-circle sidebar__icon text-info"></i>
                        <span class="sidebar__text fw-semibold text-info">Tentang & Log Update</span>
                    </Link>
                </template>

                <!-- Kasir Menus -->
                <template v-else-if="user?.role === 'kasir'">
                    <div class="sidebar__category-header">Utama</div>
                    <Link :href="route('kasir.dashboard')" class="sidebar__link" :class="{ 'active': route().current('kasir.dashboard') }">
                        <i class="bx bx-grid-alt sidebar__icon"></i>
                        <span class="sidebar__text">Dashboard</span>
                    </Link>
                    <div class="sidebar__category-header">Pelayanan</div>
                    <Link :href="route('pos.index')" class="sidebar__link" :class="{ 'active': route().current('pos.*') }">
                        <i class="bx bx-shopping-bag sidebar__icon"></i>
                        <span class="sidebar__text">Transaksi POS</span>
                    </Link>
                    <Link :href="route('sales.history.index')" class="sidebar__link" :class="{ 'active': route().current('sales.history.*') }">
                        <i class="bx bx-history sidebar__icon"></i>
                        <span class="sidebar__text">Riwayat Penjualan</span>
                    </Link>
                    <Link :href="route('about.index')" class="sidebar__link" :class="{ 'active': route().current('about.*') }">
                        <i class="bx bx-info-circle sidebar__icon text-info"></i>
                        <span class="sidebar__text fw-semibold text-info">Tentang & Log Update</span>
                    </Link>
                </template>

                <!-- Owner Menus -->
                <template v-else-if="user?.role === 'owner'">
                    <div class="sidebar__category-header">Utama</div>
                    <Link :href="route('owner.dashboard')" class="sidebar__link" :class="{ 'active': route().current('owner.dashboard') }">
                        <i class="bx bx-grid-alt sidebar__icon"></i>
                        <span class="sidebar__text">Dashboard</span>
                    </Link>
                    <div class="sidebar__category-header">Laporan & Audit</div>
                    <Link :href="route('finance.index')" class="sidebar__link" :class="{ 'active': route().current('finance.*') }">
                        <i class="bx bx-dollar-circle sidebar__icon"></i>
                        <span class="sidebar__text">Keuangan Laba/Rugi</span>
                    </Link>
                    <Link :href="route('reports.index')" class="sidebar__link" :class="{ 'active': route().current('reports.*') }">
                        <i class="bx bx-bar-chart-alt-2 sidebar__icon"></i>
                        <span class="sidebar__text">Laporan Alert</span>
                    </Link>
                    <Link :href="route('about.index')" class="sidebar__link" :class="{ 'active': route().current('about.*') }">
                        <i class="bx bx-info-circle sidebar__icon text-info"></i>
                        <span class="sidebar__text fw-semibold text-info">Tentang & Log Update</span>
                    </Link>
                </template>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-wrapper" :class="{ 'expanded': isCollapsed }">
            <!-- Warning Banner for Inactive Outlet -->
            <div v-if="activeOutlet && (activeOutlet.status === 'INACTIVE' || !activeOutlet.is_active)"
                 class="alert alert-danger border-2 border-danger shadow-sm mx-4 mt-3 mb-0 d-flex align-items-center gap-3 rounded-3" role="alert">
                <i class="bx bx-error-circle fs-2 text-danger flex-shrink-0"></i>
                <div>
                    <h6 class="fw-bold mb-1">Peringatan: Outlet Nonaktif</h6>
                    <p class="mb-0 small">
                        Outlet <strong>{{ activeOutlet.name }}</strong> sedang tidak aktif dan tidak dapat digunakan untuk transaksi baru (POS, Shift Kasir, Pembelian PO, Stok Opname).
                    </p>
                </div>
            </div>

            <div class="flex-grow-1">
                <slot />
            </div>

            <!-- Sticky Layout Footer -->
            <footer class="layout-footer text-center py-3 text-muted border-top mt-5" style="font-size: 0.82rem;">
                Made with <i class="bx bxs-heart text-danger mx-1"></i> for <strong>Apotek Medika Sore</strong> &copy; {{ new Date().getFullYear() }} — All Rights Reserved.
            </footer>
        </main>
    </div>
</template>

<style scoped>
.top-header {
    position: fixed;
    top: 0;
    left: 260px;
    right: 0;
    height: 64px;
    background: #ffffff;
    border-bottom: 1px solid #edf2f7;
    z-index: 999;
    transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.top-header.collapsed {
    left: 75px;
}

.sidebar {
    width: 260px;
    background: #ffffff;
    color: #4a5568;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    display: flex;
    flex-direction: column;
    padding-top: 12px;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
    border-right: 1px solid #edf2f7;
}

.sidebar.collapsed {
    width: 75px;
}

.sidebar.collapsed .sidebar__text {
    visibility: hidden;
    opacity: 0;
    display: none;
}

.sidebar__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
}

.sidebar__header .hamburger {
    color: #2d3748;
    font-size: 1.8rem;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.sidebar__header .hamburger:hover {
    transform: scale(1.1);
    color: #3b6bff;
}

.sidebar__logo img {
    width: 110px;
    height: auto;
    display: block;
}

.nav-links-wrapper {
    overflow-y: auto;
    flex: 1;
    padding-bottom: 20px;
}

.sidebar__category-header {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #94a3b8;
    padding: 12px 18px 4px 18px;
    margin-top: 6px;
    white-space: nowrap;
    overflow: hidden;
}

:deep(body.dark-mode) .sidebar__category-header {
    color: #64748b;
}

.sidebar.collapsed .sidebar__category-header {
    display: none;
}

.sidebar__link {
    text-decoration: none;
    color: #4a5568;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 0.93rem;
    font-weight: 500;
    border-radius: 12px;
    margin: 4px 14px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    overflow: hidden;
}

.sidebar__link:hover {
    background: #edf5ff;
    color: #3b6bff;
    transform: translateX(4px);
}

.sidebar__link.active {
    background: linear-gradient(135deg, #3b6bff 0%, #2552e0 100%);
    font-weight: 600;
    color: #ffffff !important;
    box-shadow: 0 6px 16px rgba(59, 107, 255, 0.25);
}

.sidebar__icon {
    font-size: 1.35rem;
    transition: color 0.2s ease;
}

.sidebar__link.active .sidebar__icon {
    color: #ffffff !important;
}

.main-wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    padding-top: 64px;
    margin-left: 260px;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.main-wrapper.expanded {
    margin-left: 75px;
}

.layout-footer {
    margin-top: auto !important;
}

:deep(.content) {
    margin-left: 0 !important;
    margin-top: 15px !important;
    padding: 20px !important;
}

/* Premium Clock Widget Redesign */
.clock-widget {
    background: rgba(241, 245, 249, 0.95);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(59, 107, 255, 0.25) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
}

.date-text {
    color: #1e293b !important;
}

.clock-widget:hover {
    border-color: rgba(59, 107, 255, 0.5) !important;
    box-shadow: 0 6px 20px rgba(59, 107, 255, 0.15);
    transform: translateY(-1px);
}

.live-pulse-dot {
    width: 8px;
    height: 8px;
    background-color: #10b981;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 rgba(16, 185, 129, 0.6);
    animation: pulse-dot 1.8s infinite;
}

@keyframes pulse-dot {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
    }
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

.time-text {
    color: #1d4ed8 !important;
    font-weight: 700;
    letter-spacing: 0.05em;
}

/* Dark Mode Overrides */
:global(body.dark-mode) .clock-widget {
    background: rgba(15, 23, 42, 0.92) !important;
    border: 1px solid rgba(255, 255, 255, 0.18) !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
}

:global(body.dark-mode) .date-text {
    color: #f8fafc !important;
}

:global(body.dark-mode) .time-text {
    color: #38bdf8 !important;
}
</style>
