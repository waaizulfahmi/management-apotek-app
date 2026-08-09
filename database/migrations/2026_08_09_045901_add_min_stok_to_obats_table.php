<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('obats', function (Blueprint $table) {
            if (!Schema::hasColumn('obats', 'min_stok')) {
                $table->integer('min_stok')->default(10)->after('stok');
            }
            if (!Schema::hasColumn('obats', 'max_stok')) {
                $table->integer('max_stok')->default(100)->after('min_stok');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('obats', function (Blueprint $table) {
            $table->dropColumn(['min_stok', 'max_stok']);
        });
    }
};
