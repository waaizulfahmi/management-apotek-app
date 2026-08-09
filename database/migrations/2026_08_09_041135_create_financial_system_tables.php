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
        // 1. Chart of Accounts (COA)
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. 1110, 1210, 2100, 4100, 5100, 6100
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'cogs', 'expense']);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Kas & Bank Accounts
        Schema::create('cash_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique();
            $table->string('name');
            $table->enum('type', ['cash', 'bank', 'qris', 'ewallet']);
            $table->string('account_number')->nullable();
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->foreignId('coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Jurnal Keuangan (Header & Lines)
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number')->unique();
            $table->date('transaction_date');
            $table->string('reference_number')->nullable(); // Invoice/PO/Expense Ref
            $table->text('notes')->nullable();
            $table->foreignId('posted_by')->constrained('users');
            $table->enum('status', ['posted', 'reversed'])->default('posted');
            $table->timestamps();
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->onDelete('cascade');
            $table->foreignId('coa_id')->constrained('chart_of_accounts');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();
        });

        // 4. Hutang Supplier (Accounts Payable & Payments)
        Schema::create('accounts_payables', function (Blueprint $table) {
            $table->id();
            $table->string('payable_number')->unique();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('invoice_number');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2);
            $table->date('due_date');
            $table->enum('status', ['unpaid', 'partial', 'paid', 'overdue'])->default('unpaid');
            $table->timestamps();
        });

        Schema::create('payable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounts_payable_id')->constrained('accounts_payables')->onDelete('cascade');
            $table->string('payment_number')->unique();
            $table->foreignId('cash_bank_account_id')->constrained('cash_bank_accounts');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method')->default('transfer');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // 5. Piutang Customer (Accounts Receivable & Payments)
        Schema::create('accounts_receivables', function (Blueprint $table) {
            $table->id();
            $table->string('receivable_number')->unique();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('invoice_number');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2);
            $table->date('due_date');
            $table->enum('status', ['unpaid', 'partial', 'paid', 'overdue'])->default('unpaid');
            $table->timestamps();
        });

        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounts_receivable_id')->constrained('accounts_receivables')->onDelete('cascade');
            $table->string('payment_number')->unique();
            $table->foreignId('cash_bank_account_id')->constrained('cash_bank_accounts');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // 6. Cashier Shift & Daily Closing
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_id')->constrained('users');
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('expected_cash', 15, 2)->default(0);
            $table->decimal('actual_cash', 15, 2)->default(0);
            $table->decimal('difference', 15, 2)->default(0);
            $table->text('difference_reason')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        // 7. Budgets & Reconciliation
        Schema::create('financial_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coa_id')->constrained('chart_of_accounts');
            $table->string('month_year'); // e.g. 2026-08
            $table->decimal('budget_amount', 15, 2);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_budgets');
        Schema::dropIfExists('cashier_shifts');
        Schema::dropIfExists('receivable_payments');
        Schema::dropIfExists('accounts_receivables');
        Schema::dropIfExists('payable_payments');
        Schema::dropIfExists('accounts_payables');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('cash_bank_accounts');
        Schema::dropIfExists('chart_of_accounts');
    }
};
