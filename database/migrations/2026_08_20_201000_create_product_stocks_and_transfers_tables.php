<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Product Stocks per Outlet Table
        if (!Schema::hasTable('product_stocks')) {
            Schema::create('product_stocks', function (Blueprint $table) {
                $table->id();
                $table->string('obat_id', 100);
                $table->foreignId('outlet_id')->constrained('outlets')->onDelete('cascade');
                $table->decimal('stock', 15, 2)->default(0);
                $table->decimal('min_stock', 15, 2)->default(5);
                $table->string('rack_location', 100)->nullable();
                $table->timestamps();

                $table->foreign('obat_id')->references('kode')->on('obats')->onDelete('cascade');
                $table->unique(['obat_id', 'outlet_id']);
            });
        }

        // Populate initial product_stocks for existing products and main outlet
        $mainOutlet = DB::table('outlets')->where('is_main', true)->first() ?: DB::table('outlets')->first();
        if ($mainOutlet && Schema::hasTable('obats')) {
            $obats = DB::table('obats')->get();
            foreach ($obats as $o) {
                $kodeStr = (string) $o->kode;
                $exists = DB::table('product_stocks')
                    ->where('obat_id', $kodeStr)
                    ->where('outlet_id', $mainOutlet->id)
                    ->exists();
                if (!$exists) {
                    DB::table('product_stocks')->insert([
                        'obat_id' => $kodeStr,
                        'outlet_id' => $mainOutlet->id,
                        'stock' => (float)($o->stok ?? 0),
                        'min_stock' => (float)($o->min_stok ?? 5),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 2. Stock Transfers Table
        if (!Schema::hasTable('stock_transfers')) {
            Schema::create('stock_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('transfer_code', 50)->unique();
                $table->foreignId('from_outlet_id')->constrained('outlets')->onDelete('cascade');
                $table->foreignId('to_outlet_id')->constrained('outlets')->onDelete('cascade');
                $table->enum('status', ['DRAFT', 'SENT', 'IN_TRANSIT', 'RECEIVED', 'CANCELLED'])->default('DRAFT');
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->timestamp('sent_at')->nullable();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('received_at')->nullable();
                $table->text('notes')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // 3. Stock Transfer Items Table
        if (!Schema::hasTable('stock_transfer_items')) {
            Schema::create('stock_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->onDelete('cascade');
                $table->string('obat_id', 100);
                $table->decimal('qty_requested', 15, 2)->default(0);
                $table->decimal('qty_sent', 15, 2)->default(0);
                $table->decimal('qty_received', 15, 2)->default(0);
                $table->string('unit', 50)->default('PCS');
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->foreign('obat_id')->references('kode')->on('obats')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('product_stocks');
    }
};
