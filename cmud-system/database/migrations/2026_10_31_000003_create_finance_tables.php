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
        Schema::create('student_tuitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enrollment_id')->unique();
            $table->string('tuition_number')->unique();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('waiver_amount', 12, 2)->default(0);
            $table->text('waiver_reason')->nullable();
            $table->unsignedBigInteger('waiver_approved_by')->nullable();
            $table->dateTime('waiver_approved_at')->nullable();
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->string('status');
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('student_tuition_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_tuition_id');
            $table->string('payment_number')->unique();
            $table->unsignedBigInteger('payment_method_id');
            $table->unsignedBigInteger('financial_account_id');
            $table->decimal('amount', 12, 2);
            $table->dateTime('paid_at');
            $table->string('transaction_reference')->nullable();
            $table->string('payer_phone')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('status');
            $table->string('verification_method')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('verification_reference')->nullable();
            $table->json('verification_response')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('student_expense_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('student_expense_price_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_expense_type_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('course_stage_id')->nullable();
            $table->unsignedInteger('version_number');
            $table->decimal('amount', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('student_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('enrollment_id')->nullable();
            $table->unsignedBigInteger('student_stage_record_id')->nullable();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('student_expense_type_id');
            $table->unsignedBigInteger('student_expense_price_version_id');
            $table->text('description');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('waiver_amount', 12, 2)->default(0);
            $table->text('waiver_reason')->nullable();
            $table->unsignedBigInteger('waiver_approved_by')->nullable();
            $table->dateTime('waiver_approved_at')->nullable();
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->string('status');
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('student_expense_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_expense_id');
            $table->string('payment_number')->unique();
            $table->unsignedBigInteger('payment_method_id');
            $table->unsignedBigInteger('financial_account_id');
            $table->decimal('amount', 12, 2);
            $table->dateTime('paid_at');
            $table->string('transaction_reference')->nullable();
            $table->string('payer_phone')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('status');
            $table->string('verification_method')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('verification_reference')->nullable();
            $table->json('verification_response')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('institute_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('institute_expense_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_expense_category_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('institute_expense_price_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_expense_type_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedInteger('version_number');
            $table->decimal('amount', 12, 2);
            $table->string('unit_name')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('institute_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('institute_expense_type_id');
            $table->unsignedBigInteger('institute_expense_price_version_id')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('payee_name');
            $table->string('invoice_number')->nullable();
            $table->text('description');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_amount', 12, 2);
            $table->date('expense_date');
            $table->date('service_period_from')->nullable();
            $table->date('service_period_to')->nullable();
            $table->date('due_on')->nullable();
            $table->string('bill_file_path')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('institute_expense_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_expense_id');
            $table->string('payment_number')->unique();
            $table->unsignedBigInteger('payment_method_id');
            $table->unsignedBigInteger('financial_account_id');
            $table->decimal('amount', 12, 2);
            $table->dateTime('paid_at');
            $table->string('transaction_reference')->nullable();
            $table->string('recipient_account_reference')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('status');
            $table->string('verification_method')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->string('verification_reference')->nullable();
            $table->json('verification_response')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('requires_transaction_reference');
            $table->boolean('requires_sender_phone');
            $table->boolean('is_active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('account_type');
            $table->string('provider_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_branch_name')->nullable();
            $table->string('routing_number')->nullable();
            $table->string('currency_code', 3)->default('BDT');
            $table->decimal('opening_balance', 14, 2);
            $table->dateTime('opening_balance_as_of');
            $table->boolean('is_active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->unsignedBigInteger('from_financial_account_id');
            $table->unsignedBigInteger('to_financial_account_id');
            $table->decimal('amount', 14, 2);
            $table->dateTime('transferred_at');
            $table->string('transaction_reference')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
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
