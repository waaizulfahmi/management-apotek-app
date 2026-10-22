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
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Ubah enum status menjadi VARCHAR(30) agar mendukung 'DRAFT', 'FINAL', 'CANCELLED', 'received', dll
        DB::statement("ALTER TABLE purchases MODIFY status VARCHAR(30) NOT NULL DEFAULT 'DRAFT'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE purchases MODIFY status ENUM('draft','ordered','received','cancelled') NOT NULL DEFAULT 'draft'");
    }
};
