<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Finance foreign keys described in relation.txt.
     * Institute, branch, staff, asset, academy, and enrollment links already added by
     * the earlier foundation and Students & Academy migrations are intentionally omitted.
     * Intended user references are included so finance approvals and records are tied to users.
     *
     * @return array<string, array<string, string>>
     */
    private function financeForeignKeys(): array
    {
        return [
            'student_tuitions' => [
                'waiver_approved_by' => 'users',
            ],
            'student_tuition_payments' => [
                'student_tuition_id' => 'student_tuitions',
                'payment_method_id' => 'payment_methods',
                'financial_account_id' => 'financial_accounts',
                'verified_by' => 'users',
                'recorded_by' => 'users',
            ],
            'student_expense_price_versions' => [
                'student_expense_type_id' => 'student_expense_types',
                'approved_by' => 'users',
            ],
            'student_expenses' => [
                'student_expense_type_id' => 'student_expense_types',
                'student_expense_price_version_id' => 'student_expense_price_versions',
                'waiver_approved_by' => 'users',
                'recorded_by' => 'users',
            ],
            'student_expense_payments' => [
                'student_expense_id' => 'student_expenses',
                'payment_method_id' => 'payment_methods',
                'financial_account_id' => 'financial_accounts',
                'verified_by' => 'users',
                'recorded_by' => 'users',
            ],
            'institute_expense_types' => [
                'institute_expense_category_id' => 'institute_expense_categories',
            ],
            'institute_expense_price_versions' => [
                'institute_expense_type_id' => 'institute_expense_types',
                'approved_by' => 'users',
            ],
            'institute_expenses' => [
                'institute_expense_type_id' => 'institute_expense_types',
                'institute_expense_price_version_id' => 'institute_expense_price_versions',
                'approved_by' => 'users',
                'recorded_by' => 'users',
            ],
            'institute_expense_payments' => [
                'institute_expense_id' => 'institute_expenses',
                'payment_method_id' => 'payment_methods',
                'financial_account_id' => 'financial_accounts',
                'verified_by' => 'users',
                'recorded_by' => 'users',
            ],
            'account_transfers' => [
                'from_financial_account_id' => 'financial_accounts',
                'to_financial_account_id' => 'financial_accounts',
                'approved_by' => 'users',
                'confirmed_by' => 'users',
                'recorded_by' => 'users',
            ],
        ];
    }

    /**
     * Keep the one long default constraint name below PostgreSQL's identifier limit.
     */
    private function foreignKeyName(string $tableName, string $column): string
    {
        if ($tableName === 'institute_expense_price_versions' && $column === 'institute_expense_type_id') {
            return 'institute_expense_price_versions_type_id_foreign';
        }

        return $tableName.'_'.$column.'_foreign';
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->financeForeignKeys() as $tableName => $foreignKeys) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $foreignKeys): void {
                foreach ($foreignKeys as $column => $referencedTable) {
                    $table->foreign($column, $this->foreignKeyName($tableName, $column))
                        ->references('id')
                        ->on($referencedTable);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse($this->financeForeignKeys(), true) as $tableName => $foreignKeys) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $foreignKeys): void {
                foreach (array_keys($foreignKeys) as $column) {
                    $table->dropForeign($this->foreignKeyName($tableName, $column));
                }
            });
        }
    }
};
