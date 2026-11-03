<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign key columns described in the Institute & Branches section of relation.txt.
     *
     * @return array<string, array<string, string>>
     */
    private function foundationForeignKeys(): array
    {
        return [
            'branches' => [
                'institute_id' => 'institutes',
            ],
            'departments' => [
                'institute_id' => 'institutes',
            ],
            'rooms' => [
                'branch_id' => 'branches',
            ],
            'staff' => [
                'institute_id' => 'institutes',
            ],
            'staff_assignments' => [
                'staff_id' => 'staff',
                'branch_id' => 'branches',
                'department_id' => 'departments',
            ],
            'asset_categories' => [
                'institute_id' => 'institutes',
            ],
            'assets' => [
                'asset_category_id' => 'asset_categories',
                'branch_id' => 'branches',
                'room_id' => 'rooms',
            ],
            'stocks' => [
                'branch_id' => 'branches',
            ],
            'stock_movements' => [
                'stock_id' => 'stocks',
            ],
            'students' => [
                'institute_id' => 'institutes',
                'admission_branch_id' => 'branches',
            ],
            'courses' => [
                'institute_id' => 'institutes',
            ],
            'batches' => [
                'branch_id' => 'branches',
            ],
            'enrollments' => [
                'branch_id' => 'branches',
            ],
            'certificate_templates' => [
                'institute_id' => 'institutes',
            ],
            'communication_groups' => [
                'institute_id' => 'institutes',
                'branch_id' => 'branches',
            ],
            'teachers' => [
                'institute_id' => 'institutes',
                'staff_id' => 'staff',
            ],
            'student_expense_types' => [
                'institute_id' => 'institutes',
            ],
            'student_expense_price_versions' => [
                'branch_id' => 'branches',
            ],
            'student_expenses' => [
                'branch_id' => 'branches',
            ],
            'institute_expense_categories' => [
                'institute_id' => 'institutes',
            ],
            'institute_expense_price_versions' => [
                'branch_id' => 'branches',
            ],
            'institute_expenses' => [
                'institute_id' => 'institutes',
                'branch_id' => 'branches',
                'department_id' => 'departments',
                'staff_id' => 'staff',
                'asset_id' => 'assets',
            ],
            'payment_methods' => [
                'institute_id' => 'institutes',
            ],
            'financial_accounts' => [
                'institute_id' => 'institutes',
                'branch_id' => 'branches',
            ],
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->foundationForeignKeys() as $tableName => $foreignKeys) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKeys): void {
                foreach ($foreignKeys as $column => $referencedTable) {
                    $table->foreign($column)
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
        foreach (array_reverse($this->foundationForeignKeys(), true) as $tableName => $foreignKeys) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKeys): void {
                foreach (array_keys($foreignKeys) as $column) {
                    $table->dropForeign([$column]);
                }
            });
        }
    }
};
