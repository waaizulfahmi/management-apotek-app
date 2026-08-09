@extends('layouts.legacy')

@section('content')
<section class="mt-4 me-4 ms-4">
    <div class="content mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <!-- Judul dan Tanggal -->
            <div>
                <h2 class="fw-bold text-dark mb-0">Dashboard Kasir</h2>
                <p class="text-muted">{{ date('l, d F Y') }}</p>
            </div>

            <!-- Bagian Profil -->
            <div class="d-flex align-items-center gap-4">
                <!-- Ikon Pesan Logout -->
                <button class="btn btn-outline-danger rounded-3 d-flex justify-content-center align-items-center ms-4 me-4" style="width: 45px; height: 45px;" onclick="confirmLogout(event)">
                    <i class="bx bx-exit" id="log_out" style="font-size: 1.5rem;"></i>
                </button>
                <div class="d-flex align-items-center">
                    @if(auth()->user()->profil)
                        <img src="{{ asset('Assets/uploads/' . auth()->user()->profil) }}" alt="Profile Image" class="profile-img me-3" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">
                    @else
                        <img src="{{ asset('Assets/img/default-profile.png') }}" alt="Profile Image" class="profile-img me-3" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%; background: #ccc;">
                    @endif
                    <div>
                        <h6 class="fw-bold mb-0">{{ auth()->user()->name }}</h6>
                        <p class="mb-0 text-muted">Kasir</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Total Kasir -->
            <div class="col-md-3 mb-4">
                <div class="card shadow-sm p-3" style="background-color: #f4f7fb; border-radius: 10px;">
                    <div class="d-flex align-items-center">
                        <i class="bx bxs-user-circle" style="font-size: 30px; color: #3b6bff;"></i>
                        <div class="ms-3">
                            <h5 class="card-title" style="font-weight: 600;">Total Kasir</h5>
                            <p class="card-text" style="color: #8e9baf;">{{ $total_kasir ?? 0 }} Kasir Terdaftar</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Obat -->
            <div class="col-md-3 mb-4">
                <div class="card shadow-sm p-3" style="background-color: #f4f7fb; border-radius: 10px;">
                    <div class="d-flex align-items-center">
                        <i class="bx bxs-capsule" style="font-size: 30px; color: #ff7043;"></i>
                        <div class="ms-3">
                            <h5 class="card-title" style="font-weight: 600;">Total Obat</h5>
                            <p class="card-text" style="color: #8e9baf;">{{ $total_obat ?? 0 }} Jenis Obat Tersedia</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Transaksi -->
            <div class="col-md-3 mb-4">
                <div class="card shadow-sm p-3" style="background-color: #f4f7fb; border-radius: 10px;">
                    <div class="d-flex align-items-center">
                        <i class="bx bxs-cart" style="font-size: 30px; color: #4caf50;"></i>
                        <div class="ms-3">
                            <h5 class="card-title" style="font-weight: 600;">Total Transaksi</h5>
                            <p class="card-text" style="color: #8e9baf;">{{ $total_transaksi ?? 0 }} Transaksi Terakhir</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Pendapatan -->
            <div class="col-md-3 mb-4">
                <div class="card shadow-sm p-3" style="background-color: #f4f7fb; border-radius: 10px;">
                    <div class="d-flex align-items-center">
                        <i class="bx bxs-wallet" style="font-size: 30px; color: #3b6bff;"></i>
                        <div class="ms-3">
                            <h5 class="card-title" style="font-weight: 600;">Total Pendapatan</h5>
                            <p class="card-text" style="color: #8e9baf;">Rp {{ number_format($total_pendapatan ?? 0, 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
