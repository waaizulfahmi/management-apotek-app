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
        $tables = [
            'users',
            'customers',
            'obats',
            'medicine_categories',
            'suppliers',
            'purchases',
            'sales',
            'product_returns',
            'stock_opnames',
            'vouchers',
            'rewards',
            'cashier_shifts',
            'expenses',
            'expense_categories',
            'outlets',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'deleted_at')) {
                        $table->softDeletes();
                    }
                    if (!Schema::hasColumn($tableName, 'deleted_by')) {
                        $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'users',
            'customers',
            'obats',
            'medicine_categories',
            'suppliers',
            'purchases',
            'sales',
            'product_returns',
            'stock_opnames',
            'vouchers',
            'rewards',
            'cashier_shifts',
            'expenses',
            'expense_categories',
            'outlets',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'deleted_by')) {
                        $table->dropForeign([$tableName . '_deleted_by_foreign']);
                        $table->dropColumn('deleted_by');
                    }
                    if (Schema::hasColumn($tableName, 'deleted_at')) {
                        $table->dropSoftDeletes();
                    }
                });
            }
        }
    }
};
