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
        Schema::table('obats', function (Blueprint $table) {
            $table->string('jenis_obat', 100)->default('Tablet')->change();
            $table->string('kategori', 100)->default('Umum')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('obats', function (Blueprint $table) {
            $table->enum('jenis_obat', ['Tablet', 'Kapsul', 'Sirup', 'Salep', 'Injeksi'])->change();
            $table->enum('kategori', ['Antibiotik', 'Antipiretik', 'Analgesik', 'Antihistamin', 'Vitamin', 'Antiseptik', 'Herbal'])->change();
        });
    }
};
