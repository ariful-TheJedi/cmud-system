<?php

use App\Models\Academy\CourseStage;
use App\Models\Security\User;
use App\Modules\Academy\Services\Enrollments\StudentEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('searches the selected batch roster by student code or student name', function (): void {
    $fixture = academyWorkflowFixture();
    $stage = CourseStage::query()->create([
        'course_id' => $fixture['course']->id,
        'code' => 'EXAM-'.$fixture['suffix'],
        'name' => 'Final Exam',
        'sequence_number' => 1,
        'duration_months' => 12,
        'is_active' => true,
        'is_mandatory' => true,
        'requires_exam' => true,
    ]);
    app(StudentEnrollmentService::class)->enroll($fixture['student'], [
        'course_id' => $fixture['course']->id,
        'batch_id' => $fixture['batch']->id,
        'enrollment_date' => '2026-11-20',
    ]);
    $user = User::factory()->create(['branch_id' => $fixture['branch']->id, 'is_super_admin' => false]);

    $this->actingAs($user)->getJson('/api/v1/exam-control/students?batch_id='.$fixture['batch']->id.'&search=Workflow')
        ->assertOk()
        ->assertJsonPath('data.0.student.student_code', $fixture['student']->student_code)
        ->assertJsonPath('data.0.stages.0.stage.name', $stage->name);

    $this->actingAs($user)->getJson('/api/v1/exam-control/students?batch_id='.$fixture['batch']->id.'&search='.$fixture['student']->student_code)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('blocks eligibility and exam creation until course duration completes', function (): void {
    $fixture = academyWorkflowFixture();
    $stage = CourseStage::query()->create([
        'course_id' => $fixture['course']->id,
        'code' => 'DURATION-'.$fixture['suffix'],
        'name' => 'Duration Exam',
        'sequence_number' => 1,
        'duration_months' => 12,
        'is_active' => true,
        'is_mandatory' => true,
        'requires_exam' => true,
    ]);
    $enrollment = app(StudentEnrollmentService::class)->enroll($fixture['student'], [
        'course_id' => $fixture['course']->id,
        'batch_id' => $fixture['batch']->id,
        'enrollment_date' => '2026-11-20',
    ]);
    $stageRecord = $enrollment->stageRecords()->where('course_stage_id', $stage->id)->firstOrFail();
    $user = User::factory()->create(['branch_id' => $fixture['branch']->id, 'is_super_admin' => false]);

    $this->actingAs($user)->getJson('/api/v1/exam-control/students?batch_id='.$fixture['batch']->id)
        ->assertOk()
        ->assertJsonPath('data.0.stages.0.exam_eligibility.eligible', false);

    $this->actingAs($user)->patchJson('/api/v1/exam-control/stages/'.$stageRecord->id.'/status', [
        'status' => 'eligible',
        'exam_name' => 'Final Exam',
        'exam_date' => '2027-12-02',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($stageRecord->examRecords()->count())->toBe(0);
});

it('schedules eligible exams and records submitted papers and published outcomes', function (): void {
    $fixture = academyWorkflowFixture();
    $fixture['course']->update(['duration_months' => 1]);
    $fixture['batch']->update(['starts_on' => now()->subMonths(2)->toDateString()]);
    $stage = CourseStage::query()->create([
        'course_id' => $fixture['course']->id,
        'code' => 'STATUS-'.$fixture['suffix'],
        'name' => 'Status Exam',
        'sequence_number' => 1,
        'duration_months' => 1,
        'is_active' => true,
        'is_mandatory' => true,
        'requires_exam' => true,
    ]);
    $enrollment = app(StudentEnrollmentService::class)->enroll($fixture['student'], [
        'course_id' => $fixture['course']->id,
        'batch_id' => $fixture['batch']->id,
        'enrollment_date' => '2026-01-01',
    ]);
    $stageRecord = $enrollment->stageRecords()->where('course_stage_id', $stage->id)->firstOrFail();
    $user = User::factory()->create(['branch_id' => $fixture['branch']->id, 'is_super_admin' => false]);

    $this->actingAs($user)->patchJson('/api/v1/exam-control/stages/'.$stageRecord->id.'/status', [
        'status' => 'eligible',
        'exam_name' => 'Final Exam',
        'exam_date' => now()->toDateString(),
        'total_marks' => 100,
    ])->assertOk()->assertJsonPath('data.exam.attempt_status', 'scheduled');

    $this->actingAs($user)->patchJson('/api/v1/exam-control/stages/'.$stageRecord->id.'/status', ['status' => 'paper_submitted'])
        ->assertOk()->assertJsonPath('data.exam.attempt_status', 'appeared');
    $this->actingAs($user)->patchJson('/api/v1/exam-control/stages/'.$stageRecord->id.'/status', [
        'status' => 'passed',
        'obtained_marks' => 85,
        'grade' => 'A',
    ])->assertOk()->assertJsonPath('data.exam.result_status', 'passed');

    $this->assertDatabaseHas('student_stage_histories', [
        'student_stage_record_id' => $stageRecord->id,
        'event_type' => 'exam_control_status_changed',
    ]);
});

