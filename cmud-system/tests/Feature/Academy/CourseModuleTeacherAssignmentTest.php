<?php

namespace Tests\Feature\Academy;

use App\Models\Academy\Course;
use App\Models\Academy\Subject;
use App\Models\Institute\Institute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Covers course-owned modules and direct teacher-to-module assignments. */
class CourseModuleTeacherAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_modules_belong_to_one_course_and_can_be_assigned_to_teachers(): void
    {
        $institute = Institute::query()->create([
        'code' => 'MODULE-TEST-INSTITUTE',
        'name' => 'Module Test Institute',
        'phone' => '01700001001',
        'email' => 'module-test@example.test',
        'address' => 'Test address',
        'is_active' => true,
        ]);

        $course = Course::query()->create([
        'institute_id' => $institute->id,
        'code' => 'MODULE-TEST-COURSE',
        'name' => 'Module Test Course',
        'duration_months' => 6,
        'completion_period_months' => 9,
        'course_mode' => 'offline',
        'is_active' => true,
        ]);

    // Create the module through the API to cover the single-course ownership input.
        $subjectResponse = $this->postJson('/api/v1/academy-catalog/subjects', [
        'course_id' => $course->id,
        'code' => 'MODULE-TEST-SUBJECT',
        'name' => 'Module Test Subject',
        ])->assertCreated();

        $subjectId = $subjectResponse->json('data.id');
        $this->assertSame($course->id, Subject::query()->findOrFail($subjectId)->course_id);
        $this->assertCount(1, $course->fresh()->subjects);

        // The edit endpoint accepts the visible status control and persists its boolean value.
        $this->putJson("/api/v1/academy-catalog/subjects/{$subjectId}", [
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('subjects', [
            'id' => $subjectId,
            'is_active' => false,
        ]);

    // Assign a teacher to the module and verify the persisted many-to-many relation.
        $this->postJson('/api/v1/academy-catalog/teachers', [
        'institute_id' => $institute->id,
        'teacher_code' => 'MODULE-TEST-TEACHER',
        'full_name' => 'Test Instructor',
        'phone' => '01700001002',
        'subject_assignments' => [
            ['subject_id' => $subjectId, 'teaching_role' => 'Lead instructor'],
        ],
        ])->assertCreated();

        $this->assertDatabaseHas('subject_teacher', [
        'subject_id' => $subjectId,
        'teaching_role' => 'Lead instructor',
        'is_active' => true,
        ]);

        $this->assertCount(1, Subject::query()->findOrFail($subjectId)->teachers);
    }
}
