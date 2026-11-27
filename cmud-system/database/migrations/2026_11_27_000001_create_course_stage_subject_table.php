<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the reusable module assignment between a course stage and subject. */
    public function up(): void
    {
        Schema::create('course_stage_subject', function (Blueprint $table): void {
            $table->foreignId('course_stage_id')
                ->constrained('course_stages')
                ->restrictOnDelete();
            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->restrictOnDelete();
            $table->timestamps();

            // A module is assigned at most once to a given stage, but may be reused elsewhere.
            $table->primary(['course_stage_id', 'subject_id']);
            $table->index('subject_id');
        });

        // Remove the single-stage column; stage assignments now belong to the pivot relation.
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_stage_id');
        });
    }

    /** Restore the previous optional single-stage assignment shape. */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->foreignId('course_stage_id')
                ->nullable()
                ->constrained('course_stages')
                ->restrictOnDelete();
        });

        Schema::dropIfExists('course_stage_subject');
    }
};
