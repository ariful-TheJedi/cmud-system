<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutes', function (Blueprint $table): void {
            $table->string('site_icon_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('institutes', function (Blueprint $table): void {
            $table->dropColumn('site_icon_path');
        });
    }
};
