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
        // 1. Enhance stock_opnames table
        Schema::table('stock_opnames', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_opnames', 'total_items')) {
                $table->integer('total_items')->default(0)->after('status');
            }
            if (!Schema::hasColumn('stock_opnames', 'items_matched')) {
                $table->integer('items_matched')->default(0)->after('total_items');
            }
            if (!Schema::hasColumn('stock_opnames', 'items_surplus')) {
                $table->integer('items_surplus')->default(0)->after('items_matched');
            }
            if (!Schema::hasColumn('stock_opnames', 'items_deficit')) {
                $table->integer('items_deficit')->default(0)->after('items_surplus');
            }
            if (!Schema::hasColumn('stock_opnames', 'system_total_value')) {
                $table->decimal('system_total_value', 15, 2)->default(0)->after('items_deficit');
            }
            if (!Schema::hasColumn('stock_opnames', 'physical_total_value')) {
                $table->decimal('physical_total_value', 15, 2)->default(0)->after('system_total_value');
            }
            if (!Schema::hasColumn('stock_opnames', 'difference_value')) {
                $table->decimal('difference_value', 15, 2)->default(0)->after('physical_total_value');
            }
            if (!Schema::hasColumn('stock_opnames', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('difference_value');
            }
        });

        // 2. Enhance stock_opname_items table
        Schema::table('stock_opname_items', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_opname_items', 'unit_name')) {
                $table->string('unit_name')->default('Strip')->after('batch_id');
            }
            if (!Schema::hasColumn('stock_opname_items', 'unit_price')) {
                $table->decimal('unit_price', 15, 2)->default(0)->after('unit_name');
            }
            if (!Schema::hasColumn('stock_opname_items', 'difference_value')) {
                $table->decimal('difference_value', 15, 2)->default(0)->after('difference');
            }
            if (!Schema::hasColumn('stock_opname_items', 'is_counted')) {
                $table->boolean('is_counted')->default(false)->after('difference_value');
            }
            if (!Schema::hasColumn('stock_opname_items', 'notes')) {
                $table->text('notes')->nullable()->after('reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn(['unit_name', 'unit_price', 'difference_value', 'is_counted', 'notes']);
        });

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropColumn(['total_items', 'items_matched', 'items_surplus', 'items_deficit', 'system_total_value', 'physical_total_value', 'difference_value', 'completed_at']);
        });
    }
};
