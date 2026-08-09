<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinanceMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Standard Chart of Accounts (COA) Matrix
        $coas = [
            ['code' => '1110', 'name' => 'Kas Kasir Utama', 'type' => 'asset', 'description' => 'Saldo Tunai Kasir'],
            ['code' => '1120', 'name' => 'Kas Gudang / Cadangan', 'type' => 'asset', 'description' => 'Saldo Tunai Gudang'],
            ['code' => '1210', 'name' => 'Bank BCA - Operasional', 'type' => 'asset', 'description' => 'Rekening BCA Utama'],
            ['code' => '1220', 'name' => 'Bank BRI / QRIS', 'type' => 'asset', 'description' => 'Rekening QRIS & BRI'],
            ['code' => '1300', 'name' => 'Piutang Pelanggan', 'type' => 'asset', 'description' => 'Piutang Penjualan Member'],
            ['code' => '1400', 'name' => 'Persediaan Obat (Inventory)', 'type' => 'asset', 'description' => 'Nilai Stok Fisik Obat'],
            ['code' => '2100', 'name' => 'Hutang Supplier (Accounts Payable)', 'type' => 'liability', 'description' => 'Kewajiban PO Supplier'],
            ['code' => '3100', 'name' => 'Modal Pemilik', 'type' => 'equity', 'description' => 'Modal Setor Pemilik Apotek'],
            ['code' => '3200', 'name' => 'Laba Ditahan', 'type' => 'equity', 'description' => 'Akumulasi Laba Periode Lalu'],
            ['code' => '4100', 'name' => 'Pendapatan Penjualan Obat', 'type' => 'revenue', 'description' => 'Omzet Penjualan POS'],
            ['code' => '4200', 'name' => 'Pendapatan Non-Penjualan / Lainnya', 'type' => 'revenue', 'description' => 'Jasa Konsultasi & Bunga'],
            ['code' => '5100', 'name' => 'Harga Pokok Penjualan (HPP / COGS)', 'type' => 'cogs', 'description' => 'HPP FEFO Obat Terjual'],
            ['code' => '6100', 'name' => 'Biaya Gaji Karyawan', 'type' => 'expense', 'description' => 'Gaji Apoteker & Kasir'],
            ['code' => '6200', 'name' => 'Biaya Sewa Tempat', 'type' => 'expense', 'description' => 'Sewa Ruko Apotek'],
            ['code' => '6300', 'name' => 'Biaya Listrik, Air & Internet', 'type' => 'expense', 'description' => 'Tagihan Utilitas'],
            ['code' => '6400', 'name' => 'Biaya Operasional & ATK', 'type' => 'expense', 'description' => 'Kertas Struk & Kantong Plastik'],
        ];

        foreach ($coas as $c) {
            DB::table('chart_of_accounts')->updateOrInsert(['code' => $c['code']], array_merge($c, ['updated_at' => now(), 'created_at' => now()]));
        }

        // 2. Default Kas & Bank Accounts
        $accounts = [
            ['account_code' => 'CASH-01', 'name' => 'Kas Kasir Utama', 'type' => 'cash', 'account_number' => 'CASH-001', 'initial_balance' => 5000000, 'current_balance' => 15500000, 'coa_id' => DB::table('chart_of_accounts')->where('code', '1110')->value('id')],
            ['account_code' => 'BANK-01', 'name' => 'Bank BCA Utama', 'type' => 'bank', 'account_number' => '8830-1234-5678', 'initial_balance' => 50000000, 'current_balance' => 78000000, 'coa_id' => DB::table('chart_of_accounts')->where('code', '1210')->value('id')],
            ['account_code' => 'QRIS-01', 'name' => 'QRIS Static Merchant', 'type' => 'qris', 'account_number' => 'QRIS-APOTEK-01', 'initial_balance' => 2000000, 'current_balance' => 12500000, 'coa_id' => DB::table('chart_of_accounts')->where('code', '1220')->value('id')],
        ];

        foreach ($accounts as $a) {
            DB::table('cash_bank_accounts')->updateOrInsert(['account_code' => $a['account_code']], array_merge($a, ['updated_at' => now(), 'created_at' => now()]));
        }

        // 3. Initial Sample Hutang & Piutang
        $supplierId = DB::table('suppliers')->first()->id ?? 1;
        $customerId = DB::table('customers')->first()->id ?? 1;

        DB::table('accounts_payables')->updateOrInsert(['payable_number' => 'AP-202608-001'], [
            'payable_number' => 'AP-202608-001',
            'supplier_id' => $supplierId,
            'invoice_number' => 'INV-SUP-8891',
            'total_amount' => 15000000,
            'paid_amount' => 5000000,
            'remaining_amount' => 10000000,
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => 'partial',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('accounts_receivables')->updateOrInsert(['receivable_number' => 'AR-202608-001'], [
            'receivable_number' => 'AR-202608-001',
            'customer_id' => $customerId,
            'invoice_number' => 'INV-POS-10029',
            'total_amount' => 2500000,
            'paid_amount' => 500000,
            'remaining_amount' => 2000000,
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'partial',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
