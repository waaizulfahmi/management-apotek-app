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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('movement_number')->nullable()->after('id');
            $table->string('movement_type')->default('ADJUSTMENT_IN')->after('type'); // PURCHASE, SALE, SALE_RETURN, PURCHASE_RETURN, STOCK_OPNAME, DAMAGED, EXPIRED, BONUS, TRANSFER_IN, TRANSFER_OUT, REVERSAL
            $table->foreignId('supplier_id')->nullable()->after('movement_type')->constrained('suppliers')->nullOnDelete();
            $table->string('warehouse_name')->default('Gudang Utama')->after('supplier_id');
            $table->string('batch_number')->nullable()->after('warehouse_name');
            $table->date('expired_date')->nullable()->after('batch_number');
            $table->decimal('unit_cost', 15, 2)->default(0)->after('stock_after');
            $table->enum('status', ['POSTED', 'REVERSED', 'CANCELLED'])->default('POSTED')->after('notes');
        });

        // Stock Reconciliations Audit Table
        Schema::create('stock_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('reconcile_number')->unique();
            $table->string('medicine_id');
            $table->integer('master_stock');
            $table->integer('calculated_stock');
            $table->integer('difference');
            $table->enum('status', ['MATCH', 'MISMATCH'])->default('MATCH');
            $table->foreignId('checked_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reconciliations');
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn([
                'movement_number',
                'movement_type',
                'supplier_id',
                'warehouse_name',
                'batch_number',
                'expired_date',
                'unit_cost',
                'status'
            ]);
        });
    }
};
