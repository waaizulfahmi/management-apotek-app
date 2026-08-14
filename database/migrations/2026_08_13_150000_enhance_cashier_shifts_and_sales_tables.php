<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Enhance cashier_shifts table if columns don't exist
        Schema::table('cashier_shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('cashier_shifts', 'outlet_id')) {
                $table->foreignId('outlet_id')->nullable()->after('cashier_id')->constrained('outlets');
            }
            if (!Schema::hasColumn('cashier_shifts', 'shift_name')) {
                $table->string('shift_name')->default('Pagi')->after('outlet_id');
            }
            if (!Schema::hasColumn('cashier_shifts', 'cash_sales')) {
                $table->decimal('cash_sales', 15, 2)->default(0)->after('opening_cash');
            }
            if (!Schema::hasColumn('cashier_shifts', 'non_cash_sales')) {
                $table->decimal('non_cash_sales', 15, 2)->default(0)->after('cash_sales');
            }
            if (!Schema::hasColumn('cashier_shifts', 'cash_refunds')) {
                $table->decimal('cash_refunds', 15, 2)->default(0)->after('non_cash_sales');
            }
            if (!Schema::hasColumn('cashier_shifts', 'cash_adjustments')) {
                $table->decimal('cash_adjustments', 15, 2)->default(0)->after('cash_refunds');
            }
            if (!Schema::hasColumn('cashier_shifts', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users');
            }
            if (!Schema::hasColumn('cashier_shifts', 'force_close_reason')) {
                $table->text('force_close_reason')->nullable()->after('difference_reason');
            }
        });

        // Modify status enum in cashier_shifts if needed
        try {
            DB::statement("ALTER TABLE cashier_shifts MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'OPEN'");
        } catch (\Throwable $e) {
            // Ignore if driver doesn't support raw ALTER
        }

        // 2. Add shift_id & outlet_id to sales table
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'shift_id')) {
                $table->foreignId('shift_id')->nullable()->after('cashier_id')->constrained('cashier_shifts');
            }
            if (!Schema::hasColumn('sales', 'outlet_id')) {
                $table->foreignId('outlet_id')->nullable()->after('shift_id')->constrained('outlets');
            }
        });

        // 3. Create shift_cash_movements table
        if (!Schema::hasTable('shift_cash_movements')) {
            Schema::create('shift_cash_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shift_id')->constrained('cashier_shifts')->onDelete('cascade');
                $table->enum('type', ['in', 'out']);
                $table->decimal('amount', 15, 2);
                $table->string('reason');
                $table->foreignId('user_id')->constrained('users');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_cash_movements');
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn(['shift_id', 'outlet_id']);
        });
    }
};
