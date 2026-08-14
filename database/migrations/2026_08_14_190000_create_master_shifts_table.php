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
        if (!Schema::hasTable('master_shifts')) {
            Schema::create('master_shifts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->time('start_time');
                $table->time('end_time');
                $table->integer('grace_minutes')->default(15);
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            // Seed default shifts
            DB::table('master_shifts')->insert([
                [
                    'name' => 'Shift Pagi',
                    'start_time' => '07:00:00',
                    'end_time' => '15:00:00',
                    'grace_minutes' => 15,
                    'is_active' => true,
                    'description' => 'Jadwal kerja shift pagi farmasi',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Shift Siang',
                    'start_time' => '15:00:00',
                    'end_time' => '22:00:00',
                    'grace_minutes' => 15,
                    'is_active' => true,
                    'description' => 'Jadwal kerja shift siang & sore',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Shift Malam',
                    'start_time' => '22:00:00',
                    'end_time' => '07:00:00',
                    'grace_minutes' => 15,
                    'is_active' => true,
                    'description' => 'Jadwal kerja shift malam / terpadu 24 jam',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Shift Full Day',
                    'start_time' => '08:00:00',
                    'end_time' => '20:00:00',
                    'grace_minutes' => 30,
                    'is_active' => true,
                    'description' => 'Jadwal kerja penuh seharian',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        if (Schema::hasTable('cashier_shifts')) {
            Schema::table('cashier_shifts', function (Blueprint $table) {
                if (!Schema::hasColumn('cashier_shifts', 'master_shift_id')) {
                    $table->foreignId('master_shift_id')->nullable()->after('outlet_id')->constrained('master_shifts')->nullOnDelete();
                }
                if (!Schema::hasColumn('cashier_shifts', 'is_out_of_schedule')) {
                    $table->boolean('is_out_of_schedule')->default(false)->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cashier_shifts')) {
            Schema::table('cashier_shifts', function (Blueprint $table) {
                if (Schema::hasColumn('cashier_shifts', 'master_shift_id')) {
                    $table->dropForeign(['master_shift_id']);
                    $table->dropColumn(['master_shift_id', 'is_out_of_schedule']);
                }
            });
        }
        Schema::dropIfExists('master_shifts');
    }
};
