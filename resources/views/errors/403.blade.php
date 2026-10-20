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
        $user = $isLoggedIn ? auth()->user() : null;
        $userRole = $user ? $user->role : null;

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

        $customMessage = isset($exception) && $exception->getMessage() && !in_array($exception->getMessage(), ['Unauthorized access.', 'This action is unauthorized.', 'Forbidden'])
            ? $exception->getMessage() 
            : null;
    @endphp

    <title>403 - Akses Dibatasi | {{ $pharmacyName }}</title>
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
            background-color: #090b14;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Glowing Background Orbs (Amber & Crimson Security Tone) */
        .ambient-glow {
            position: fixed;
            border-radius: 50%;
            filter: blur(140px);
            pointer-events: none;
            z-index: 1;
            opacity: 0.4;
        }

        .glow-1 {
            width: 500px;
            height: 500px;
            background: #d97706;
            top: -15%;
            left: 18%;
            animation: floatGlow 12s ease-in-out infinite alternate;
        }

        .glow-2 {
            width: 440px;
            height: 440px;
            background: #dc2626;
            bottom: -12%;
            right: 12%;
            animation: floatGlow 10s ease-in-out infinite alternate-reverse;
        }

        .glow-3 {
            width: 340px;
            height: 340px;
            background: #4338ca;
            top: 45%;
            left: -8%;
            animation: floatGlow 14s ease-in-out infinite alternate;
        }

        @keyframes floatGlow {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(35px, 25px) scale(1.1); }
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

        /* Main Glassmorphic Card */
        .error-card {
            position: relative;
            z-index: 10;
            background: rgba(17, 20, 34, 0.86);
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
            border-color: rgba(251, 191, 36, 0.3);
            transform: translateY(-1px);
        }

        .brand-logo-frame {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #d97706, #ea580c);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.4);
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

        /* 403 Hero Display */
        .hero-403-container {
            position: relative;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-403-number {
            font-size: clamp(5.5rem, 16vw, 8rem);
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.05em;
            background: linear-gradient(135deg, #ffffff 0%, #fde68a 35%, #f59e0b 65%, #ef4444 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 10px 30px rgba(245, 158, 11, 0.25);
            user-select: none;
            position: relative;
        }

        /* Floating Security Shield / Lock Centerpiece */
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
            border: 2px dashed rgba(245, 158, 11, 0.4);
            animation: spinOrbit 22s linear infinite;
        }

        .orbital-ring-inner {
            position: absolute;
            width: 80%;
            height: 80%;
            border-radius: 50%;
            border: 1px solid rgba(239, 68, 68, 0.4);
            animation: spinOrbitReverse 16s linear infinite;
        }

        .icon-core {
            position: relative;
            z-index: 2;
            width: 66px;
            height: 66px;
            background: linear-gradient(135deg, #78350f 0%, #1c1917 100%);
            border: 2px solid rgba(245, 158, 11, 0.5);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(217, 119, 6, 0.4);
            animation: floatCore 4s ease-in-out infinite;
        }

        .icon-core i {
            font-size: 2.1rem;
            color: #fbbf24;
            filter: drop-shadow(0 2px 6px rgba(245, 158, 11, 0.5));
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
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.35);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: #fbbf24;
            margin-bottom: 14px;
            text-transform: uppercase;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: #f59e0b;
            border-radius: 50%;
            box-shadow: 0 0 10px #f59e0b;
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
            max-width: 530px;
            margin: 0 auto 20px auto;
        }

        /* Custom Exception Alert if Passed */
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

        /* User Identity Diagnostic Card */
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

        /* Action Buttons */
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

        .btn-primary-action {
            background: linear-gradient(135deg, #d97706 0%, #ea580c 100%);
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(217, 119, 6, 0.4);
        }

        .btn-primary-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(217, 119, 6, 0.55);
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

        /* Security Hint / Guidance Box */
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

        /* Footer */
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
            color: #fbbf24;
            text-decoration: none;
        }

        .footer-info a:hover {
            text-decoration: underline;
        }

        @media (max-width: 620px) {
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
</head>
<body>
    <!-- Ambient Background Lighting -->
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>
    <div class="ambient-glow glow-3"></div>
    <div class="bg-grid"></div>

    <!-- Main Glassmorphic 403 Card -->
    <div class="error-card">
        <!-- Pharmacy Brand Header -->
        <div class="brand-badge">
            <div class="brand-logo-frame">
                @if($pharmacyLogo)
                    <img src="{{ $pharmacyLogo }}" alt="{{ $pharmacyName }}">
                @else
                    <i class="bx bx-shield-quarter"></i>
                @endif
            </div>
            <span class="brand-name">{{ $pharmacyName }}</span>
        </div>

        <!-- 403 Hero Display -->
        <div class="hero-403-container">
            <div class="hero-403-number">403</div>
        </div>

        <!-- Floating Security Shield / Lock Centerpiece -->
        <div class="hero-visual">
            <div class="orbital-ring"></div>
            <div class="orbital-ring-inner"></div>
            <div class="icon-core">
                <i class="bx bxs-lock-alt"></i>
            </div>
        </div>

        <!-- Status Pill -->
        <div>
            <span class="status-pill">
                <span class="pulse-dot"></span>
                Akses Terbatas &bull; Unauthorized
            </span>
        </div>

        <!-- Titles & Friendly Explanation -->
        <h1 class="error-title">Akses Ditolak: Hak Akses Dibatasi</h1>
        <p class="error-desc">
            Maaf, akun Anda tidak memiliki izin atau wewenang yang diperlukan untuk mengakses halaman, fitur, atau tindakan ini.
        </p>

        <!-- Custom Reason if Exists -->
        @if($customMessage)
            <div class="custom-reason-alert">
                <i class="bx bx-error-alt"></i>
                <span>{{ $customMessage }}</span>
            </div>
        @endif

        <!-- Current User Auth Info Card -->
        <div class="user-auth-card">
            <div class="user-auth-info">
                <div class="user-avatar-circle">
                    <i class="bx bx-user"></i>
                </div>
                <div class="user-auth-text">
                    @if($isLoggedIn)
                        <strong>{{ $user->name }}</strong>
                        <span>{{ $user->email }}</span>
                    @else
                        <strong>Tamu (Belum Login)</strong>
                        <span>Silakan login terlebih dahulu</span>
                    @endif
                </div>
            </div>
            <div>
                @if($isLoggedIn)
                    <span class="role-pill">Peran: {{ strtoupper($userRole ?? 'Pengguna') }}</span>
                @else
                    <a href="{{ route('login') }}" class="role-pill" style="text-decoration: none;">Login Sekarang</a>
                @endif
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-group">
            <a href="{{ $dashboardRoute }}" class="btn-action btn-primary-action">
                <i class="bx bx-home-alt fs-5"></i> Kembali ke Menu Utama
            </a>
            <button onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ $dashboardRoute }}'" class="btn-action btn-secondary-action">
                <i class="bx bx-arrow-back fs-5"></i> Halaman Sebelumnya
            </button>
            @if($isLoggedIn)
                <form action="{{ route('logout') }}" method="POST" style="display: inline-block; margin: 0;">
                    @csrf
                    <button type="submit" class="btn-action btn-secondary-action">
                        <i class="bx bx-log-out fs-5"></i> Ganti Akun
                    </button>
                </form>
            @endif
        </div>

        <!-- Security Hint & Guidance -->
        <div class="security-hint-box">
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
            <span>&copy; {{ date('Y') }} {{ $pharmacyName }}. Keamanan Sistem Terjamin.</span>
            @if($pharmacyPhone)
                <span>Bantuan Admin: <a href="tel:{{ $pharmacyPhone }}"><i class="bx bx-phone me-1"></i>{{ $pharmacyPhone }}</a></span>
            @else
                <span>Sistem Informasi Manajemen Farmasi & Apotek</span>
            @endif
        </div>
    </div>
</body>
</html>
