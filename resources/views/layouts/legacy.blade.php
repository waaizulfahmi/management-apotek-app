<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- <link href="{{ asset('Assets/css/header.css') }}" rel="stylesheet"> -->
    <style>
        /* General Styles */
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #f1f5ffff
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background: #ffffffff;
            color: #4c4c4cff;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            padding-top: 10px;
            transition: width 0.3s ease;
        }

        /* Sidebar Header */
        .sidebar__header {
            display: flex;
            align-items: center;
            background-color: #fff;
            justify-content: space-between;
            padding: 10px 10px;
        }

        .sidebar__header .hamburger {
            color: rgb(0, 0, 0);
        }

        .sidebar__logo img {
            width: 120px;
            height: auto;
            margin-right: 25px;
            display: block;
        }

        .sidebar__link {
            text-decoration: none;
            color: inherit;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1rem;
            border-radius: 10px;
            margin: 5px 15px;
            transition: background 0.3s, transform 0.2s;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar__link:hover {
            background: #6fc2eeff;
            transform: scale(1.05);
        }

        .sidebar__link.active {
            background: #47b9f8ff;
            font-weight: bold;
            color: rgb(255, 255, 255);
        }

        .sidebar__icon {
            font-size: 1.3rem;
        }

        .sidebar__link.active .sidebar__icon {
            color: rgb(255, 255, 255);
        }

        .sidebar__text {
            display: inline-block;
            transition: opacity 0.3s, visibility 0.3s;
        }

        /* Content Wrapper */
        .content {
            margin-left: 260px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            background-color: #f1f5ffff;
        }

        .hamburger {
            margin: 10px 20px;
            font-size: 1.8rem;
            color: #fff;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .hamburger {
                margin: 10px auto;
                display: block;
            }

            /* Responsive Styles */
            .sidebar {
                width: 70px;
            }

            .content {
                margin-left: 70px;
            }

            .sidebar__text {
                visibility: hidden;
                opacity: 0;
            }

            .hamburger {
                left: 80px;
            }
        }
    </style>
    <title>Apoteker Medika Sore</title>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar shadow ms">
        <div class="sidebar__header mb-4">
            <!-- Hamburger Menu -->
            <i class="bx bx-menu hamburger" onclick="toggleSidebar()"></i>
            <div class="sidebar__logo">
                <img class="sidebar__text" src="{{ asset('Assets/img/LOGO.svg') }}" alt="Logo Apoteker">
            </div>
        </div>

        @if(auth()->user()->role === 'admin')
        <a href="{{ route('admin.dashboard') }}" class="sidebar__link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="bx bx-grid-alt sidebar__icon"></i>
            <span class="sidebar__text">Dashboard</span>
        </a>
        <a href="{{ route('admin.kasir.index') }}" class="sidebar__link {{ request()->routeIs('admin.kasir.*') ? 'active' : '' }}">
            <i class="bx bx-wallet sidebar__icon"></i>
            <span class="sidebar__text">Kasir</span>
        </a>
        <a href="{{ route('admin.obat.index') }}" class="sidebar__link {{ request()->routeIs('admin.obat.*') ? 'active' : '' }}">
            <i class="bx bx-capsule sidebar__icon"></i>
            <span class="sidebar__text">Obat</span>
        </a>
        <a href="#" class="sidebar__link">
            <i class="bx bx-shopping-bag sidebar__icon"></i>
            <span class="sidebar__text">Penjualan</span>
        </a>
        @elseif(auth()->user()->role === 'kasir')
        <a href="{{ route('kasir.dashboard') }}" class="sidebar__link {{ request()->routeIs('kasir.dashboard') ? 'active' : '' }}">
            <i class="bx bx-grid-alt sidebar__icon"></i>
            <span class="sidebar__text">Dashboard</span>
        </a>
        <a href="#" class="sidebar__link">
            <i class="bx bx-shopping-bag sidebar__icon"></i>
            <span class="sidebar__text">Transaksi</span>
        </a>
        @elseif(auth()->user()->role === 'owner')
        <a href="{{ route('owner.dashboard') }}" class="sidebar__link {{ request()->routeIs('owner.dashboard') ? 'active' : '' }}">
            <i class="bx bx-grid-alt sidebar__icon"></i>
            <span class="sidebar__text">Dashboard</span>
        </a>
        <a href="#" class="sidebar__link">
            <i class="bx bx-file sidebar__icon"></i>
            <span class="sidebar__text">Laporan</span>
        </a>
        @endif
    </aside>

    <div class="main-wrapper">
        @yield('content')
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('Assets/scripts/header.js') }}"></script>
    <script>
        // Konfirmasi Logout
        function confirmLogout(event) {
            event.preventDefault();
            if (confirm("Apakah Anda yakin ingin keluar?")) {
                document.getElementById('logout-form').submit();
            }
        }
    </script>
    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        @csrf
    </form>
</body>

</html>
