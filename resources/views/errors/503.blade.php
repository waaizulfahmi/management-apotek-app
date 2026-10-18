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

        $customMessage = isset($exception) && $exception->getMessage() && $exception->getMessage() !== 'Service Unavailable'
            ? $exception->getMessage() 
            : null;
    @endphp

    <title>Sedang Dalam Pemeliharaan - {{ $pharmacyName }}</title>
    @if($pharmacyLogo)
        <link rel="icon" href="{{ $pharmacyLogo }}">
    @endif

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
            width: 500px;
            height: 500px;
            background: #1e40af;
            top: -15%;
            left: 20%;
        }

        .glow-2 {
            width: 420px;
            height: 420px;
            background: #0284c7;
            bottom: -10%;
            right: 15%;
        }

        .glow-3 {
            width: 320px;
            height: 320px;
            background: #4f46e5;
            top: 40%;
            left: -10%;
        }

        /* Subtle Matrix Background Grid */
        .bg-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(rgba(255, 255, 255, 0.07) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
            z-index: 2;
        }

        /* Main Container Card */
        .maintenance-card {
            position: relative;
            z-index: 10;
            background: rgba(17, 24, 39, 0.88);
            border: 1px solid rgba(255, 255, 255, 0.09);
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 28px;
            max-width: 660px;
            width: 100%;
            padding: 44px 36px;
            text-align: center;
        }

        /* Brand Header */
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 6px 16px 6px 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
            margin-bottom: 28px;
        }

        .brand-logo-frame {
            width: 30px;
            height: 30px;
            background: rgba(37, 99, 235, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
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

        /* Hero Illustrated Centerpiece */
        .hero-visual {
            position: relative;
            width: 130px;
            height: 130px;
            margin: 0 auto 26px auto;
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
            animation: spinOrbit 20s linear infinite;
        }

        .orbital-ring-inner {
            position: absolute;
            width: 82%;
            height: 82%;
            border-radius: 50%;
            border: 1px solid rgba(99, 102, 241, 0.4);
            animation: spinOrbitReverse 14s linear infinite;
        }

        .icon-core {
            position: relative;
            z-index: 2;
            width: 76px;
            height: 76px;
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            border: 2px solid rgba(56, 189, 248, 0.4);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.45);
        }

        .icon-core i {
            font-size: 2.4rem;
            color: #38bdf8;
            animation: gearPulse 3s ease-in-out infinite;
        }

        @keyframes spinOrbit {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes spinOrbitReverse {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }

        @keyframes gearPulse {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(18deg); }
        }

        /* Status Pill */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 14px;
            border-radius: 9999px;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.3);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: #fbbf24;
            margin-bottom: 16px;
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
            50% { transform: scale(1.35); opacity: 0.6; }
        }

        /* Titles and Texts */
        .maintenance-title {
            font-size: 1.85rem;
            font-weight: 800;
            line-height: 1.3;
            letter-spacing: -0.025em;
            color: #ffffff;
            margin-bottom: 12px;
        }

        .maintenance-desc {
            font-size: 0.95rem;
            line-height: 1.65;
            color: #94a3b8;
            max-width: 520px;
            margin: 0 auto 28px auto;
        }

        /* Highlighted Custom Message */
        .custom-notice {
            background: rgba(37, 99, 235, 0.12);
            border: 1px solid rgba(59, 130, 246, 0.25);
            border-radius: 12px;
            padding: 12px 18px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            color: #bfdbfe;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* 3 Pillar Cards */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 30px;
            text-align: left;
        }

        @media (max-width: 600px) {
            .feature-grid {
                grid-template-columns: 1fr;
            }
            .maintenance-card {
                padding: 32px 20px;
            }
            .maintenance-title {
                font-size: 1.5rem;
            }
        }

        .feature-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 14px;
            padding: 14px 16px;
            transition: all 0.25s ease;
        }

        .feature-box:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(56, 189, 248, 0.3);
            transform: translateY(-2px);
        }

        .feature-box-icon {
            font-size: 1.35rem;
            color: #38bdf8;
            margin-bottom: 6px;
        }

        .feature-box-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #f8fafc;
            margin-bottom: 2px;
        }

        .feature-box-desc {
            font-size: 0.75rem;
            color: #94a3b8;
            line-height: 1.4;
        }

        /* Auto Refresh Countdown Bar */
        .countdown-container {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 22px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .countdown-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .countdown-header strong {
            color: #38bdf8;
            font-family: monospace;
            font-size: 0.95rem;
        }

        .progress-bar-bg {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 9999px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, #2563eb, #38bdf8);
            border-radius: 9999px;
            transition: width 1s linear;
        }

        /* Action Buttons */
        .action-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .btn-reload {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            font-family: inherit;
            font-size: 0.92rem;
            font-weight: 600;
            padding: 11px 24px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.35);
            transition: all 0.25s ease;
            text-decoration: none;
        }

        .btn-reload:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.5);
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.06);
            color: #cbd5e1;
            font-family: inherit;
            font-size: 0.92rem;
            font-weight: 500;
            padding: 11px 20px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        /* Footer Info */
        .footer-note {
            font-size: 0.78rem;
            color: #64748b;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* Administrator Bypass Toggle */
        .bypass-box {
            display: none;
            margin-top: 16px;
            padding: 14px;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            animation: fadeIn 0.25s ease;
        }

        .bypass-box.active {
            display: block;
        }

        .bypass-form {
            display: flex;
            gap: 8px;
        }

        .bypass-input {
            flex: 1;
            background: #1e293b;
            border: 1px solid #334155;
            color: #ffffff;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            outline: none;
        }

        .bypass-input:focus {
            border-color: #3b82f6;
        }

        .bypass-btn {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
        }

        .bypass-toggle-link {
            color: #64748b;
            font-size: 0.75rem;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .bypass-toggle-link:hover {
            color: #94a3b8;
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <!-- Ambient Glows -->
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>
    <div class="ambient-glow glow-3"></div>

    <!-- Matrix Dot Grid -->
    <div class="bg-grid"></div>

    <!-- Central Card -->
    <main class="maintenance-card">
        
        <!-- Brand Header Badge -->
        <div class="brand-badge">
            <div class="brand-logo-frame">
                @if($pharmacyLogo)
                    <img src="{{ $pharmacyLogo }}" alt="Logo {{ $pharmacyName }}">
                @else
                    <i class="bx bx-plus-medical text-white" style="font-size: 1rem;"></i>
                @endif
            </div>
            <span class="brand-name">{{ $pharmacyName }}</span>
        </div>

        <!-- Centerpiece Orbital Visual -->
        <div class="hero-visual">
            <div class="orbital-ring"></div>
            <div class="orbital-ring-inner"></div>
            <div class="icon-core">
                <i class="bx bx-cog"></i>
            </div>
        </div>

        <!-- Status Indicator Pill -->
        <div>
            <div class="status-pill">
                <span class="pulse-dot"></span>
                <span>Pemeliharaan Sistem Sedang Berlangsung</span>
            </div>
        </div>

        <!-- Title & Subtitle -->
        <h1 class="maintenance-title">Kami Sedang Melakukan Peningkatan Sistem</h1>
        <p class="maintenance-desc">
            Aplikasi sedang dalam proses pembaruan dan optimalisasi untuk meningkatkan kecepatan, keandalan, dan keamanan operasional apotek. Mohon tunggu sejenak, kami akan segera kembali online.
        </p>

        <!-- Optional Custom Notice -->
        @if($customMessage)
            <div class="custom-notice">
                <i class="bx bx-info-circle text-info fs-5"></i>
                <span><strong>Catatan:</strong> {{ $customMessage }}</span>
            </div>
        @endif

        <!-- 3 Feature Highlight Boxes -->
        <div class="feature-grid">
            <div class="feature-box">
                <div class="feature-box-icon">
                    <i class="bx bx-bolt-circle"></i>
                </div>
                <div class="feature-box-title">Performa POS</div>
                <div class="feature-box-desc">Optimalisasi kecepatan kasir &amp; transaksi kasir</div>
            </div>

            <div class="feature-box">
                <div class="feature-box-icon">
                    <i class="bx bx-shield-quarter"></i>
                </div>
                <div class="feature-box-title">Keamanan Data</div>
                <div class="feature-box-desc">Pencadangan berkas dan pembaruan enkripsi</div>
            </div>

            <div class="feature-box">
                <div class="feature-box-icon">
                    <i class="bx bx-package"></i>
                </div>
                <div class="feature-box-title">Sinkronisasi Stok</div>
                <div class="feature-box-desc">Penyelarasan multi-satuan dan batch obat</div>
            </div>
        </div>

        <!-- Auto-Refresh Countdown Section -->
        <div class="countdown-container">
            <div class="countdown-header">
                <span><i class="bx bx-time-five me-1"></i> Pemeriksaan Ulang Otomatis:</span>
                <span><strong id="countdown-val">30</strong> detik</span>
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" id="progress-fill"></div>
            </div>
        </div>

        <!-- Action Row -->
        <div class="action-row">
            <button type="button" class="btn-reload" id="reloadBtn" onclick="doReload()">
                <i class="bx bx-refresh fs-5" id="reloadIcon"></i>
                <span id="reloadText">Cek Status Sekarang</span>
            </button>

            @if($pharmacyPhone)
                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $pharmacyPhone) }}" target="_blank" class="btn-secondary">
                    <i class="bx bxl-whatsapp text-success fs-5"></i>
                    <span>Bantuan Darurat</span>
                </a>
            @endif
        </div>

        <!-- Secret Bypass Box (Hidden by Default) -->
        <div id="bypassBox" class="bypass-box">
            <p style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 8px;">
                Masukkan <strong>Secret Token</strong> maintenance mode untuk mengakses sistem:
            </p>
            <div class="bypass-form">
                <input type="text" id="bypassSecret" class="bypass-input" placeholder="Contoh: secret123" />
                <button type="button" class="bypass-btn" onclick="submitBypass()">Akses</button>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer-note">
            <span>&copy; {{ date('Y') }} {{ $pharmacyName }}. Pharmacy Management System.</span>
            <a href="javascript:void(0)" class="bypass-toggle-link" onclick="toggleBypass()">
                <i class="bx bx-key"></i> Akses Bypass Administrator
            </a>
        </footer>

    </main>

    <!-- Interactive Auto-Reload Script -->
    <script>
        const totalDuration = 30; // 30 seconds
        let remainingSeconds = totalDuration;
        const countdownEl = document.getElementById('countdown-val');
        const progressFill = document.getElementById('progress-fill');
        const reloadBtn = document.getElementById('reloadBtn');
        const reloadIcon = document.getElementById('reloadIcon');
        const reloadText = document.getElementById('reloadText');

        // Interval timer for countdown
        const timer = setInterval(() => {
            remainingSeconds--;
            if (countdownEl) {
                countdownEl.textContent = remainingSeconds;
            }
            if (progressFill) {
                const percent = (remainingSeconds / totalDuration) * 100;
                progressFill.style.width = percent + '%';
            }

            if (remainingSeconds <= 0) {
                clearInterval(timer);
                doReload();
            }
        }, 1000);

        function doReload() {
            if (reloadIcon) {
                reloadIcon.classList.add('bx-spin');
            }
            if (reloadText) {
                reloadText.textContent = 'Memeriksa Sistem...';
            }
            if (reloadBtn) {
                reloadBtn.style.opacity = '0.7';
                reloadBtn.disabled = true;
            }
            setTimeout(() => {
                window.location.reload();
            }, 400);
        }

        function toggleBypass() {
            const box = document.getElementById('bypassBox');
            if (box) {
                box.classList.toggle('active');
                if (box.classList.contains('active')) {
                    document.getElementById('bypassSecret')?.focus();
                }
            }
        }

        function submitBypass() {
            const secret = document.getElementById('bypassSecret')?.value.trim();
            if (secret) {
                window.location.href = '/' + encodeURIComponent(secret);
            }
        }

        // Allow Enter key in bypass input
        document.getElementById('bypassSecret')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                submitBypass();
            }
        });
    </script>
</body>
</html>
