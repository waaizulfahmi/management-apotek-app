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
            if (!Schema::hasColumn('obats', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete()->after('harga');
            }
            if (!Schema::hasColumn('obats', 'supplier_name')) {
                $table->string('supplier_name')->nullable()->after('supplier_id');
            }
            if (!Schema::hasColumn('obats', 'merk')) {
                $table->string('merk')->nullable()->after('supplier_name');
            }
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_opname_items', 'supplier_name')) {
                $table->string('supplier_name')->nullable()->after('unit_price');
            }
            if (!Schema::hasColumn('stock_opname_items', 'merk')) {
                $table->string('merk')->nullable()->after('supplier_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'merk']);
        });

        Schema::table('obats', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'supplier_name', 'merk']);
        });
    }
};
