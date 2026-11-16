<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->json('phone_numbers')->nullable();
            $table->text('present_address')->nullable()->change();
            $table->string('qualification')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('students')->whereNull('present_address')->update(['present_address' => '']);
        DB::table('students')->whereNull('qualification')->update(['qualification' => '']);

        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn('phone_numbers');
            $table->text('present_address')->nullable(false)->change();
            $table->string('qualification')->nullable(false)->change();
        });
    }
};
