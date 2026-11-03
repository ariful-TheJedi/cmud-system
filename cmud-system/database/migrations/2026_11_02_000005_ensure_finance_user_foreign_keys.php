<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $financeUserColumns = [
        'student_tuitions' => ['waiver_approved_by'],
        'student_tuition_payments' => ['verified_by', 'recorded_by'],
        'student_expense_price_versions' => ['approved_by'],
        'student_expenses' => ['waiver_approved_by', 'recorded_by'],
        'student_expense_payments' => ['verified_by', 'recorded_by'],
        'institute_expense_price_versions' => ['approved_by'],
        'institute_expenses' => ['approved_by', 'recorded_by'],
        'institute_expense_payments' => ['verified_by', 'recorded_by'],
        'account_transfers' => ['approved_by', 'confirmed_by', 'recorded_by'],
    ];

    private function constraintName(string $table, string $column): string
    {
        return $table.'_'.$column.'_user_fk';
    }

    public function up(): void
    {
        foreach ($this->financeUserColumns as $tableName => $columns) {
            foreach ($columns as $column) {
                $foreignKeys = Schema::getForeignKeys($tableName);
                $existing = collect($foreignKeys)->first(
                    fn (array $foreignKey): bool => in_array($column, $foreignKey['columns'], true)
                );

                if ($existing !== null) {
                    if ($existing['foreign_table'] !== 'users') {
                        throw new RuntimeException("{$tableName}.{$column} already references a table other than users.");
                    }

                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($tableName, $column): void {
                    $table->foreign($column, $this->constraintName($tableName, $column))
                        ->references('id')
                        ->on('users');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->financeUserColumns, true) as $tableName => $columns) {
            foreach ($columns as $column) {
                $constraintName = $this->constraintName($tableName, $column);
                $exists = collect(Schema::getForeignKeys($tableName))
                    ->contains(fn (array $foreignKey): bool => $foreignKey['name'] === $constraintName);

                if ($exists) {
                    Schema::table($tableName, function (Blueprint $table) use ($constraintName): void {
                        $table->dropForeign($constraintName);
                    });
                }
            }
        }
    }
};
