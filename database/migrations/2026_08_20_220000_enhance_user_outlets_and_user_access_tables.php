<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add access_all_outlets to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'access_all_outlets')) {
                $table->boolean('access_all_outlets')->default(false)->after('outlet_id');
            }
        });

        // Set access_all_outlets = true for existing Super Admin / Admin / Owner users
        DB::table('users')
            ->whereIn(DB::raw('LOWER(role)'), ['admin', 'owner', 'superadmin', 'super admin'])
            ->update(['access_all_outlets' => true]);

        // 2. Enhance user_outlets table
        if (Schema::hasTable('user_outlets')) {
            Schema::table('user_outlets', function (Blueprint $table) {
                if (!Schema::hasColumn('user_outlets', 'is_primary')) {
                    $table->boolean('is_primary')->default(false)->after('outlet_id');
                }
                if (!Schema::hasColumn('user_outlets', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // Populate initial user_outlets for existing users
        $mainOutletId = DB::table('outlets')->where('is_main', true)->value('id') 
            ?: DB::table('outlets')->value('id');

        $users = DB::table('users')->get();
        foreach ($users as $user) {
            $userOutletId = $user->outlet_id ?: $mainOutletId;
            if (!$userOutletId) continue;

            // Ensure primary outlet_id is set on users table
            if (!$user->outlet_id) {
                DB::table('users')->where('id', $user->id)->update(['outlet_id' => $userOutletId]);
            }

            $exists = DB::table('user_outlets')
                ->where('user_id', $user->id)
                ->where('outlet_id', $userOutletId)
                ->first();

            if (!$exists) {
                DB::table('user_outlets')->insert([
                    'user_id' => $user->id,
                    'outlet_id' => $userOutletId,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('user_outlets')
                    ->where('id', $exists->id)
                    ->update(['is_primary' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'access_all_outlets')) {
                $table->dropColumn('access_all_outlets');
            }
        });
    }
};
