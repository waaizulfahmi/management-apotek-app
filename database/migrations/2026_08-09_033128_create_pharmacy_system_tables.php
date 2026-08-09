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
        // 1. Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('npwp')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->integer('payment_terms_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Customers / Patients
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('gender', ['L', 'P'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('medical_notes')->nullable();
            $table->text('allergies')->nullable();
            $table->enum('membership_level', ['regular', 'silver', 'gold', 'platinum'])->default('regular');
            $table->integer('points')->default(0);
            $table->decimal('total_spending', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Doctors
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('license_number')->nullable(); // SIP/STR
            $table->string('specialty')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Medicine Categories & Units
        Schema::create('medicine_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('medicine_units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 5. Medicine Batches (FEFO System)
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->string('medicine_id');
            $table->foreign('medicine_id')->references('kode')->on('obats')->onDelete('cascade');
            $table->string('batch_number');
            $table->date('expired_date');
            $table->integer('stock')->default(0);
            $table->decimal('buy_price', 15, 2)->default(0);
            $table->decimal('sell_price', 15, 2)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('received_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 6. Purchases (PO & Goods Received)
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('user_id')->constrained('users');
            $table->date('purchase_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->enum('status', ['draft', 'ordered', 'received', 'cancelled'])->default('draft');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'overdue'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->onDelete('cascade');
            $table->string('medicine_id');
            $table->foreign('medicine_id')->references('kode')->on('obats');
            $table->string('batch_number')->nullable();
            $table->date('expired_date')->nullable();
            $table->integer('quantity_ordered');
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        // 7. Sales & POS
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('cashier_id')->constrained('users');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->date('sale_date');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2);
            $table->decimal('paid_amount', 15, 2);
            $table->decimal('change_amount', 15, 2)->default(0);
            $table->enum('payment_method', ['cash', 'debit', 'credit', 'qris', 'transfer', 'e-wallet', 'mixed']);
            $table->enum('status', ['completed', 'cancelled', 'refunded'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
            $table->string('medicine_id');
            $table->foreign('medicine_id')->references('kode')->on('obats');
            $table->foreignId('batch_id')->nullable()->constrained('medicine_batches');
            $table->integer('quantity');
            $table->decimal('buy_price', 15, 2)->default(0); // For exact COGS & Profit
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        // 8. Prescriptions (Resep Dokter)
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('doctor_id')->nullable()->constrained('doctors');
            $table->foreignId('pharmacist_id')->nullable()->constrained('users');
            $table->date('prescription_date');
            $table->enum('status', ['created', 'verified', 'dispensed', 'completed'])->default('created');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->onDelete('cascade');
            $table->string('medicine_id');
            $table->foreign('medicine_id')->references('kode')->on('obats');
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->string('duration')->nullable();
            $table->integer('quantity');
            $table->text('instructions')->nullable();
            $table->timestamps();
        });

        // 9. Inventory Movements & Stock Opname
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('medicine_id');
            $table->foreign('medicine_id')->references('kode')->on('obats');
            $table->foreignId('batch_id')->nullable()->constrained('medicine_batches');
            $table->enum('type', ['in', 'out', 'adjustment', 'transfer', 'return']);
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->string('reference_number')->nullable(); // Invoice/PO/Opname No
            $table->foreignId('user_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number')->unique();
            $table->date('opname_date');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->onDelete('cascade');
            $table->string('medicine_id');
            $table->foreign('medicine_id')->references('kode')->on('obats');
            $table->foreignId('batch_id')->nullable()->constrained('medicine_batches');
            $table->integer('system_stock');
            $table->integer('physical_stock');
            $table->integer('difference');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        // 10. Financial Records (Expense & Income)
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('expense_categories');
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 11. Audit Logs & System Settings
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // CREATE, UPDATE, DELETE, LOGIN
            $table->string('module');
            $table->string('record_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('medicine_batches');
        Schema::dropIfExists('medicine_units');
        Schema::dropIfExists('medicine_categories');
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('suppliers');
    }
};
