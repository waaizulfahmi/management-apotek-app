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
        Schema::create('obats', function (Blueprint $table) {
            $table->string('kode', 10)->primary();
            $table->string('nama', 100);
            $table->string('gambar', 225)->nullable();
            $table->integer('stok')->unsigned();
            $table->enum('jenis_obat', ['Tablet', 'Kapsul', 'Sirup', 'Salep', 'Injeksi']);
            $table->enum('kategori', ['Antibiotik', 'Antipiretik', 'Analgesik', 'Antihistamin', 'Vitamin', 'Antiseptik', 'Herbal']);
            $table->decimal('harga', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obats');
    }
};
