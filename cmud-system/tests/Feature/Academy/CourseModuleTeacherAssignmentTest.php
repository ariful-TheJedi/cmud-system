<?php

namespace Tests\Feature\Academy;

use App\Models\Academy\Course;
use App\Models\Academy\Subject;
use App\Models\Institute\Institute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Covers independent modules and their course, stage, and teacher pivot assignments. */
class CourseModuleTeacherAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_independent_module_can_be_reused_by_courses_and_assigned_to_a_teacher(): void
    {
        $institute = $this->createInstitute();
        $firstCourse = $this->createCourse($institute, 'MODULE-COURSE-ONE');
        $secondCourse = $this->createCourse($institute, 'MODULE-COURSE-TWO');

        $created = $this->postJson('/api/v1/academy-catalog/subjects', [
            'code' => 'SHARED-MODULE',
            'name' => 'Shared Module',
        ])->assertCreated()->assertJsonPath('data.name', 'Shared Module');
        $subjectId = (int) $created->json('data.id');
        $subject = Subject::query()->findOrFail($subjectId);
        $this->assertCount(0, $subject->courses);

        foreach ([$firstCourse, $secondCourse] as $course) {
            $this->putJson('/api/v1/academy-catalog/courses/'.$course->id, [
                'subject_ids' => [$subjectId],
            ])->assertOk();
        }

        $this->assertDatabaseCount('course_subject', 2);
        $this->getJson('/api/v1/academy-catalog/subjects/'.$subjectId)
            ->assertOk()
            ->assertJsonCount(2, 'data.courses');
        $this->getJson('/api/v1/academy-catalog/subjects')
            ->assertOk()
            ->assertJsonPath('data.0.id', $subjectId)
            ->assertJsonCount(2, 'data.0.courses');
        $this->getJson('/api/v1/academy-catalog/subjects?search=MODULE-COURSE-ONE')
            ->assertOk()
            ->assertJsonPath('data.0.id', $subjectId);
        $this->getJson('/api/v1/academy-catalog/subjects?search=missing-module')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->putJson('/api/v1/academy-catalog/subjects/'.$subjectId, [
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->postJson('/api/v1/academy-catalog/teachers', [
            'institute_id' => $institute->id,
            'teacher_code' => 'SHARED-MODULE-TEACHER',
            'full_name' => 'Module Instructor',
            'phone' => '01700001002',
            'subject_assignments' => [[
                'subject_id' => $subjectId,
                'teaching_role' => 'Lead instructor',
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('subject_teacher', [
            'subject_id' => $subjectId,
            'teaching_role' => 'Lead instructor',
            'is_active' => true,
        ]);
    }

    public function test_independent_module_can_be_assigned_to_stages_in_different_courses(): void
    {
        $institute = $this->createInstitute();
        $firstCourse = $this->createCourse($institute, 'STAGE-COURSE-ONE');
        $secondCourse = $this->createCourse($institute, 'STAGE-COURSE-TWO');
        $subjectId = (int) $this->postJson('/api/v1/academy-catalog/subjects', [
            'code' => 'REUSED-STAGE-MODULE',
            'name' => 'Reusable Stage Module',
        ])->assertCreated()->json('data.id');

        $stageIds = [];
        foreach ([[$firstCourse, 'FIRST-STAGE'], [$secondCourse, 'SECOND-STAGE']] as [$course, $code]) {
            $stage = $this->postJson('/api/v1/academy-catalog/courses/'.$course->id.'/stages', [
                'code' => $code,
                'name' => 'Semester One',
                'sequence_number' => 1,
                'duration_months' => 3,
                'subject_ids' => [$subjectId],
            ])->assertCreated()->assertJsonPath('data.subjects.0.id', $subjectId);
            $stageIds[] = (int) $stage->json('data.id');
        }

        $this->assertDatabaseCount('course_stage_subject', 2);
        $this->assertSame(2, Subject::query()->findOrFail($subjectId)->courseStages()->count());
        $this->assertNotSame($stageIds[0], $stageIds[1]);
    }

    private function createInstitute(): Institute
    {
        return Institute::query()->create([
            'code' => 'MODULE-TEST-INSTITUTE',
            'name' => 'Module Test Institute',
            'phone' => '01700001001',
            'email' => 'module-test@example.test',
            'address' => 'Test address',
            'is_active' => true,
        ]);
    }

    private function createCourse(Institute $institute, string $code): Course
    {
        return Course::query()->create([
            'institute_id' => $institute->id,
            'code' => $code,
            'name' => $code,
            'duration_months' => 6,
            'completion_period_months' => 9,
            'course_mode' => 'offline',
            'is_active' => true,
        ]);
    }
}
