<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Allow institute bills to be created without an optional description. */
    public function up(): void
    {
        Schema::table('institute_expenses', function (Blueprint $table): void {
            $table->text('description')->nullable()->change();
        });
    }

    /** Restore the previous non-null constraint, replacing absent descriptions with an empty string. */
    public function down(): void
    {
        DB::table('institute_expenses')
            ->whereNull('description')
            ->update(['description' => '']);

        Schema::table('institute_expenses', function (Blueprint $table): void {
            $table->text('description')->nullable(false)->change();
        });
    }
};
