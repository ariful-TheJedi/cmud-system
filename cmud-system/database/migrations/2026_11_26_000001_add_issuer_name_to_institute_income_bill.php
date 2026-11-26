<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add a separately printable issuer while preserving recorded_by as the audit user. */
    public function up(): void
    {
        Schema::table('institute_income_bill', function (Blueprint $table): void {
            $table->string('issuer_name')->nullable()->after('university_name');
        });
    }

    /** Remove only the optional display-name field. */
    public function down(): void
    {
        Schema::table('institute_income_bill', function (Blueprint $table): void {
            $table->dropColumn('issuer_name');
        });
    }
};
