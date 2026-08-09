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
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->string('warehouse_name')->default('Gudang Utama')->after('opname_number');
            $table->string('scope_type')->default('all')->after('warehouse_name'); // all, category, supplier, stock_status, manual
            $table->string('scope_filter')->nullable()->after('scope_type');
            $table->boolean('lock_stock')->default(false)->after('scope_filter');
            $table->integer('threshold_difference')->default(5)->after('lock_stock'); // min difference requiring approval
            $table->timestamp('counting_started_at')->nullable()->after('status');
            $table->timestamp('counting_completed_at')->nullable()->after('counting_started_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('counting_completed_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->enum('match_status', ['MATCH', 'SHORTAGE', 'SURPLUS'])->default('MATCH')->after('difference');
            $table->boolean('requires_recount')->default(false)->after('match_status');
            $table->enum('count_status', ['pending', 'counted', 'recounted'])->default('pending')->after('requires_recount');
        });

        Schema::create('stock_opname_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_item_id')->constrained('stock_opname_items')->onDelete('cascade');
            $table->integer('count_number')->default(1); // 1, 2, 3...
            $table->integer('quantity');
            $table->foreignId('counted_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_counts');
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn(['match_status', 'requires_recount', 'count_status']);
        });
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropColumn(['warehouse_name', 'scope_type', 'scope_filter', 'lock_stock', 'threshold_difference', 'counting_started_at', 'counting_completed_at', 'approved_by', 'approved_at']);
        });
    }
};
