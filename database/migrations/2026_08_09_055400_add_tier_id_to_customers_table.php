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
        if (!Schema::hasColumn('customers', 'tier_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->foreignId('tier_id')->nullable()->after('membership_level')->constrained('membership_tiers')->nullOnDelete();
                $table->string('referral_code', 50)->nullable()->after('tier_id');
                $table->string('referred_by', 50)->nullable()->after('referral_code');
                $table->string('status', 20)->default('ACTIVE')->after('referred_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('customers', 'tier_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropForeign(['tier_id']);
                $table->dropColumn(['tier_id', 'referral_code', 'referred_by', 'status']);
            });
        }
    }
};
