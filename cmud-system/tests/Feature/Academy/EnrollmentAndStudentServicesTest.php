<?php

use App\Models\Academy\Batch;
use App\Models\Academy\Certificate;
use App\Models\Academy\CertificateTemplate;
use App\Models\Academy\CommunicationGroup;
use App\Models\Academy\Course;
use App\Models\Academy\CourseStage;
use App\Models\Academy\ExamRecord;
use App\Models\Academy\Student;
use App\Models\Academy\StudentGroupMembership;
use App\Models\Finance\StudentTuition;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use App\Modules\Academy\Services\Enrollments\StudentEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function academyWorkflowFixture(): array
{
    $suffix = Str::upper(Str::random(8));
    $institute = Institute::query()->create([
        'code' => 'WF-INSTITUTE-'.$suffix,
        'name' => 'Workflow Institute',
        'phone' => '01700000001',
        'email' => 'workflow-'.$suffix.'@example.test',
        'address' => 'Workflow institute address',
        'is_active' => true,
    ]);
    $branch = Branch::query()->create([
        'institute_id' => $institute->id,
        'code' => 'WF-BRANCH-'.$suffix,
        'name' => 'Workflow Branch',
        'phone' => '01700000002',
        'address' => 'Workflow branch address',
        'is_active' => true,
    ]);
    $course = Course::query()->create([
        'institute_id' => $institute->id,
        'code' => 'WF-COURSE-'.$suffix,
        'name' => 'Workflow Course',
        'duration_months' => 12,
        'completion_period_months' => 15,
        'is_active' => true,
    ]);
    $batch = Batch::query()->create([
        'course_id' => $course->id,
        'branch_id' => $branch->id,
        'code' => 'WF-BATCH-'.$suffix,
        'name' => 'Workflow Batch',
        'starts_on' => '2026-12-01',
        'expected_ends_on' => '2027-12-01',
        'status' => 'open',
    ]);
    $student = Student::query()->create([
        'institute_id' => $institute->id,
        'admission_branch_id' => $branch->id,
        'student_code' => 'WF-STUDENT-'.$suffix,
        'admission_date' => '2026-11-20',
        'full_name' => 'Workflow Student',
        'phone' => '01712345678',
        'status' => 'active',
    ]);

    return compact('institute', 'branch', 'course', 'batch', 'student', 'suffix');
}

it('creates a not-eligible certificate when an active course template exists', function (): void {
    $fixture = academyWorkflowFixture();
    $template = CertificateTemplate::query()->create([
        'institute_id' => $fixture['institute']->id,
        'course_id' => $fixture['course']->id,
        'name' => 'Workflow Course Certificate',
        'version_number' => 1,
        'template_file_path' => 'certificates/workflow-course.pdf',
        'is_active' => true,
    ]);

    $enrollment = app(StudentEnrollmentService::class)->enroll($fixture['student'], [
        'course_id' => $fixture['course']->id,
        'batch_id' => $fixture['batch']->id,
        'enrollment_date' => '2026-11-20',
    ]);

    $this->assertDatabaseHas('certificates', [
        'enrollment_id' => $enrollment->id,
        'certificate_template_id' => $template->id,
        'recipient_name' => $fixture['student']->full_name,
        'course_title' => $fixture['course']->name,
        'status' => 'not_eligible',
    ]);
});

it('allows enrollment without a certificate when no course template exists', function (): void {
    $fixture = academyWorkflowFixture();
    CertificateTemplate::query()->create([
        'institute_id' => $fixture['institute']->id,
        'course_id' => null,
        'name' => 'Institute-wide Certificate',
        'version_number' => 1,
        'template_file_path' => 'certificates/institute-wide.pdf',
        'is_active' => true,
    ]);

    $enrollment = app(StudentEnrollmentService::class)->enroll($fixture['student'], [
        'course_id' => $fixture['course']->id,
        'batch_id' => $fixture['batch']->id,
        'enrollment_date' => '2026-11-20',
    ]);

    $this->assertSame('enrolled', $enrollment->getRawOriginal('status'));
    $this->assertSame(0, Certificate::query()
        ->where('enrollment_id', $enrollment->id)
        ->count());
});

