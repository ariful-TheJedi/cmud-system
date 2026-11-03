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
        $tables = [
            'institutes', 'branches', 'departments', 'rooms', 'staff',
            'staff_assignments', 'asset_categories', 'assets', 'stocks', 'stock_movements',
            'students', 'medical_colleges', 'courses', 'course_stages', 'batches',
            'enrollments', 'student_stage_records', 'exam_records', 'certificate_templates',
            'certificates', 'communication_groups', 'student_group_memberships', 'teachers',
            'course_teacher', 'student_tuitions', 'student_tuition_payments',
            'student_expense_types', 'student_expense_price_versions', 'student_expenses',
            'student_expense_payments', 'institute_expense_categories', 'institute_expense_types',
            'institute_expense_price_versions', 'institute_expenses', 'institute_expense_payments',
            'payment_methods', 'financial_accounts', 'account_transfers', 'users', 'permission_groups', 'permissions',
            'sessions', 'password_reset_tokens', 'audit_logs',
            'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'passkeys',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                $this->addMissingTimestamps($tableName);
            }
        }
    }

    /**
     * Add only the timestamp columns that are missing from an existing table.
     */
    private function addMissingTimestamps(string $tableName): void
    {
        $hasCreatedAt = Schema::hasColumn($tableName, 'created_at');
        $hasUpdatedAt = Schema::hasColumn($tableName, 'updated_at');

        if ($hasCreatedAt && $hasUpdatedAt) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($hasCreatedAt, $hasUpdatedAt) {
            if (! $hasCreatedAt && ! $hasUpdatedAt) {
                $table->timestamps();

                return;
            }

            if (! $hasCreatedAt) {
                $table->timestamp('created_at')->nullable();
            }

            if (! $hasUpdatedAt) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    /**
     * Keep timestamp columns and their data during rollback.
     */
    public function down(): void
    {
        // Intentionally non-destructive: timestamp values are retained.
    }
};
