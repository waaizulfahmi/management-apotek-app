<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_outlets')) {
            Schema::create('product_outlets', function (Blueprint $table) {
                $table->id();
                $table->string('obat_id', 100);
                $table->foreignId('outlet_id')->constrained('outlets')->onDelete('cascade');
                $table->boolean('is_active')->default(true);
                $table->decimal('price', 15, 2)->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->foreign('obat_id')->references('kode')->on('obats')->onDelete('cascade');
                $table->unique(['obat_id', 'outlet_id']);
            });
        }

        // Auto-attach all existing obats to all existing outlets so current data is preserved seamlessly
        if (Schema::hasTable('obats') && Schema::hasTable('outlets')) {
            $obats = DB::table('obats')->pluck('kode');
            $outlets = DB::table('outlets')->pluck('id');

            foreach ($outlets as $outletId) {
                foreach ($obats as $obatKode) {
                    $exists = DB::table('product_outlets')
                        ->where('obat_id', (string)$obatKode)
                        ->where('outlet_id', $outletId)
                        ->exists();

                    if (!$exists) {
                        DB::table('product_outlets')->insert([
                            'obat_id' => (string)$obatKode,
                            'outlet_id' => $outletId,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_outlets');
    }
};
