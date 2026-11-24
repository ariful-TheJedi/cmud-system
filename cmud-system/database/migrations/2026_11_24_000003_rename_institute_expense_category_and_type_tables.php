<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Swap category/type terminology while retaining IDs and historical bill links. */
    public function up(): void
    {
        // The old expense type FK must be replaced because its column changes meaning.
        Schema::table('institute_expenses', function (Blueprint $table): void {
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['institute_expense_type_id']);
            } else {
                $table->dropForeign($this->constraintName(
                    'institute_expenses',
                    'institute_expense_type_id',
                    'institute_expense_types',
                ));
            }
        });

        Schema::rename('institute_expense_categories', 'institute_expense_categories_rename');
        Schema::rename('institute_expense_types', 'institute_expense_categories');
        Schema::rename('institute_expense_categories_rename', 'institute_expense_types');

        Schema::table('institute_expense_categories', function (Blueprint $table): void {
            $table->renameColumn('institute_expense_category_id', 'institute_expense_type_id');
        });
        Schema::table('institute_expense_price_versions', function (Blueprint $table): void {
            $table->renameColumn('institute_expense_type_id', 'institute_expense_category_id');
        });
        Schema::table('institute_expenses', function (Blueprint $table): void {
            $table->renameColumn('institute_expense_type_id', 'institute_expense_category_id');
            $table->foreignId('institute_expense_type_id')->nullable();
        });

        // Preserve the former category as each historical expense's new selectable type.
        DB::table('institute_expenses')->update([
            'institute_expense_type_id' => DB::raw(
                '(SELECT institute_expense_type_id FROM institute_expense_categories WHERE institute_expense_categories.id = institute_expenses.institute_expense_category_id)',
            ),
        ]);

        Schema::table('institute_expenses', function (Blueprint $table): void {
            $table->foreignId('institute_expense_type_id')->nullable(false)->change();
        });

        $this->addForeignKey('institute_expenses', 'institute_expense_type_id', 'institute_expense_types');
    }

    /** Restore original table/column names and historical expense references. */
    public function down(): void
    {
        Schema::table('institute_expenses', function (Blueprint $table): void {
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['institute_expense_type_id']);
            } else {
                $table->dropForeign($this->constraintName(
                    'institute_expenses',
                    'institute_expense_type_id',
                    'institute_expense_types',
                ));
            }
        });

        Schema::table('institute_expenses', function (Blueprint $table): void {
            $table->dropColumn('institute_expense_type_id');
            $table->renameColumn('institute_expense_category_id', 'institute_expense_type_id');
        });
        Schema::table('institute_expense_price_versions', function (Blueprint $table): void {
            $table->renameColumn('institute_expense_category_id', 'institute_expense_type_id');
        });
        Schema::table('institute_expense_categories', function (Blueprint $table): void {
            $table->renameColumn('institute_expense_type_id', 'institute_expense_category_id');
        });

        Schema::rename('institute_expense_types', 'institute_expense_categories_rename');
        Schema::rename('institute_expense_categories', 'institute_expense_types');
        Schema::rename('institute_expense_categories_rename', 'institute_expense_categories');

        // The original constraints move with their tables and columns during the rename.
    }

    /** Add a project-named restrictive foreign key for the new expense type link. */
    private function addForeignKey(string $tableName, string $column, string $targetTable): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($tableName, $column, $targetTable): void {
            $table->foreign($column, $this->constraintName($tableName, $column, $targetTable))
                ->references('id')
                ->on($targetTable)
                ->restrictOnDelete();
        });
    }

    /** Match the deterministic constraint naming used by the foundation migration. */
    private function constraintName(string $tableName, string $column, string $targetTable): string
    {
        $hash = substr(sha1("{$tableName}.{$column}->{$targetTable}.id"), 0, 8);

        return 'fk_'.substr($tableName, 0, 20).'_'.substr($column, 0, 18).'_'.$hash;
    }
};