it('freezes academic course structure after the first enrollment', function (): void {
    $fixture = academyWorkflowFixture();
    $stage = CourseStage::query()->create([
        'course_id' => $fixture['course']->id,
        'code' => 'WF-STAGE-'.$fixture['suffix'],
        'name' => 'First Stage',
        'sequence_number' => 1,
        'duration_months' => 12,
        'is_active' => true,
        'is_mandatory' => true,
    ]);
    app(StudentEnrollmentService::class)->enroll($fixture['student'], [
        'course_id' => $fixture['course']->id,
        'batch_id' => $fixture['batch']->id,
        'enrollment_date' => '2026-11-20',
    ]);

    $courseStagesUrl = '/api/v1/academy-catalog/courses/'.$fixture['course']->id.'/stages';
    $this->putJson($courseStagesUrl.'/'.$stage->id, ['sequence_number' => 2])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stage');
    $this->postJson($courseStagesUrl, [
        'code' => 'WF-STAGE-NEW-'.$fixture['suffix'],
        'name' => 'New Stage',
        'sequence_number' => 2,
        'duration_months' => 6,
        'is_mandatory' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('course_id');
});

it('preserves assignment changes in enrollment history', function (): void {
    $fixture = academyWorkflowFixture();
    $enrollment = app(StudentEnrollmentService::class)->enroll($fixture['student'], [
        'course_id' => $fixture['course']->id,
        'batch_id' => $fixture['batch']->id,
        'enrollment_date' => '2026-11-20',
    ]);
    $newBatch = Batch::query()->create([
        'course_id' => $fixture['course']->id,
        'branch_id' => $fixture['branch']->id,
        'code' => 'WF-BATCH-NEW-'.$fixture['suffix'],
        'name' => 'Workflow New Batch',
        'starts_on' => '2027-01-01',
        'expected_ends_on' => '2028-01-01',
        'status' => 'upcoming',
    ]);

    $this->putJson('/api/v1/enrollments/'.$enrollment->id.'/assignment', [
        'course_id' => $fixture['course']->id,
        'batch_id' => $newBatch->id,
        'reason' => 'Student schedule change',
    ])->assertOk()
        ->assertJsonPath('data.batch_id', $newBatch->id);

    expect($enrollment->fresh()->batch_id)->toBe($newBatch->id)
        ->and($enrollment->history()->where('action', 'assignment_changed')->value('reason'))
        ->toBe('Student schedule change');

    $this->getJson('/api/v1/enrollments/'.$enrollment->id.'/history')
        ->assertOk()
        ->assertJsonPath('data.0.old_values.batch_id', $fixture['batch']->id)
        ->assertJsonPath('data.0.new_values.batch_id', $newBatch->id);
});

it('requires a passing exam for configured stages and allows a passing retake', function (): void {
    $fixture = academyWorkflowFixture();
    $stage = CourseStage::query()->create([
        'course_id' => $fixture['course']->id,
        'code' => 'WF-STAGE-'.$fixture['suffix'],
        'name' => 'Required Exam Stage',
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
    $stageRecord->update(['status' => 'completed', 'completed_on' => '2027-12-01']);

    $this->postJson('/api/v1/enrollments/'.$enrollment->id.'/complete')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('enrollment');

    foreach (['failed', 'passed'] as $attempt => $result) {
        ExamRecord::query()->create([
            'student_stage_record_id' => $stageRecord->id,
            'exam_name' => 'Final Examination',
            'attempt_number' => $attempt + 1,
            'exam_date' => '2027-11-01',
            'attempt_status' => 'appeared',
            'result_status' => $result,
            'total_marks' => '100.00',
            'obtained_marks' => $result === 'passed' ? '80.00' : '40.00',
        ]);
    }

    $this->postJson('/api/v1/enrollments/'.$enrollment->id.'/complete')
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('retains communication-group membership history and exposes student notifications', function (): void {
    $fixture = academyWorkflowFixture();
    $group = CommunicationGroup::query()->create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Workflow Student Group',
        'platform' => 'whatsapp',
        'group_url' => 'https://example.test/workflow-group',
        'is_active' => true,
    ]);
    $studentUrl = '/api/v1/students/'.$fixture['student']->student_code;

    $this->postJson($studentUrl.'/communication-groups/'.$group->id, [
        'notes' => 'Added for course updates',
    ])->assertCreated();
    $this->deleteJson($studentUrl.'/communication-groups/'.$group->id, [
        'notes' => 'No longer required',
    ])->assertOk();

    expect(StudentGroupMembership::query()
        ->where('student_id', $fixture['student']->id)
        ->count())->toBe(1)
        ->and(StudentGroupMembership::query()
            ->where('student_id', $fixture['student']->id)
            ->value('status'))->toBe('left');

    $this->postJson($studentUrl.'/notifications', [
        'type' => 'schedule_change',
        'title' => 'Batch schedule updated',
        'message' => 'The next class schedule has changed.',
        'action_url' => '/academy/students',
    ])->assertCreated();

    $this->getJson($studentUrl.'/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'schedule_change')
        ->assertJsonPath('data.0.title', 'Batch schedule updated');
});

it('records student communications with a follow-up timestamp', function (): void {
    $fixture = academyWorkflowFixture();

    $this->postJson('/api/v1/students/'.$fixture['student']->student_code.'/communications', [
        'activity_type' => 'phone_call',
        'channel' => 'phone',
        'direction' => 'outgoing',
        'remarks' => 'Discussed the next course stage.',
        'contacted_at' => '2026-11-20 09:00:00',
        'follow_up_at' => '2026-11-25 09:00:00',
    ])->assertCreated()
        ->assertJsonPath('data.activity_type', 'phone_call')
        ->assertJsonPath('data.remarks', 'Discussed the next course stage.');
});

it('synchronizes payment and completion alerts from their source records', function (): void {
    $fixture = academyWorkflowFixture();
    $enrollment = app(StudentEnrollmentService::class)->enroll($fixture['student'], [
        'course_id' => $fixture['course']->id,
        'batch_id' => $fixture['batch']->id,
        'enrollment_date' => '2026-11-20',
    ]);
    StudentTuition::query()->create([
        'enrollment_id' => $enrollment->id,
        'tuition_number' => 'WF-TUITION-'.$fixture['suffix'],
        'original_amount' => '5000.00',
        'waiver_amount' => '0.00',
        'issued_on' => '2026-11-20',
        'due_on' => '2026-12-20',
        'status' => 'issued',
    ]);
    $enrollment->update(['status' => 'completed', 'is_active' => false]);

    $this->postJson('/api/v1/students/'.$fixture['student']->student_code.'/notifications/synchronize')
        ->assertOk()
        ->assertJsonPath('data.created', 2);

    expect($fixture['student']->notifications()->where('type', 'payment_due')->exists())->toBeTrue()
        ->and($fixture['student']->notifications()->where('type', 'course_completion')->exists())->toBeTrue();

    expect($fixture['student']->notifications()->where('type', 'payment_due')->value('action_url'))
        ->toContain('enrollment_id='.$enrollment->id);
});
