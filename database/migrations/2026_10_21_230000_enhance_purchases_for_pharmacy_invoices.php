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
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->index()->after('id');
            }
            if (!Schema::hasColumn('purchases', 'receipt_number')) {
                $table->string('receipt_number')->nullable()->unique()->after('invoice_number');
            }
            if (!Schema::hasColumn('purchases', 'delivery_order_number')) {
                $table->string('delivery_order_number')->nullable()->after('receipt_number');
            }
            if (!Schema::hasColumn('purchases', 'purchase_order_id')) {
                $table->unsignedBigInteger('purchase_order_id')->nullable()->index()->after('delivery_order_number');
            }
            if (!Schema::hasColumn('purchases', 'received_date')) {
                $table->date('received_date')->nullable()->after('purchase_date');
            }
            if (!Schema::hasColumn('purchases', 'payment_type')) {
                $table->string('payment_type', 30)->default('credit')->after('received_date'); // cash, credit, termin
            }
            if (!Schema::hasColumn('purchases', 'discount_type')) {
                $table->string('discount_type', 20)->default('nominal')->after('subtotal'); // nominal, percentage
            }
            if (!Schema::hasColumn('purchases', 'discount_rate')) {
                $table->decimal('discount_rate', 5, 2)->default(0)->after('discount_type');
            }
            if (!Schema::hasColumn('purchases', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_rate');
            }
            if (!Schema::hasColumn('purchases', 'tax_type')) {
                $table->string('tax_type', 20)->default('none')->after('discount_amount'); // none, ppn_11, ppn_12, manual
            }
            if (!Schema::hasColumn('purchases', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_type');
            }
            if (!Schema::hasColumn('purchases', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate');
            }
            if (!Schema::hasColumn('purchases', 'shipping_cost')) {
                $table->decimal('shipping_cost', 15, 2)->default(0)->after('tax_amount');
            }
            if (!Schema::hasColumn('purchases', 'remaining_amount')) {
                $table->decimal('remaining_amount', 15, 2)->default(0)->after('paid_amount');
            }
            if (!Schema::hasColumn('purchases', 'document_path')) {
                $table->string('document_path')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('purchases', 'finalized_at')) {
                $table->timestamp('finalized_at')->nullable()->after('document_path');
            }
            if (!Schema::hasColumn('purchases', 'finalized_by')) {
                $table->unsignedBigInteger('finalized_by')->nullable()->after('finalized_at');
            }
            if (!Schema::hasColumn('purchases', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('finalized_by');
            }
            if (!Schema::hasColumn('purchases', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('purchases', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            }
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_items', 'quantity')) {
                $table->decimal('quantity', 12, 2)->default(0)->after('expired_date');
            }
            if (!Schema::hasColumn('purchase_items', 'unit_id')) {
                $table->unsignedBigInteger('unit_id')->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('purchase_items', 'unit_name')) {
                $table->string('unit_name', 50)->nullable()->after('unit_id');
            }
            if (!Schema::hasColumn('purchase_items', 'conversion_factor')) {
                $table->decimal('conversion_factor', 10, 4)->default(1)->after('unit_name');
            }
            if (!Schema::hasColumn('purchase_items', 'quantity_base')) {
                $table->decimal('quantity_base', 12, 2)->default(0)->after('conversion_factor');
            }
            if (!Schema::hasColumn('purchase_items', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('unit_price');
            }
            if (!Schema::hasColumn('purchase_items', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_percent');
            }
            if (!Schema::hasColumn('purchase_items', 'tax_percent')) {
                $table->decimal('tax_percent', 5, 2)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('purchase_items', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_percent');
            }
            if (!Schema::hasColumn('purchase_items', 'subtotal_net')) {
                $table->decimal('subtotal_net', 15, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('purchase_items', 'notes')) {
                $table->string('notes')->nullable()->after('subtotal_net');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn([
                'quantity', 'unit_id', 'unit_name', 'conversion_factor', 'quantity_base',
                'discount_percent', 'discount_amount', 'tax_percent', 'tax_amount', 'subtotal_net', 'notes'
            ]);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_number', 'receipt_number', 'delivery_order_number', 'purchase_order_id',
                'received_date', 'payment_type', 'discount_type', 'discount_rate', 'discount_amount',
                'tax_type', 'tax_rate', 'tax_amount', 'shipping_cost', 'remaining_amount',
                'document_path', 'finalized_at', 'finalized_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason'
            ]);
        });
    }
};
