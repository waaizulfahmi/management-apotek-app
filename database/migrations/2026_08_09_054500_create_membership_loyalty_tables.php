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
        // 1. Membership Tiers
        Schema::create('membership_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->decimal('min_spending', 15, 2)->default(0);
            $table->decimal('max_spending', 15, 2)->nullable();
            $table->decimal('point_multiplier', 4, 2)->default(1.00);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->string('badge_color', 20)->default('#6b7280');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Membership Tier Histories
        Schema::create('membership_tier_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('previous_tier_id')->nullable()->constrained('membership_tiers')->nullOnDelete();
            $table->foreignId('new_tier_id')->constrained('membership_tiers');
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 3. Point Transactions
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->enum('transaction_type', [
                'PURCHASE',
                'WELCOME_BONUS',
                'BIRTHDAY_BONUS',
                'REFERRAL_BONUS',
                'CAMPAIGN_BONUS',
                'MANUAL_ADJUSTMENT_IN',
                'MANUAL_ADJUSTMENT_OUT',
                'REDEEM',
                'EXPIRED',
                'REFUND_REVERSAL',
                'RETURN_REVERSAL'
            ]);
            $table->string('reference_type', 50)->nullable(); // Sale, RewardRedemption, Campaign, Admin
            $table->string('reference_id', 100)->nullable(); // Invoice Number / Code
            $table->integer('points_in')->default(0);
            $table->integer('points_out')->default(0);
            $table->integer('balance_before')->default(0);
            $table->integer('balance_after')->default(0);
            $table->date('expired_at')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        // 4. Point Lots (FIFO Expiration Tracking)
        Schema::create('point_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('point_transaction_id')->nullable()->constrained('point_transactions')->onDelete('cascade');
            $table->integer('original_points');
            $table->integer('remaining_points');
            $table->date('earned_at');
            $table->date('expired_at')->nullable();
            $table->enum('status', ['ACTIVE', 'FULLY_REDEEMED', 'EXPIRED'])->default('ACTIVE');
            $table->timestamps();

            $table->index(['customer_id', 'status', 'expired_at']);
        });

        // 5. Rewards
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->integer('required_points');
            $table->enum('reward_type', [
                'DISCOUNT_AMOUNT',
                'DISCOUNT_PERCENT',
                'VOUCHER',
                'FREE_PRODUCT',
                'CASHBACK',
                'SPECIAL_GIFT'
            ])->default('DISCOUNT_AMOUNT');
            $table->decimal('reward_value', 15, 2)->default(0);
            $table->integer('stock')->default(999);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 6. Reward Redemptions
        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('reward_id')->constrained('rewards')->onDelete('cascade');
            $table->string('redemption_code', 50)->unique();
            $table->integer('points_used');
            $table->enum('status', ['AVAILABLE', 'USED', 'EXPIRED', 'CANCELLED'])->default('AVAILABLE');
            $table->timestamp('redeemed_at')->useCurrent();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });

        // 7. Vouchers
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->enum('type', ['DISCOUNT_PERCENT', 'DISCOUNT_AMOUNT', 'FREE_PRODUCT', 'CASHBACK'])->default('DISCOUNT_AMOUNT');
            $table->decimal('value', 15, 2);
            $table->decimal('min_purchase', 15, 2)->default(0);
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('usage_limit_total')->nullable();
            $table->integer('usage_limit_per_member')->default(1);
            $table->string('applicable_tier', 50)->default('ALL'); // ALL, BRONZE, SILVER, GOLD, PLATINUM
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 8. Voucher Usages
        Schema::create('voucher_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('voucher_id')->constrained('vouchers')->onDelete('cascade');
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->decimal('discount_amount', 15, 2);
            $table->timestamp('used_at')->useCurrent();
            $table->timestamps();
        });

        // 9. Membership Rules
        Schema::create('membership_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key_name', 50)->unique();
            $table->string('display_name', 100);
            $table->text('value');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 10. Membership Campaigns
        Schema::create('membership_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->decimal('multiplier', 4, 2)->default(2.00);
            $table->integer('bonus_points')->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('target_tier', 50)->default('ALL');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 11. Member Referrals
        Schema::create('member_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('referred_customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('referral_code', 50);
            $table->integer('referrer_bonus_points')->default(100);
            $table->integer('referred_bonus_points')->default(50);
            $table->enum('status', ['PENDING', 'COMPLETED'])->default('COMPLETED');
            $table->timestamps();
        });

        // 12. Member Notifications
        Schema::create('member_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('title', 100);
            $table->text('message');
            $table->enum('channel', ['IN_APP', 'EMAIL', 'WHATSAPP', 'SMS'])->default('IN_APP');
            $table->enum('status', ['PENDING', 'SENT', 'FAILED', 'READ'])->default('SENT');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();
        });

        // 13. Membership Audit Logs
        Schema::create('membership_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('module', 50)->default('MEMBERSHIP');
            $table->string('record_id', 100)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_audit_logs');
        Schema::dropIfExists('member_notifications');
        Schema::dropIfExists('member_referrals');
        Schema::dropIfExists('membership_campaigns');
        Schema::dropIfExists('membership_rules');
        Schema::dropIfExists('voucher_usages');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('point_lots');
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('membership_tier_histories');
        Schema::dropIfExists('membership_tiers');
    }
};
