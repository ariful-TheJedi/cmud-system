<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the Finance foundation tables.
     *
     * Reference columns are created here without foreign-key constraints.
     * Those constraints will be added in separate relationship migrations.
     */
    public function up(): void
    {
        Schema::create('student_tuitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique();
            $table->foreignId('course_price_version_id')->nullable();
            $table->string('tuition_number')->unique();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('waiver_amount', 12, 2)->default(0);
            $table->text('waiver_reason')->nullable();
            $table->foreignId('waiver_approved_by')->nullable();
            $table->timestamp('waiver_approved_at')->nullable();
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->string('status');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('student_tuition_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_tuition_id');
            $table->string('payment_number')->unique();
            $table->foreignId('payment_method_id');
            $table->foreignId('financial_account_id');
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at');
            $table->string('transaction_reference')->nullable();
            $table->string('payer_phone', 30)->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('status');
            $table->string('verification_method')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable();
            $table->string('verification_reference')->nullable();
            $table->json('verification_response')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('student_expense_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('student_expense_price_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_expense_type_id');
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('course_id')->nullable();
            $table->foreignId('course_stage_id')->nullable();
            $table->unsignedInteger('version_number');
            $table->decimal('amount', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('student_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->foreignId('student_id');
            $table->foreignId('enrollment_id')->nullable();
            $table->foreignId('student_stage_record_id')->nullable();
            $table->foreignId('branch_id');
            $table->foreignId('student_expense_type_id');
            $table->foreignId('student_expense_price_version_id');
            $table->text('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('waiver_amount', 12, 2)->default(0);
            $table->text('waiver_reason')->nullable();
            $table->foreignId('waiver_approved_by')->nullable();
            $table->timestamp('waiver_approved_at')->nullable();
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->string('status');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('student_expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_expense_id');
            $table->string('payment_number')->unique();
            $table->foreignId('payment_method_id');
            $table->foreignId('financial_account_id');
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at');
            $table->string('transaction_reference')->nullable();
            $table->string('payer_phone', 30)->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('status');
            $table->string('verification_method')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable();
            $table->string('verification_reference')->nullable();
            $table->json('verification_response')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('institute_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('institute_expense_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_expense_category_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('institute_expense_price_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_expense_type_id');
            $table->foreignId('branch_id')->nullable();
            $table->unsignedInteger('version_number');
            $table->decimal('amount', 12, 2);
            $table->string('unit_name')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('institute_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('department_id')->nullable();
            $table->foreignId('institute_expense_type_id');
            $table->foreignId('institute_expense_price_version_id')->nullable();
            $table->foreignId('staff_id')->nullable();
            $table->foreignId('teacher_id')->nullable();
            $table->foreignId('asset_id')->nullable();
            $table->string('payee_name');
            $table->string('invoice_number')->nullable();
            $table->text('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_amount', 12, 2);
            $table->date('expense_date');
            $table->date('service_period_from')->nullable();
            $table->date('service_period_to')->nullable();
            $table->date('due_on')->nullable();
            $table->string('bill_file_path')->nullable();
            $table->string('status');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('institute_expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_expense_id');
            $table->string('payment_number')->unique();
            $table->foreignId('payment_method_id');
            $table->foreignId('financial_account_id');
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at');
            $table->string('transaction_reference')->nullable();
            $table->string('recipient_account_reference')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('status');
            $table->string('verification_method')->nullable();
            $table->foreignId('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_reference')->nullable();
            $table->json('verification_response')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('requires_transaction_reference')->default(false);
            $table->boolean('requires_sender_phone')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->foreignId('branch_id')->nullable();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('account_type');
            $table->string('provider_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_branch_name')->nullable();
            $table->string('routing_number')->nullable();
            $table->string('currency_code', 3)->default('BDT');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->timestamp('opening_balance_as_of');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('from_financial_account_id');
            $table->foreignId('to_financial_account_id');
            $table->decimal('amount', 12, 2);
            $table->timestamp('transferred_at');
            $table->string('transaction_reference')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('status');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('institute_income_bill', function (Blueprint $table) {
            $table->id();
            $table->string('university_name');
            $table->string('bill_number')->unique();
            $table->jsonb('bill_items');
            $table->decimal('total_amount', 12, 2);
            $table->date('bill_date');
            $table->date('due_on')->nullable();
            $table->string('status');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('institute_other_income_bill_payment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_income_bill_id');
            $table->string('payment_number')->unique();
            $table->foreignId('payment_method_id');
            $table->foreignId('financial_account_id');
            $table->decimal('amount', 12, 2);
            $table->string('status');
            $table->timestamp('paid_at')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->foreignId('received_by')->nullable();
            $table->foreignId('recorded_by');
            $table->foreignId('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institute_other_income_bill_payment');
        Schema::dropIfExists('institute_income_bill');
        Schema::dropIfExists('account_transfers');
        Schema::dropIfExists('financial_accounts');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('institute_expense_payments');
        Schema::dropIfExists('institute_expenses');
        Schema::dropIfExists('institute_expense_price_versions');
        Schema::dropIfExists('institute_expense_types');
        Schema::dropIfExists('institute_expense_categories');
        Schema::dropIfExists('student_expense_payments');
        Schema::dropIfExists('student_expenses');
        Schema::dropIfExists('student_expense_price_versions');
        Schema::dropIfExists('student_expense_types');
        Schema::dropIfExists('student_tuition_payments');
        Schema::dropIfExists('student_tuitions');
    }
};
