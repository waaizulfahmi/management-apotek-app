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

    <title>500 - Gangguan Server Internal | {{ $pharmacyName }}</title>
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
            background-color: #0b0f19;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient glowing circles */
        .ambient-glow {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.18;
            pointer-events: none;
            z-index: 0;
        }

        .glow-1 {
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, #f43f5e 0%, #e11d48 100%);
            top: -100px;
            right: -80px;
            animation: pulse-slow 8s infinite alternate ease-in-out;
        }

        .glow-2 {
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, #8b5cf6 0%, #6366f1 100%);
            bottom: -80px;
            left: -60px;
            animation: pulse-slow 10s infinite alternate-reverse ease-in-out;
        }

        @keyframes pulse-slow {
            0% { transform: scale(1) translateY(0); opacity: 0.14; }
            100% { transform: scale(1.15) translateY(20px); opacity: 0.24; }
        }

        /* Subtle grid background */
        .grid-bg {
            position: fixed;
            inset: 0;
            background-image: 
                linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        /* Container */
        .container {
            width: 100%;
            max-width: 680px;
            position: relative;
            z-index: 1;
            text-align: center;
            animation: fadeInUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.97);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Brand Pill Header */
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 18px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
            border-radius: 9999px;
            margin-bottom: 32px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .brand-icon {
            width: 26px;
            height: 26px;
            background: linear-gradient(135deg, #f43f5e, #be123c);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 15px;
            box-shadow: 0 2px 8px rgba(244, 63, 94, 0.35);
        }

        .brand-name {
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            color: #e2e8f0;
        }

        /* Main Error Card */
        .error-card {
            background: rgba(18, 24, 38, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            padding: 44px 36px 40px;
            box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.04) inset;
            position: relative;
            overflow: hidden;
        }

        .error-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 10%;
            right: 10%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(244, 63, 94, 0.6), transparent);
        }

        /* Big 500 Display with Illustration */
        .error-code-container {
            position: relative;
            display: inline-block;
            margin-bottom: 12px;
        }

        .error-code {
            font-size: clamp(6.5rem, 16vw, 9.5rem);
            font-weight: 900;
            line-height: 1;
            letter-spacing: -4px;
            background: linear-gradient(180deg, #ffffff 30%, rgba(255, 255, 255, 0.15) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .error-code .accent-zero {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #f43f5e;
            -webkit-text-fill-color: initial;
            animation: pulse-icon 2.4s infinite ease-in-out;
        }

        @keyframes pulse-icon {
            0%, 100% { transform: scale(1); filter: drop-shadow(0 0 20px rgba(244, 63, 94, 0.4)); }
            50% { transform: scale(1.08); filter: drop-shadow(0 0 35px rgba(244, 63, 94, 0.8)); }
        }

        .badge-status {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(244, 63, 94, 0.12);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: #fb7185;
            margin-bottom: 18px;
        }

        .error-title {
            font-size: clamp(1.4rem, 4vw, 1.85rem);
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .error-description {
            font-size: 0.96rem;
            color: #94a3b8;
            line-height: 1.65;
            max-width: 520px;
            margin: 0 auto 28px;
        }

        /* Action Buttons */
        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            margin-bottom: 32px;
        }

        .btn-modern {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 0.92rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
            border: none;
        }

        .btn-modern:active {
            transform: scale(0.96);
        }

        .btn-primary {
            background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(244, 63, 94, 0.35);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #fb7185 0%, #f43f5e 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(244, 63, 94, 0.5);
            color: #fff;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
            backdrop-filter: blur(8px);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            color: #ffffff;
        }

        /* Quick Navigation Links */
        .quick-links-title {
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #64748b;
            margin-bottom: 14px;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px;
            margin-top: 6px;
        }

        .quick-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 14px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: all 0.2s ease;
            text-align: left;
        }

        .quick-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(244, 63, 94, 0.3);
            transform: translateY(-2px);
        }

        .quick-card-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(244, 63, 94, 0.12);
            color: #fb7185;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .quick-card-info h6 {
            font-size: 0.85rem;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 2px;
        }

        .quick-card-info p {
            font-size: 0.72rem;
            color: #64748b;
        }

        /* Footer */
        .footer-info {
            margin-top: 24px;
            font-size: 0.78rem;
            color: #64748b;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            padding: 0 4px;
        }

        .footer-info a {
            color: #fb7185;
            text-decoration: none;
        }

        .footer-info a:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            .error-card {
                padding: 32px 20px 28px;
                border-radius: 22px;
            }
            .btn-group {
                flex-direction: column;
                width: 100%;
            }
            .btn-modern {
                width: 100%;
            }
            .quick-links {
                grid-template-columns: 1fr;
            }
            .footer-info {
                justify-content: center;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Ambient Background Lighting -->
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>
    <div class="grid-bg"></div>

    <div class="container">
        <!-- Pharmacy Brand Badge -->
        <div class="brand-badge">
            <div class="brand-icon">
                <i class="bx bx-server"></i>
            </div>
            <span class="brand-name">{{ $pharmacyName }}</span>
        </div>

        <!-- Main Card -->
        <div class="error-card">
            <!-- 500 Code -->
            <div class="error-code-container">
                <div class="error-code">
                    <span>5</span>
                    <span class="accent-zero"><i class="bx bx-error-circle"></i></span>
                    <span>0</span>
                </div>
            </div>

            <div>
                <span class="badge-status">
                    <i class="bx bx-error-alt me-1"></i> Gangguan Server Internal
                </span>
            </div>

            <h1 class="error-title">Oops! Terjadi Kendala Pada Server</h1>
            <p class="error-description">
                Sistem mendeteksi adanya kendala saat memproses permintaan ini. Jangan khawatir, tim teknis sedang menangani kendala ini. Anda dapat mencoba memuat ulang halaman.
            </p>

            <!-- Navigation Buttons -->
            <div class="btn-group">
                <button onclick="window.location.reload()" class="btn-modern btn-primary">
                    <i class="bx bx-refresh fs-5"></i> Coba Muat Ulang
                </button>
                <a href="{{ $dashboardRoute }}" class="btn-modern btn-secondary">
                    <i class="bx bx-home-alt fs-5"></i> Ke Dashboard
                </a>
                <button onclick="history.back()" class="btn-modern btn-secondary">
                    <i class="bx bx-arrow-back fs-5"></i> Halaman Sebelumnya
                </button>
            </div>

            <!-- Quick Access Sections -->
            <div class="quick-links-title">
                Pintasan Navigasi
            </div>
            <div class="quick-links">
                <a href="{{ url('/pos') }}" class="quick-card">
                    <div class="quick-card-icon">
                        <i class="bx bx-cart-alt"></i>
                    </div>
                    <div class="quick-card-info">
                        <h6>Kasir POS</h6>
                        <p>Menu transaksi apotek</p>
                    </div>
                </a>

                <a href="{{ url('/sales/cashier') }}" class="quick-card">
                    <div class="quick-card-icon">
                        <i class="bx bx-user-check"></i>
                    </div>
                    <div class="quick-card-info">
                        <h6>Laporan Kasir</h6>
                        <p>Rekap shift & penjualan</p>
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
