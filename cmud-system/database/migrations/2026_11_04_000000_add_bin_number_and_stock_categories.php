<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutes', function (Blueprint $table): void {
            $table->string('bin_number')->nullable()->unique();
        });

        Schema::create('stock_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('stock_price_versions', function (Blueprint $table): void {
            $table->foreignId('stock_category_id')
                ->nullable()
                ->constrained('stock_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_price_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_category_id');
        });

        Schema::dropIfExists('stock_categories');

        Schema::table('institutes', function (Blueprint $table): void {
            $table->dropColumn('bin_number');
        });
    }
};