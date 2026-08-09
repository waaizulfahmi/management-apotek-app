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
        // 1. Outlets Table
        if (!Schema::hasTable('outlets')) {
            Schema::create('outlets', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->text('address')->nullable();
                $table->string('phone', 30)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. User Outlets Pivot Table
        if (!Schema::hasTable('user_outlets')) {
            Schema::create('user_outlets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('outlet_id')->constrained('outlets')->onDelete('cascade');
                $table->timestamps();
            });
        }

        // 3. Update Users Table with Status & Security fields
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status', 20)->default('ACTIVE')->after('role'); // ACTIVE, INACTIVE, SUSPENDED
            }
            if (!Schema::hasColumn('users', 'outlet_id')) {
                $table->foreignId('outlet_id')->nullable()->after('status')->constrained('outlets')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('outlet_id');
            }
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            }
            if (!Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->integer('failed_login_attempts')->default(0)->after('last_login_ip');
            }
            if (!Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            }
        });

        // 4. Audit Logs Table
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();
                $table->string('action'); // CREATE, EDIT, DELETE, APPROVE, VOID, REFUND, LOGIN, LOCK
                $table->string('module'); // User, Medicine, Stock, POS, PO, Finance, Membership, Settings
                $table->string('ip_address', 45)->nullable();
                $table->json('data_before')->nullable();
                $table->json('data_after')->nullable();
                $table->timestamps();
            });
        }

        // 5. Approval Requests Table
        if (!Schema::hasTable('approval_requests')) {
            Schema::create('approval_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('module');
                $table->string('action_type'); // VOID_SALE, REFUND_SALE, ADJUST_STOCK, ADJUST_POINT, APPROVE_PO, EXPENSE
                $table->text('description');
                $table->json('payload')->nullable();
                $table->string('status', 20)->default('PENDING'); // PENDING, APPROVED, REJECTED
                $table->text('reason')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('audit_logs');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropColumn(['status', 'outlet_id', 'last_login_at', 'last_login_ip', 'failed_login_attempts', 'locked_until']);
        });
        Schema::dropIfExists('user_outlets');
        Schema::dropIfExists('outlets');
    }
};
