<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $pharmacyName = 'Apotek Saya';
        $pharmacyLogo = null;
        $pharmacyPhone = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $settings = \Illuminate\Support\Facades\DB::table('settings')->pluck('value', 'key')->all();
                $pharmacyName = $settings['pharmacy_name'] ?? 'Apotek Saya';
                $pharmacyLogo = !empty($settings['pharmacy_logo']) ? asset($settings['pharmacy_logo']) : null;
                $pharmacyPhone = $settings['pharmacy_phone'] ?? null;
            }
        } catch (\Throwable $e) {
            // Safe fallback during offline/migration state
        }

        $attemptedUrl = request()->fullUrl();
        $isLoggedIn = auth()->check();
        $userRole = $isLoggedIn ? auth()->user()->role : null;
        $dashboardRoute = url('/dashboard');
        if ($isLoggedIn) {
            if ($userRole === 'admin' || $userRole === 'superadmin') {
                $dashboardRoute = url('/admin/dashboard');
            } elseif ($userRole === 'kasir') {
                $dashboardRoute = url('/pos');
            } elseif ($userRole === 'owner') {
                $dashboardRoute = url('/owner/dashboard');
            }
        }
    @endphp

    <title>404 - Halaman Tidak Ditemukan | {{ $pharmacyName }}</title>
    @if($pharmacyLogo)
        <link rel="icon" href="{{ $pharmacyLogo }}">
    @endif

    <!-- Frame breaker to ensure full screen view if embedded -->
    <script>
        if (window.top !== window.self) {
            try {
                window.top.location = window.self.location.href;
            } catch (e) {}
        }
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #080d1a;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Glowing Background Orbs */
        .ambient-glow {
            position: fixed;
            border-radius: 50%;
            filter: blur(140px);
            pointer-events: none;
            z-index: 1;
            opacity: 0.45;
        }

        .glow-1 {
            width: 520px;
            height: 520px;
            background: #2563eb;
            top: -15%;
            left: 15%;
            animation: floatGlow 12s ease-in-out infinite alternate;
        }

        .glow-2 {
            width: 440px;
            height: 440px;
            background: #06b6d4;
            bottom: -12%;
            right: 12%;
            animation: floatGlow 10s ease-in-out infinite alternate-reverse;
        }

        .glow-3 {
            width: 360px;
            height: 360px;
            background: #6366f1;
            top: 45%;
            left: -8%;
            animation: floatGlow 14s ease-in-out infinite alternate;
        }

        @keyframes floatGlow {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(40px, 30px) scale(1.1); }
        }

        /* Subtle Matrix Dot Grid */
        .bg-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
            z-index: 2;
        }

        /* Main Card Container */
        .error-card {
            position: relative;
            z-index: 10;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 
                0 30px 70px -15px rgba(0, 0, 0, 0.8),
                0 0 0 1px rgba(255, 255, 255, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            border-radius: 32px;
            max-width: 680px;
            width: 100%;
            padding: 48px 38px;
            text-align: center;
            animation: cardAppear 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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

        /* Pharmacy Brand Header */
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 6px 16px 6px 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
            margin-bottom: 24px;
            transition: all 0.25s ease;
        }

        .brand-badge:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(56, 189, 248, 0.3);
            transform: translateY(-1px);
        }

        .brand-logo-frame {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #2563eb, #0284c7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4);
        }

        .brand-logo-frame img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-logo-frame i {
            font-size: 1rem;
            color: #ffffff;
        }

        .brand-name {
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #e2e8f0;
        }

        /* 404 Hero Display */
        .hero-404-container {
            position: relative;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-404-number {
            font-size: clamp(5.5rem, 16vw, 8rem);
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.05em;
            background: linear-gradient(135deg, #ffffff 0%, #93c5fd 40%, #38bdf8 70%, #818cf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 10px 30px rgba(56, 189, 248, 0.25);
            user-select: none;
            position: relative;
        }

        /* Floating Capsule / Pill Icon Illustration */
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
            border: 2px dashed rgba(56, 189, 248, 0.35);
            animation: spinOrbit 22s linear infinite;
        }

        .orbital-ring-inner {
            position: absolute;
            width: 80%;
            height: 80%;
            border-radius: 50%;
            border: 1px solid rgba(129, 140, 248, 0.4);
            animation: spinOrbitReverse 16s linear infinite;
        }

        .icon-core {
            position: relative;
            z-index: 2;
            width: 66px;
            height: 66px;
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            border: 2px solid rgba(56, 189, 248, 0.45);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.4);
            animation: floatCore 4s ease-in-out infinite;
        }

        .icon-core i {
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
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.28);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: #f87171;
            margin-bottom: 14px;
            text-transform: uppercase;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: #ef4444;
            border-radius: 50%;
            box-shadow: 0 0 10px #ef4444;
            animation: pulseGlow 1.8s infinite;
        }

        @keyframes pulseGlow {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.4); opacity: 0.5; }
        }

        /* Headings & Descriptions */
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
            max-width: 520px;
            margin: 0 auto 20px auto;
        }

        /* Attempted Route Info Box */
        .route-info-box {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 10px 14px;
            margin: 0 auto 26px auto;
            max-width: 520px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.82rem;
            color: #cbd5e1;
            word-break: break-all;
        }

        .route-info-box i {
            color: #f87171;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        /* Primary Action Buttons */
        .action-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 30px;
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

        .btn-primary-action {
            background: linear-gradient(135deg, #2563eb 0%, #0284c7 100%);
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }

        .btn-primary-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.55);
            color: #ffffff;
        }

        .btn-primary-action:active {
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

        .btn-secondary-action:active {
            transform: scale(0.97);
        }

        /* Quick Destination Cards Grid */
        .quick-nav-section {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 24px;
            text-align: left;
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
            transition: background 0.2s ease;
        }

        .quick-card:hover .quick-card-icon {
            background: #0284c7;
            color: #ffffff;
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

        /* Footer info */
        .footer-info {
            margin-top: 24px;
            font-size: 0.78rem;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .footer-info a {
            color: #38bdf8;
            text-decoration: none;
        }

        .footer-info a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Ambient Background Effects -->
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>
    <div class="ambient-glow glow-3"></div>
    <div class="bg-grid"></div>

    <!-- Main 404 Glass Card -->
    <div class="error-card">
        <!-- Pharmacy Brand Header -->
        <div class="brand-badge">
            <div class="brand-logo-frame">
                @if($pharmacyLogo)
                    <img src="{{ $pharmacyLogo }}" alt="{{ $pharmacyName }}">
                @else
                    <i class="bx bx-plus-medical"></i>
                @endif
            </div>
            <span class="brand-name">{{ $pharmacyName }}</span>
        </div>

        <!-- 404 Big Heading with Visual Orbit -->
        <div class="hero-404-container">
            <div class="hero-404-number">404</div>
        </div>

        <!-- Illustrated Orbit Centerpiece -->
        <div class="hero-visual">
            <div class="orbital-ring"></div>
            <div class="orbital-ring-inner"></div>
            <div class="icon-core">
                <i class="bx bx-compass"></i>
            </div>
        </div>

        <!-- Status Pill -->
        <div>
            <span class="status-pill">
                <span class="pulse-dot"></span>
                Halaman Tidak Ditemukan
            </span>
        </div>

        <!-- Titles & Friendly Explanation -->
        <h1 class="error-title">Oops! Halaman Tidak Ada di Rak Apotek</h1>
        <p class="error-desc">
            Halaman atau tautan yang Anda tuju mungkin telah dipindahkan, diubah jalurnya, atau alamat URL yang Anda masukkan salah.
        </p>

        <!-- Route Attempt Snippet -->
        <div class="route-info-box">
            <i class="bx bx-error-circle"></i>
            <span>{{ parse_url($attemptedUrl, PHP_URL_PATH) ?: '/' }}</span>
        </div>

        <!-- Action Buttons -->
        <div class="action-group">
            <a href="{{ $dashboardRoute }}" class="btn-action btn-primary-action">
                <i class="bx bx-home-alt fs-5"></i> Kembali ke Dashboard
            </a>
            <button onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ $dashboardRoute }}'" class="btn-action btn-secondary-action">
                <i class="bx bx-arrow-back fs-5"></i> Halaman Sebelumnya
            </button>
            <button onclick="window.location.reload()" class="btn-action btn-secondary-action">
                <i class="bx bx-refresh fs-5"></i> Muat Ulang
            </button>
        </div>

        <!-- Quick Navigation Shortcuts -->
        <div class="quick-nav-section">
            <div class="quick-nav-header">
                <i class="bx bx-compass"></i> Akses Menu Cepat
            </div>
            <div class="quick-grid">
                <a href="{{ url('/pos') }}" class="quick-card">
                    <div class="quick-card-icon">
                        <i class="bx bx-shopping-bag"></i>
                    </div>
                    <div class="quick-card-info">
                        <h6>Kasir / POS</h6>
                        <p>Buka transaksi kasir</p>
                    </div>
                </a>

                <a href="{{ url('/sales/cashier') }}" class="quick-card">
                    <div class="quick-card-icon">
                        <i class="bx bx-user-check"></i>
                    </div>
                    <div class="quick-card-info">
                        <h6>Laporan Kasir</h6>
                        <p>Penjualan shift & kasir</p>
                    </div>
                </a>

                <a href="{{ url('/sales/history') }}" class="quick-card">
                    <div class="quick-card-icon">
                        <i class="bx bx-history"></i>
                    </div>
                    <div class="quick-card-info">
                        <h6>Riwayat Faktur</h6>
                        <p>Daftar transaksi nota</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Footer Assistance -->
        <div class="footer-info">
            <span>&copy; {{ date('Y') }} {{ $pharmacyName }}. Seluruh hak cipta dilindungi.</span>
            @if($pharmacyPhone)
                <span>Bantuan: <a href="tel:{{ $pharmacyPhone }}"><i class="bx bx-phone me-1"></i>{{ $pharmacyPhone }}</a></span>
            @else
                <span>Sistem Informasi Manajemen Apotek</span>
            @endif
        </div>
    </div>
</body>
</html>
