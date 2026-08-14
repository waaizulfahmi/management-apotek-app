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
        Schema::create('product_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->enum('type', ['sale', 'purchase']);
            $table->unsignedBigInteger('reference_id'); // sales.id or purchases.id
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users'); // Created by
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['DRAFT', 'PENDING', 'APPROVED', 'COMPLETED', 'REJECTED', 'CANCELLED'])->default('DRAFT');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->enum('refund_method', ['Cash', 'Transfer', 'Saldo/Store Credit', 'Credit Note'])->nullable();
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_returns');
    }
};
