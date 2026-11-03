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
        if (Schema::hasTable('course_teacher')) {
            return;
        }

        Schema::create('course_teacher', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('teacher_id');
            $table->string('teaching_role')->nullable();
            $table->boolean('is_active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Keep the table if created, so existing association data is never removed.
     */
    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
