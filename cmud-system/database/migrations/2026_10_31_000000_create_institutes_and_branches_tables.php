<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('institutes', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('phone');
            $table->string('email');
            $table->string('website')->nullable();
            $table->text('address');
            $table->string('logo_path')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('code');
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('address');
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('institutes');
    }
};
