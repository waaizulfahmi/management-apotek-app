<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create medicine_types table (Master Jenis Obat / Bentuk Sediaan)
        if (!Schema::hasTable('medicine_types')) {
            Schema::create('medicine_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 120)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Update medicine_categories table if columns missing
        if (Schema::hasTable('medicine_categories')) {
            Schema::table('medicine_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('medicine_categories', 'description')) {
                    $table->text('description')->nullable()->after('slug');
                }
                if (!Schema::hasColumn('medicine_categories', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('description');
                }
                if (!Schema::hasColumn('medicine_categories', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('is_active');
                }
                if (!Schema::hasColumn('medicine_categories', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                }
            });
        }

        // 3. Seed default Jenis Obat
        $defaultTypes = [
            'Tablet' => 'Sediaan padat kompak, dibuat secara kempa cetak, berbentuk pipih dengan kedua permukaan rata atau cembung.',
            'Kapsul' => 'Sediaan padat yang terdiri dari obat dalam cangkang keras atau lunak yang dapat larut.',
            'Sirup' => 'Sediaan cair berupa larutan yang mengandung sukrosa atau gula lain dalam kadar tinggi.',
            'Salep' => 'Sediaan setengah padat ditujukan untuk pemakaian topikal pada kulit atau selaput lendir.',
            'Injeksi' => 'Sediaan steril berupa larutan, emulsi atau suspensi untuk disuntikkan.',
            'Kaplet' => 'Bentuk sediaan tablet yang berbentuk lonjong seperti kapsul.',
            'Puyer / Serbuk' => 'Campuran kering obat atau zat kimia yang dihaluskan untuk pemakaian oral/luar.',
            'Tetes (Drop)' => 'Sediaan cair steril atau larutan untuk mata, telinga, atau hidung.',
            'Gel / Krim' => 'Sediaan setengah padat emulsi yang mengandung air dan minyak untuk penggunaan luar.',
            'Suspensi' => 'Sediaan cair yang mengandung partikel padat tidak larut yang terdispersi dalam fase cair.',
            'Infus' => 'Larutan steril dalam jumlah besar yang disuntikkan langsung ke dalam vena.',
        ];

        foreach ($defaultTypes as $name => $desc) {
            $exists = DB::table('medicine_types')->where('name', $name)->exists();
            if (!$exists) {
                DB::table('medicine_types')->insert([
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => $desc,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 4. Seed default Kategori Obat
        $defaultCategories = [
            'Antibiotik' => 'Obat untuk mengobati infeksi bakteri.',
            'Antipiretik' => 'Obat penurun panas / demam.',
            'Analgesik' => 'Obat pereda nyeri dan rasa sakit.',
            'Antihistamin' => 'Obat untuk mengatasi reaksi alergi dan gatal.',
            'Vitamin & Suplemen' => 'Nutrisi pelengkap untuk menjaga imunitas dan metabolisme tubuh.',
            'Antiseptik' => 'Zat pembunuh kuman dan mikroorganisme pada jaringan hidup.',
            'Herbal' => 'Obat tradisional berbasis ekstrak tumbuhan alami.',
            'Flu & Batuk' => 'Obat kombinasi pereda gejala flu, hidung tersumbat, dan batuk.',
            'Antiinflamasi' => 'Obat anti-radang untuk mengatasi pembengkakan dan nyeri sendi.',
            'Antasida' => 'Obat untuk menetralkan asam lambung berlebih.',
        ];

        foreach ($defaultCategories as $name => $desc) {
            $exists = DB::table('medicine_categories')->where('name', $name)->exists();
            if (!$exists) {
                DB::table('medicine_categories')->insert([
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => $desc,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('medicine_categories')->where('name', $name)->update([
                    'description' => $desc,
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicine_types');
    }
};
