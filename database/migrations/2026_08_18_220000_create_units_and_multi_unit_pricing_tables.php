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
        // 1. Units Table (Master Satuan)
        if (!Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // 2. Add Unit FK Columns to Obats Table
        Schema::table('obats', function (Blueprint $table) {
            if (!Schema::hasColumn('obats', 'satuan_dasar_id')) {
                $table->foreignId('satuan_dasar_id')->nullable()->constrained('units')->nullOnDelete();
            }
            if (!Schema::hasColumn('obats', 'satuan_pembelian_id')) {
                $table->foreignId('satuan_pembelian_id')->nullable()->constrained('units')->nullOnDelete();
            }
            if (!Schema::hasColumn('obats', 'satuan_penjualan_id')) {
                $table->foreignId('satuan_penjualan_id')->nullable()->constrained('units')->nullOnDelete();
            }
        });

        // 3. Product Units Table (Units mapped to a Product)
        if (!Schema::hasTable('product_units')) {
            Schema::create('product_units', function (Blueprint $table) {
                $table->id();
                $table->string('product_id', 10);
                $table->foreign('product_id')->references('kode')->on('obats')->onDelete('cascade');
                $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
                $table->boolean('is_base_unit')->default(false);
                $table->boolean('is_purchase_unit')->default(false);
                $table->boolean('is_selling_unit')->default(true);
                $table->decimal('conversion_factor', 15, 4)->default(1.0000); // multiplier relative to base unit
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // 4. Unit Conversions Table (Parent -> Child Conversion per Product)
        if (!Schema::hasTable('unit_conversions')) {
            Schema::create('unit_conversions', function (Blueprint $table) {
                $table->id();
                $table->string('product_id', 10);
                $table->foreign('product_id')->references('kode')->on('obats')->onDelete('cascade');
                $table->foreignId('parent_unit_id')->constrained('units')->onDelete('cascade');
                $table->foreignId('child_unit_id')->constrained('units')->onDelete('cascade');
                $table->decimal('conversion_rate', 15, 4); // e.g. 1 Box = 10 Strip => 10.0000
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // 5. Product Prices Table (Purchase & Selling Prices per Unit)
        if (!Schema::hasTable('product_prices')) {
            Schema::create('product_prices', function (Blueprint $table) {
                $table->id();
                $table->string('product_id', 10);
                $table->foreign('product_id')->references('kode')->on('obats')->onDelete('cascade');
                $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
                $table->enum('price_type', ['PURCHASE', 'SELLING']);
                $table->decimal('price', 15, 2)->default(0.00);
                $table->boolean('is_default')->default(false);
                $table->date('effective_date')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // 6. Price Histories Table
        if (!Schema::hasTable('price_histories')) {
            Schema::create('price_histories', function (Blueprint $table) {
                $table->id();
                $table->string('product_id', 10);
                $table->foreign('product_id')->references('kode')->on('obats')->onDelete('cascade');
                $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
                $table->enum('price_type', ['PURCHASE', 'SELLING']);
                $table->decimal('old_price', 15, 2)->default(0.00);
                $table->decimal('new_price', 15, 2)->default(0.00);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 7. Add Snapshot Columns to Sale Items, Purchase Order Items, Purchase Receipt Items, and Stock Movements
        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->string('unit_name', 50)->nullable();
                $table->decimal('conversion_to_base', 15, 4)->default(1.0000);
                $table->integer('quantity_base')->nullable();
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_items', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->string('unit_name', 50)->nullable();
                $table->decimal('conversion_to_base', 15, 4)->default(1.0000);
                $table->integer('quantity_base')->nullable();
            }
        });

        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_receipt_items', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->string('unit_name', 50)->nullable();
                $table->decimal('conversion_to_base', 15, 4)->default(1.0000);
                $table->integer('quantity_base')->nullable();
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->string('unit_name', 50)->nullable();
                $table->integer('transaction_quantity')->nullable();
                $table->decimal('conversion_to_base', 15, 4)->default(1.0000);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'unit_name', 'transaction_quantity', 'conversion_to_base']);
        });

        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'unit_name', 'conversion_to_base', 'quantity_base']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'unit_name', 'conversion_to_base', 'quantity_base']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'unit_name', 'conversion_to_base', 'quantity_base']);
        });

        Schema::dropIfExists('price_histories');
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('unit_conversions');
        Schema::dropIfExists('product_units');

        Schema::table('obats', function (Blueprint $table) {
            $table->dropForeign(['satuan_dasar_id']);
            $table->dropForeign(['satuan_pembelian_id']);
            $table->dropForeign(['satuan_penjualan_id']);
            $table->dropColumn(['satuan_dasar_id', 'satuan_pembelian_id', 'satuan_penjualan_id']);
        });

        Schema::dropIfExists('units');
    }
};
