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
        // 1. Dedicated Purchase Orders (PO Ke PBF) Table
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique(); // Format: PO-PBF-YYYYMMDD-XXXX
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('warehouse_name')->default('Gudang Utama');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->integer('payment_term_days')->default(30);
            $table->date('due_date')->nullable();
            $table->enum('status', [
                'DRAFT',
                'WAITING_APPROVAL',
                'APPROVED',
                'REJECTED',
                'SENT',
                'CONFIRMED',
                'PARTIAL_RECEIVED',
                'RECEIVED',
                'CANCELLED'
            ])->default('DRAFT');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(11); // PPN 11%
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->text('notes_internal')->nullable();
            $table->text('notes_supplier')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->string('medicine_id'); // foreign to obats.kode
            $table->integer('order_quantity');
            $table->integer('bonus_quantity')->default(0);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->integer('received_quantity')->default(0);
            $table->integer('outstanding_quantity')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. Goods Receipts (Penerimaan Barang Fisik) Table
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('supplier_invoice_number');
            $table->date('received_date');
            $table->foreignId('received_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained('purchase_receipts')->onDelete('cascade');
            $table->string('medicine_id');
            $table->string('batch_number');
            $table->date('expired_date');
            $table->integer('received_quantity');
            $table->decimal('unit_cost', 15, 2);
            $table->timestamps();
        });

        // 3. Supplier Price History
        Schema::create('supplier_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('medicine_id');
            $table->decimal('price', 15, 2);
            $table->date('transaction_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_price_histories');
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
