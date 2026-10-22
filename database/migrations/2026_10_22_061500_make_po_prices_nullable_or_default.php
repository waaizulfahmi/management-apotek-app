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
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('purchase_order_items')) {
            DB::statement("ALTER TABLE purchase_order_items 
                MODIFY unit_price DECIMAL(15, 2) DEFAULT 0.00,
                MODIFY subtotal DECIMAL(15, 2) DEFAULT 0.00,
                MODIFY discount_percent DECIMAL(5, 2) DEFAULT 0.00,
                MODIFY discount_amount DECIMAL(15, 2) DEFAULT 0.00;");
        }

        if (Schema::hasTable('purchase_orders')) {
            DB::statement("ALTER TABLE purchase_orders 
                MODIFY subtotal DECIMAL(15, 2) DEFAULT 0.00,
                MODIFY grand_total DECIMAL(15, 2) DEFAULT 0.00,
                MODIFY discount_percent DECIMAL(5, 2) DEFAULT 0.00,
                MODIFY discount_amount DECIMAL(15, 2) DEFAULT 0.00,
                MODIFY tax_percent DECIMAL(5, 2) DEFAULT 0.00,
                MODIFY tax_amount DECIMAL(15, 2) DEFAULT 0.00,
                MODIFY shipping_cost DECIMAL(15, 2) DEFAULT 0.00;");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse
    }
};
