<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Convert course modules to one-course ownership and remove course-level teacher links. */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
        });

        // Scope any formerly shared module to one course per row. Copy its teaching assignments
        // along with each extra module so the normalization does not discard allocation data.
        $assignments = DB::table('course_subject')
            ->orderBy('subject_id')
            ->orderBy('course_id')
            ->get()
            ->groupBy('subject_id');

        foreach ($assignments as $subjectId => $courseAssignments) {
            $subject = (array) DB::table('subjects')->where('id', $subjectId)->first();

            foreach ($courseAssignments->values() as $index => $assignment) {
                if ($index === 0) {
                    DB::table('subjects')->where('id', $subjectId)->update(['course_id' => $assignment->course_id]);
                    continue;
                }

                $module = $subject;
                unset($module['id']);
                $module['course_id'] = $assignment->course_id;
                $module['code'] = $this->uniqueCourseScopedCode($subject['code'], (int) $assignment->course_id);
                $moduleId = DB::table('subjects')->insertGetId($module);

                foreach (DB::table('subject_teacher')->where('subject_id', $subjectId)->get() as $teachingAssignment) {
                    $pivot = (array) $teachingAssignment;
                    unset($pivot['id']);
                    $pivot['subject_id'] = $moduleId;
                    DB::table('subject_teacher')->insert($pivot);
                }
            }
        }

        // Translate legacy course-level allocations to that course's modules before removing
        // course_teacher. Existing explicit module assignments retain their metadata.
        foreach (DB::table('course_teacher')->orderBy('id')->get() as $courseTeacher) {
            $moduleIds = DB::table('subjects')
                ->where('course_id', $courseTeacher->course_id)
                ->pluck('id');

            foreach ($moduleIds as $moduleId) {
                $alreadyAssigned = DB::table('subject_teacher')
                    ->where('subject_id', $moduleId)
                    ->where('teacher_id', $courseTeacher->teacher_id)
                    ->exists();

                if (! $alreadyAssigned) {
                    DB::table('subject_teacher')->insert([
                        'subject_id' => $moduleId,
                        'teacher_id' => $courseTeacher->teacher_id,
                        'teaching_role' => $courseTeacher->teaching_role,
                        'is_active' => $courseTeacher->is_active,
                        'notes' => $courseTeacher->notes,
                        'created_at' => $courseTeacher->created_at,
                        'updated_at' => $courseTeacher->updated_at,
                    ]);
                }
            }
        }

        // Teachers are assigned to subjects through subject_teacher from this point forward.
        Schema::dropIfExists('course_teacher');
        Schema::dropIfExists('course_subject');
    }

    /** Add a deterministic course suffix while keeping the subject code within its column limit. */
    private function uniqueCourseScopedCode(string $code, int $courseId): string
    {
        $suffix = '-COURSE-'.$courseId;
        $candidate = substr($code, 0, 255 - strlen($suffix)).$suffix;
        $collision = 1;

        while (DB::table('subjects')->where('code', $candidate)->exists()) {
            $collisionSuffix = $suffix.'-'.$collision++;
            $candidate = substr($code, 0, 255 - strlen($collisionSuffix)).$collisionSuffix;
        }

        return $candidate;
    }

    /** Restore the former catalog pivots; teacher assignments cannot be reconstructed. */
    public function down(): void
    {
        Schema::create('course_subject', function (Blueprint $table): void {
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['course_id', 'subject_id']);
        });

        DB::table('subjects')->whereNotNull('course_id')->orderBy('id')->each(function (object $subject): void {
            DB::table('course_subject')->insert([
                'course_id' => $subject->course_id,
                'subject_id' => $subject->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::create('course_teacher', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->string('teaching_role')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_id');
        });
    }
};
