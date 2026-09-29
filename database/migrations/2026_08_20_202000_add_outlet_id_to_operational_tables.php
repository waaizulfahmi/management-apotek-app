<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $mainOutlet = DB::table('outlets')->where('is_main', true)->first() ?: DB::table('outlets')->first();
        $defaultOutletId = $mainOutlet ? $mainOutlet->id : null;

        $tables = [
            'transaksis',
            'sales',
            'cashier_shifts',
            'master_shifts',
            'purchases',
            'purchase_orders',
            'stock_opnames',
            'product_returns',
            'expenses',
            'financial_transactions',
            'journal_entries',
            'accounts_receivables',
            'accounts_payables',
            'stock_movements',
        ];

        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                    if (!Schema::hasColumn($tbl, 'outlet_id')) {
                        $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
                    }
                });

                if ($defaultOutletId) {
                    DB::table($tbl)->whereNull('outlet_id')->update(['outlet_id' => $defaultOutletId]);
                }
            }
        }

        // Cash & Bank Accounts Scope Enhancement
        if (Schema::hasTable('cash_bank_accounts')) {
            Schema::table('cash_bank_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('cash_bank_accounts', 'account_scope')) {
                    $table->string('account_scope', 20)->default('GLOBAL')->after('is_active'); // GLOBAL, OUTLET
                }
                if (!Schema::hasColumn('cash_bank_accounts', 'outlet_id')) {
                    $table->foreignId('outlet_id')->nullable()->after('account_scope')->constrained('outlets')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        // Keeping columns for safety
    }
};
