<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ensure outlets table exists & enhance it
        if (!Schema::hasTable('outlets')) {
            Schema::create('outlets', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->string('legal_name')->nullable();
                $table->text('address')->nullable();
                $table->string('province')->nullable();
                $table->string('city')->nullable();
                $table->string('district')->nullable();
                $table->string('postal_code', 10)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('email')->nullable();
                $table->string('pic_name')->nullable();
                $table->boolean('is_main')->default(false);
                $table->string('status', 20)->default('ACTIVE'); // ACTIVE, INACTIVE
                $table->boolean('is_active')->default(true);
                $table->softDeletes();
                $table->timestamps();
            });
        } else {
            Schema::table('outlets', function (Blueprint $table) {
                if (!Schema::hasColumn('outlets', 'legal_name')) {
                    $table->string('legal_name')->nullable()->after('name');
                }
                if (!Schema::hasColumn('outlets', 'province')) {
                    $table->string('province')->nullable()->after('address');
                }
                if (!Schema::hasColumn('outlets', 'city')) {
                    $table->string('city')->nullable()->after('province');
                }
                if (!Schema::hasColumn('outlets', 'district')) {
                    $table->string('district')->nullable()->after('city');
                }
                if (!Schema::hasColumn('outlets', 'postal_code')) {
                    $table->string('postal_code', 10)->nullable()->after('district');
                }
                if (!Schema::hasColumn('outlets', 'email')) {
                    $table->string('email')->nullable()->after('phone');
                }
                if (!Schema::hasColumn('outlets', 'pic_name')) {
                    $table->string('pic_name')->nullable()->after('email');
                }
                if (!Schema::hasColumn('outlets', 'is_main')) {
                    $table->boolean('is_main')->default(false)->after('pic_name');
                }
                if (!Schema::hasColumn('outlets', 'status')) {
                    $table->string('status', 20)->default('ACTIVE')->after('is_main');
                }
                if (!Schema::hasColumn('outlets', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // Ensure default Main Outlet exists if empty
        if (DB::table('outlets')->count() === 0) {
            DB::table('outlets')->insert([
                'code' => 'OUT-001',
                'name' => 'Apotek Utama - Pusat',
                'legal_name' => 'PT Apotek Utama Medika',
                'address' => 'Jl. Raya Utama No. 1',
                'phone' => '021-5551234',
                'email' => 'pusat@apotek.com',
                'pic_name' => 'apt. Budi Santoso, S.Farm',
                'is_main' => true,
                'status' => 'ACTIVE',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Ensure at least one outlet is set as is_main
            $hasMain = DB::table('outlets')->where('is_main', true)->exists();
            if (!$hasMain) {
                $firstId = DB::table('outlets')->min('id');
                if ($firstId) {
                    DB::table('outlets')->where('id', $firstId)->update(['is_main' => true]);
                }
            }
        }

        // 2. Ensure user_outlets table exists
        if (!Schema::hasTable('user_outlets')) {
            Schema::create('user_outlets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('outlet_id')->constrained('outlets')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Keep outlets table intact for safety
    }
};
