<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Add the custom-price Other type/item so the special bill flow is selectable. */
    public function up(): void
    {
        foreach (DB::table('institutes')->pluck('id') as $instituteId) {
            $typeId = DB::table('institute_expense_types')
                ->where('institute_id', $instituteId)
                ->where('code', 'OTHER')
                ->value('id');

            if ($typeId === null) {
                $typeId = DB::table('institute_expense_types')->insertGetId([
                    'institute_id' => $instituteId,
                    'code' => 'OTHER',
                    'name' => 'Other',
                    'description' => 'Custom institute bill with a user-entered name and amount.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $otherCategoryExists = DB::table('institute_expense_categories')
                ->where('institute_expense_type_id', $typeId)
                ->where('code', 'OTHER')
                ->exists();

            if (! $otherCategoryExists) {
                DB::table('institute_expense_categories')->insert([
                    'institute_expense_type_id' => $typeId,
                    'code' => 'OTHER',
                    'name' => 'Other',
                    'description' => 'Custom-named bill category with a user-entered amount.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** Keep this reference option on rollback because bills may already use it. */
    public function down(): void
    {
        // This data is intentionally retained so existing Other bills keep valid references.
    }
};
