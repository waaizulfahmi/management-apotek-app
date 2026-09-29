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
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Expand obats.kode to varchar(100)
        DB::statement("ALTER TABLE obats MODIFY kode VARCHAR(100) NOT NULL;");

        // Expand referencing columns
        if (Schema::hasTable('product_units')) {
            DB::statement("ALTER TABLE product_units MODIFY product_id VARCHAR(100) NOT NULL;");
        }
        if (Schema::hasTable('unit_conversions')) {
            DB::statement("ALTER TABLE unit_conversions MODIFY product_id VARCHAR(100) NOT NULL;");
        }
        if (Schema::hasTable('product_prices')) {
            DB::statement("ALTER TABLE product_prices MODIFY product_id VARCHAR(100) NOT NULL;");
        }
        if (Schema::hasTable('price_histories')) {
            DB::statement("ALTER TABLE price_histories MODIFY product_id VARCHAR(100) NOT NULL;");
        }
        if (Schema::hasTable('sale_items')) {
            DB::statement("ALTER TABLE sale_items MODIFY medicine_id VARCHAR(100) NOT NULL;");
        }
        if (Schema::hasTable('purchase_order_items')) {
            DB::statement("ALTER TABLE purchase_order_items MODIFY medicine_id VARCHAR(100) NOT NULL;");
        }
        if (Schema::hasTable('purchase_receipt_items')) {
            DB::statement("ALTER TABLE purchase_receipt_items MODIFY medicine_id VARCHAR(100) NOT NULL;");
        }
        if (Schema::hasTable('stock_movements')) {
            DB::statement("ALTER TABLE stock_movements MODIFY medicine_id VARCHAR(100) NOT NULL;");
        }
        if (Schema::hasTable('medicine_batches')) {
            DB::statement("ALTER TABLE medicine_batches MODIFY medicine_id VARCHAR(100) NOT NULL;");
        }
        if (Schema::hasTable('prescription_items')) {
            DB::statement("ALTER TABLE prescription_items MODIFY medicine_id VARCHAR(100) NOT NULL;");
        }
        if (Schema::hasTable('transaksis')) {
            DB::statement("ALTER TABLE transaksis MODIFY kode_produk VARCHAR(100) NOT NULL;");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::statement("ALTER TABLE obats MODIFY kode VARCHAR(10) NOT NULL;");
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
