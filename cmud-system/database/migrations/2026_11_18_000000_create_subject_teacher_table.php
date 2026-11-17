<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create direct teacher-to-subject assignments with optional teaching metadata. */
    public function up(): void
    {
        Schema::create('subject_teacher', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->string('teaching_role')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['subject_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_teacher');
    }
};
