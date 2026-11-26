<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Student billing receipts do not require a separate ledger account. */
    public function up(): void
    {
        foreach (['student_tuition_payments', 'student_other_payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('financial_account_id')->nullable()->change();
            });
        }
    }

    /** Restore the required link only if every receipt already has an account. */
    public function down(): void
    {
        foreach (['student_tuition_payments', 'student_other_payments'] as $tableName) {
            if (DB::table($tableName)->whereNull('financial_account_id')->exists()) {
                throw new RuntimeException("Cannot require a ledger account while {$tableName} contains direct receipts.");
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('financial_account_id')->nullable(false)->change();
            });
        }
    }
};
