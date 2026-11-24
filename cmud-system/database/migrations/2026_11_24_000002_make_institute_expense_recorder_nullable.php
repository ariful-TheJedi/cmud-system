<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Allow bill creation until API authentication middleware supplies a recorder. */
    public function up(): void
    {
        Schema::table('institute_expenses', function (Blueprint $table): void {
            $table->foreignId('recorded_by')->nullable()->change();
        });
    }

    /** Restore the required expense recorder after all bills have a recorded_by value. */
    public function down(): void
    {
        Schema::table('institute_expenses', function (Blueprint $table): void {
            $table->foreignId('recorded_by')->nullable(false)->change();
        });
    }
};
