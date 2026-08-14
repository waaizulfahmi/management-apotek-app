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
        Schema::create('product_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_return_id')->constrained('product_returns')->onDelete('cascade');
            $table->string('medicine_id');
            $table->foreign('medicine_id')->references('kode')->on('obats');
            $table->foreignId('batch_id')->nullable()->constrained('medicine_batches');
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->string('reason')->nullable(); // Barang rusak, Salah barang, etc.
            $table->enum('condition', ['Baik', 'Rusak', 'Kadaluarsa', 'Kemasan Rusak'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_return_items');
    }
};
