<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('accounts_payables')) {
            DB::statement("ALTER TABLE accounts_payables MODIFY status VARCHAR(30) NOT NULL DEFAULT 'unpaid'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('accounts_payables')) {
            DB::statement("ALTER TABLE accounts_payables MODIFY status ENUM('unpaid','partial','paid','overdue') NOT NULL DEFAULT 'unpaid'");
        }
    }
};
