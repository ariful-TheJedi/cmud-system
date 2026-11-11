<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add all database foreign-key constraints after every foundation table exists.
     */
    public function up(): void
    {
        foreach ($this->relationships() as $sourceTable => $foreignKeys) {
            Schema::table($sourceTable, function (Blueprint $table) use ($sourceTable, $foreignKeys): void {
                foreach ($foreignKeys as [$column, $targetTable, $targetColumn, $deleteAction]) {
                    $constraint = $table->foreign(
                        $column,
                        $this->constraintName($sourceTable, $column, $targetTable, $targetColumn),
                    )->references($targetColumn)->on($targetTable);

                    if ($deleteAction === 'cascade') {
                        $constraint->cascadeOnDelete();
                    } else {
                        $constraint->restrictOnDelete();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->relationships(), true) as $sourceTable => $foreignKeys) {
            $constraintNames = array_map(
                fn (array $foreignKey): string => $this->constraintName(
                    $sourceTable,
                    $foreignKey[0],
                    $foreignKey[1],
                    $foreignKey[2],
                ),
                array_reverse($foreignKeys),
            );

            Schema::table($sourceTable, function (Blueprint $table) use ($constraintNames): void {
                foreach ($constraintNames as $constraintName) {
                    $table->dropForeign($constraintName);
                }
            });
        }
    }

    /**
     * @return array<string, list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function relationships(): array
    {
        return [
            'branches' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
            ],
            'departments' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
            ],
            'rooms' => [
                ['branch_id', 'branches', 'id', 'restrict'],
            ],
            'staff' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
            ],
            'staff_assignments' => [
                ['staff_id', 'staff', 'id', 'restrict'],
                ['branch_id', 'branches', 'id', 'restrict'],
                ['department_id', 'departments', 'id', 'restrict'],
            ],
            'asset_categories' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
            ],
            'assets' => [
                ['asset_category_id', 'asset_categories', 'id', 'restrict'],
                ['branch_id', 'branches', 'id', 'restrict'],
                ['room_id', 'rooms', 'id', 'restrict'],
            ],
            'stocks' => [
                ['branch_id', 'branches', 'id', 'restrict'],
            ],
            'stock_movements' => [
                ['stock_id', 'stocks', 'id', 'restrict'],
                ['stock_price_version_id', 'stock_price_versions', 'id', 'restrict'],
            ],
            'stock_price_versions' => [
                ['stock_id', 'stocks', 'id', 'restrict'],
                ['approved_by', 'users', 'id', 'restrict'],
            ],
            'students' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
                ['admission_branch_id', 'branches', 'id', 'restrict'],
                ['medical_college_id', 'medical_colleges', 'id', 'restrict'],
            ],
            'courses' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
            ],
            'course_stages' => [
                ['course_id', 'courses', 'id', 'restrict'],
            ],
            'batches' => [
                ['course_id', 'courses', 'id', 'restrict'],
                ['branch_id', 'branches', 'id', 'restrict'],
            ],
            'enrollments' => [
                ['student_id', 'students', 'id', 'restrict'],
                ['course_id', 'courses', 'id', 'restrict'],
                ['branch_id', 'branches', 'id', 'restrict'],
                ['batch_id', 'batches', 'id', 'restrict'],
            ],
            'student_stage_records' => [
                ['enrollment_id', 'enrollments', 'id', 'restrict'],
                ['course_stage_id', 'course_stages', 'id', 'restrict'],
                ['batch_id', 'batches', 'id', 'restrict'],
            ],
            'exam_records' => [
                ['student_stage_record_id', 'student_stage_records', 'id', 'restrict'],
            ],
            'certificate_templates' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
                ['course_id', 'courses', 'id', 'restrict'],
            ],
            'certificates' => [
                ['enrollment_id', 'enrollments', 'id', 'restrict'],
                ['certificate_template_id', 'certificate_templates', 'id', 'restrict'],
            ],
            'communication_groups' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
                ['branch_id', 'branches', 'id', 'restrict'],
                ['course_id', 'courses', 'id', 'restrict'],
                ['batch_id', 'batches', 'id', 'restrict'],
            ],
            'student_group_memberships' => [
                ['student_id', 'students', 'id', 'restrict'],
                ['communication_group_id', 'communication_groups', 'id', 'restrict'],
            ],
            'teachers' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
                ['staff_id', 'staff', 'id', 'restrict'],
            ],
            'course_teacher' => [
                ['course_id', 'courses', 'id', 'restrict'],
                ['teacher_id', 'teachers', 'id', 'restrict'],
            ],
            'branch_teacher' => [
                ['branch_id', 'branches', 'id', 'cascade'],
                ['teacher_id', 'teachers', 'id', 'cascade'],
            ],
            'course_price_versions' => [
                ['course_id', 'courses', 'id', 'restrict'],
                ['branch_id', 'branches', 'id', 'restrict'],
                ['approved_by', 'users', 'id', 'restrict'],
            ],
            'course_subject' => [
                ['course_id', 'courses', 'id', 'cascade'],
                ['subject_id', 'subjects', 'id', 'cascade'],
            ],
            'student_tuitions' => [
                ['enrollment_id', 'enrollments', 'id', 'restrict'],
                ['course_price_version_id', 'course_price_versions', 'id', 'restrict'],
                ['waiver_approved_by', 'users', 'id', 'restrict'],
            ],
            'student_tuition_payments' => [
                ['student_tuition_id', 'student_tuitions', 'id', 'restrict'],
                ['payment_method_id', 'payment_methods', 'id', 'restrict'],
                ['financial_account_id', 'financial_accounts', 'id', 'restrict'],
                ['verified_by', 'users', 'id', 'restrict'],
                ['recorded_by', 'users', 'id', 'restrict'],
            ],
            'student_expense_types' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
            ],
            'student_expense_price_versions' => [
                ['student_expense_type_id', 'student_expense_types', 'id', 'restrict'],
                ['branch_id', 'branches', 'id', 'restrict'],
                ['course_id', 'courses', 'id', 'restrict'],
                ['course_stage_id', 'course_stages', 'id', 'restrict'],
                ['approved_by', 'users', 'id', 'restrict'],
            ],
            'student_expenses' => [
                ['student_id', 'students', 'id', 'restrict'],
                ['enrollment_id', 'enrollments', 'id', 'restrict'],
                ['student_stage_record_id', 'student_stage_records', 'id', 'restrict'],
                ['branch_id', 'branches', 'id', 'restrict'],
                ['student_expense_type_id', 'student_expense_types', 'id', 'restrict'],
                ['student_expense_price_version_id', 'student_expense_price_versions', 'id', 'restrict'],
                ['waiver_approved_by', 'users', 'id', 'restrict'],
                ['recorded_by', 'users', 'id', 'restrict'],
            ],
            'student_expense_payments' => [
                ['student_expense_id', 'student_expenses', 'id', 'restrict'],
                ['payment_method_id', 'payment_methods', 'id', 'restrict'],
                ['financial_account_id', 'financial_accounts', 'id', 'restrict'],
                ['verified_by', 'users', 'id', 'restrict'],
                ['recorded_by', 'users', 'id', 'restrict'],
            ],
            'institute_expense_categories' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
            ],
            'institute_expense_types' => [
                ['institute_expense_category_id', 'institute_expense_categories', 'id', 'restrict'],
            ],
            'institute_expense_price_versions' => [
                ['institute_expense_type_id', 'institute_expense_types', 'id', 'restrict'],
                ['branch_id', 'branches', 'id', 'restrict'],
                ['approved_by', 'users', 'id', 'restrict'],
            ],
            'institute_expenses' => [
                ['branch_id', 'branches', 'id', 'restrict'],
                ['department_id', 'departments', 'id', 'restrict'],
                ['institute_expense_type_id', 'institute_expense_types', 'id', 'restrict'],
                ['institute_expense_price_version_id', 'institute_expense_price_versions', 'id', 'restrict'],
                ['staff_id', 'staff', 'id', 'restrict'],
                ['teacher_id', 'teachers', 'id', 'restrict'],
                ['asset_id', 'assets', 'id', 'restrict'],
                ['approved_by', 'users', 'id', 'restrict'],
                ['recorded_by', 'users', 'id', 'restrict'],
            ],
            'institute_expense_payments' => [
                ['institute_expense_id', 'institute_expenses', 'id', 'restrict'],
                ['payment_method_id', 'payment_methods', 'id', 'restrict'],
                ['financial_account_id', 'financial_accounts', 'id', 'restrict'],
                ['verified_by', 'users', 'id', 'restrict'],
                ['recorded_by', 'users', 'id', 'restrict'],
            ],
            'payment_methods' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
            ],
            'financial_accounts' => [
                ['institute_id', 'institutes', 'id', 'restrict'],
                ['branch_id', 'branches', 'id', 'restrict'],
            ],
            'account_transfers' => [
                ['from_financial_account_id', 'financial_accounts', 'id', 'restrict'],
                ['to_financial_account_id', 'financial_accounts', 'id', 'restrict'],
                ['approved_by', 'users', 'id', 'restrict'],
                ['confirmed_by', 'users', 'id', 'restrict'],
                ['recorded_by', 'users', 'id', 'restrict'],
            ],
            'institute_income_bill' => [
                ['approved_by', 'users', 'id', 'restrict'],
                ['recorded_by', 'users', 'id', 'restrict'],
            ],
            'institute_other_income_bill_payment' => [
                ['institute_income_bill_id', 'institute_income_bill', 'id', 'restrict'],
                ['payment_method_id', 'payment_methods', 'id', 'restrict'],
                ['financial_account_id', 'financial_accounts', 'id', 'restrict'],
                ['received_by', 'users', 'id', 'restrict'],
                ['recorded_by', 'users', 'id', 'restrict'],
                ['verified_by', 'users', 'id', 'restrict'],
            ],
            'users' => [
                ['permission_group_id', 'permission_groups', 'id', 'restrict'],
            ],
            'permissions' => [
                ['permission_group_id', 'permission_groups', 'id', 'restrict'],
            ],
            'sessions' => [
                ['user_id', 'users', 'id', 'cascade'],
            ],
            'password_reset_tokens' => [
                ['email', 'users', 'email', 'cascade'],
            ],
            'audit_logs' => [
                ['actor_user_id', 'users', 'id', 'restrict'],
            ],
        ];
    }

    private function constraintName(string $sourceTable, string $column, string $targetTable, string $targetColumn): string
    {
        $hash = substr(sha1("{$sourceTable}.{$column}->{$targetTable}.{$targetColumn}"), 0, 8);

        return 'fk_' . substr($sourceTable, 0, 20) . '_' . substr($column, 0, 18) . '_' . $hash;
    }
};
