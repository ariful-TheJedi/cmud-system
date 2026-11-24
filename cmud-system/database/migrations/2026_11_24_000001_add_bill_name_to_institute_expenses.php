<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add the separate display name used by Institute Billing records. */
    public function up(): void
    {
        Schema::table('institute_expenses', function (Blueprint $table): void {
            $table->string('bill_name')->nullable()->after('expense_number');
        });
    }

    /** Remove the bill display name when rolling back this schema change. */
    public function down(): void
    {
        Schema::table('institute_expenses', function (Blueprint $table): void {
            $table->dropColumn('bill_name');
        });
    }
};
