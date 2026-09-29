<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class AboutController extends Controller
{
    public function index()
    {
        $changelogs = [
            [
                'version' => 'v2.4.0',
                'date' => '20 Agustus 2026',
                'badge' => 'Terbaru',
                'badge_color' => 'bg-success',
                'summary' => 'Pembaruan Fitur Stok Opname Multi-Outlet & Navigasi Keyboard Cepat',
                'highlights' => [
                    'Sistem Stok Opname berbasis Outlet dengan enkapsulasi data stok & produk per-outlet.',
                    'Fitur Fast Keyboard Navigation (Enter / Arrow Down untuk simpan & lompat baris, Arrow Up, = / Ctrl+M untuk menyamakan stok, F2 untuk cari obat, F9 untuk finalisasi).',
                    'Penilaian Nilai Stok (Valuasi) menggunakan Harga Beli (HPP) secara ketat.',
                    'Cetak Dokumen Laporan Stok Opname Resmi & Export Excel (UTF-8 BOM CSV).',
                    'Integrasi Kartu Stok (Stock Movements) otomatis pada saat finalisasi SO.',
                    'Perbaikan bug kolom pada pencatatan mutasi stok & perbaikan error search filter.',
                ]
            ],
            [
                'version' => 'v2.3.0',
                'date' => '15 Agustus 2026',
                'badge' => 'Major Update',
                'badge_color' => 'bg-primary',
                'summary' => 'Modul Keuangan Laba Rugi, Manajemen Shift Kasir & Retur Barang',
                'highlights' => [
                    'Laporan Laba/Rugi Real-Time dengan rincian Omset Penjualan, HPP Terjual, Pengeluaran Operasional, dan Laba Bersih.',
                    'Manajemen Shift Kasir lengkap dengan Mastering Shift, Kas Awal/Akhir, Selisih Kas, dan Force Close.',
                    'Modul Retur Barang ke Supplier & Retur Pelanggan dengan pencetakan bukti retur.',
                    'Audit Log Aktivitas Pengguna berbasis peran (Role-Based Audit Logging).',
                ]
            ],
            [
                'version' => 'v2.2.0',
                'date' => '01 Agustus 2026',
                'badge' => 'Feature Update',
                'badge_color' => 'bg-info text-dark',
                'summary' => 'Membership & Loyalty System, Resep Dokter & Laporan Kasir',
                'highlights' => [
                    'Sistem Membership & Tiering (Bronze, Silver, Gold, Platinum) dengan akumulasi poin belanja.',
                    'Penukaran Hadiah Promo & Voucher Membership.',
                    'Pelayanan Resep Dokter (Racikan & Non-Racikan) dengan perhitungan tuslah & embalase.',
                    'Laporan Laba/Rugi Penjualan Per Kasir & Riwayat Transaksi Terperinci.',
                ]
            ],
            [
                'version' => 'v2.1.0',
                'date' => '15 Juli 2026',
                'badge' => 'Core Feature',
                'badge_color' => 'bg-secondary',
                'summary' => 'Point of Sales (POS) Kasir, Integrasi Scan Barcode & Purchase Order',
                'highlights' => [
                    'Kasir POS berkecepatan tinggi dengan dukungan Scan Barcode Scanner USB/Bluetooth.',
                    'Pilihan metode pembayaran Tunai, QRIS, Transfer Bank, dan Non-Tunai.',
                    'Pencetakan Struk Nota Kasir Thermal (58mm / 80mm).',
                    'Modul Purchase Order (PO) & Goods Receipt dari Supplier PBF.',
                ]
            ],
            [
                'version' => 'v1.0.0',
                'date' => '01 Juni 2026',
                'badge' => 'Initial Release',
                'badge_color' => 'bg-dark text-white',
                'summary' => 'Rilis Perdana Sistem Management Apotek',
                'highlights' => [
                    'Master Data Obat, Satuan, Kategori, Supplier, dan Dokter.',
                    'Manajemen Pengguna & Otorisasi Role (Admin, Kasir, Apoteker).',
                    'Dasar Arsitektur Sistem Laravel & Vue 3 Inertia.',
                ]
            ],
        ];

        $developerInfo = [
            'app_name' => config('app.name', 'Management Apotek'),
            'app_title' => 'Apotek Medika System',
            'version' => 'v2.4.0',
            'release_date' => '20 Agustus 2026',
            'developer' => 'Waaizulfahmi & Development Team',
            'role' => 'Full-Stack Software Engineer & AI System Architect',
            'organization' => 'Management Apotek Enterprise Solution',
            'contact_email' => 'support@apotekmedika.com',
            'license' => 'Proprietary Commercial License',
            'tech_stack' => [
                ['name' => 'Laravel Framework', 'version' => app()->version(), 'icon' => 'bxl-laravel text-danger'],
                ['name' => 'Vue.js & Inertia', 'version' => '3.x', 'icon' => 'bxl-vuejs text-success'],
                ['name' => 'PHP Engine', 'version' => PHP_VERSION, 'icon' => 'bxl-php text-primary'],
                ['name' => 'Database Engine', 'version' => 'MySQL 8.0', 'icon' => 'bx-data text-info'],
                ['name' => 'Styling & UI Design', 'version' => 'Bootstrap 5 + Custom Glassmorphism', 'icon' => 'bxl-bootstrap text-primary'],
            ]
        ];

        $systemEnv = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug') ? 'Enabled (ON)' : 'Disabled (OFF)',
            'timezone' => config('app.timezone', 'Asia/Jakarta'),
            'server_time' => now()->format('Y-m-d H:i:s T'),
            'database' => config('database.default'),
        ];

        return Inertia::render('About/Index', [
            'developerInfo' => $developerInfo,
            'changelogs' => $changelogs,
            'systemEnv' => $systemEnv,
        ]);
    }
}
