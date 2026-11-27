<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Move the course assignment off the module row into a reusable many-to-many pivot. */
    public function up(): void
    {
        Schema::create('course_subject', function (Blueprint $table): void {
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['course_id', 'subject_id']);
            $table->index('subject_id');
        });

        // Preserve each existing subject-to-course assignment before removing subjects.course_id.
        DB::table('subjects')->whereNotNull('course_id')->orderBy('id')->get(['id', 'course_id'])
            ->each(function (object $subject): void {
                DB::table('course_subject')->insertOrIgnore([
                    'course_id' => $subject->course_id,
                    'subject_id' => $subject->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_id');
        });
    }

    /** Restore the former single-course column when rolling back this relationship change. */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable()->constrained('courses')->restrictOnDelete();
        });

        // The old foreign key could not represent shared modules, so rollback keeps one course link.
        DB::table('course_subject')->orderBy('subject_id')->orderBy('course_id')
            ->get(['subject_id', 'course_id'])
            ->each(function (object $assignment): void {
                DB::table('subjects')->where('id', $assignment->subject_id)->whereNull('course_id')->update([
                    'course_id' => $assignment->course_id,
                ]);
            });

        Schema::dropIfExists('course_subject');
    }
};
