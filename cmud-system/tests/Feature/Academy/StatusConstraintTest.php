<?php

namespace Tests\Feature\Academy;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatusConstraintTest extends TestCase
{
    use RefreshDatabase;

    private ?int $instituteId = null;
    private ?int $branchId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituteId = DB::table('institutes')->insertGetId([
            'code' => 'STATUS-INSTITUTE-'.uniqid(),
            'name' => 'Status Test Institute',
            'phone' => '01700000001',
            'email' => 'status-institute@example.test',
            'address' => 'Status Test Address',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->branchId = DB::table('branches')->insertGetId([
            'institute_id' => $this->instituteId,
            'code' => 'STATUS-BRANCH-'.uniqid(),
            'name' => 'Status Test Branch',
            'phone' => '01700000002',
            'address' => 'Status Test Branch Address',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_valid_student_statuses_are_accepted_and_invalid_statuses_are_rejected(): void
    {
        foreach (['registered', 'active', 'deactivated', 'suspended'] as $status) {
            $id = $this->createMinimalStudent($status);
            $this->assertDatabaseHas('students', ['id' => $id, 'status' => $status]);
        }

        $this->expectException(QueryException::class);
        $this->createMinimalStudent('inactive');
    }

    public function test_valid_and_invalid_enrollment_statuses_are_rejected_consistently(): void
    {
        foreach (['enrolled', 'ongoing', 'on_hold', 'completed', 'discontinued', 'cancelled'] as $status) {
            $id = $this->createMinimalEnrollment($status);
            $this->assertDatabaseHas('enrollments', ['id' => $id, 'status' => $status]);
        }

        $this->expectException(QueryException::class);
        $this->createMinimalEnrollment('active');
    }

    public function test_stage_attempt_result_and_certificate_status_constraints_are_enforced(): void
    {
        foreach (['pending', 'ongoing', 'on_hold', 'completed', 'failed', 'incomplete'] as $status) {
            $this->assertStatusAccepted('student_stage_records', $status);
        }
        foreach (['scheduled', 'appeared', 'absent', 'cancelled'] as $status) {
            $this->assertExamAttemptAccepted($status);
        }
        foreach (['pending', 'passed', 'failed', 'incomplete'] as $status) {
            $this->assertExamResultAccepted($status);
        }
        foreach (['not_eligible', 'eligibility_pending', 'pending', 'processing', 'ready', 'issued', 'delivered'] as $status) {
            $this->assertCertificateStatusAccepted($status);
        }

        $this->expectException(QueryException::class);
        $this->assertStatusAccepted('student_stage_records', 'in_progress');
    }

    public function test_reissue_status_rejects_invalid_values_and_self_replacement(): void
    {
        $certificateId = $this->createMinimalCertificate();

        $validId = DB::table('certificate_reissue_requests')->insertGetId([
            'original_certificate_id' => $certificateId,
            'replacement_certificate_id' => null,
            'reason' => 'Test reissue',
            'requested_at' => now(),
            'status' => 'requested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertDatabaseHas('certificate_reissue_requests', ['id' => $validId, 'status' => 'requested']);

        $this->expectException(QueryException::class);
        DB::table('certificate_reissue_requests')->insert([
            'original_certificate_id' => $certificateId,
            'replacement_certificate_id' => $certificateId,
            'reason' => 'Invalid self replacement',
            'requested_at' => now(),
            'status' => 'requested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_replacement_certificate_is_unique_and_rejects_invalid_request_status(): void
    {
        $originalId = $this->createMinimalCertificate();
        $replacementId = $this->createMinimalCertificate();

        DB::table('certificate_reissue_requests')->insert([
            'original_certificate_id' => $originalId,
            'replacement_certificate_id' => $replacementId,
            'reason' => 'First assignment',
            'requested_at' => now(),
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('certificate_reissue_requests')->insert([
            'original_certificate_id' => $originalId,
            'replacement_certificate_id' => $replacementId,
            'reason' => 'Duplicate assignment',
            'requested_at' => now(),
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMinimalStudent(string $status): int
    {
        return DB::table('students')->insertGetId([
            'institute_id' => $this->instituteId,
            'admission_branch_id' => $this->branchId,
            'student_code' => 'STU-STATUS-'.str_replace('_', '-', $status).'-'.uniqid(),
            'admission_date' => '2026-01-01',
            'full_name' => 'Status Test',
            'phone' => '01700000'.random_int(100, 999),
            'present_address' => 'Test',
            'qualification' => 'Test',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMinimalEnrollment(string $status): int
    {
        $studentId = $this->createMinimalStudent('active');
        $courseId = DB::table('courses')->insertGetId([
            'institute_id' => $this->instituteId,
            'code' => 'STATUS-'.uniqid(),
            'name' => 'Status Course',
            'duration_months' => 12,
            'completion_period_months' => 15,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $branchId = $this->branchId;
        $batchId = DB::table('batches')->insertGetId([
            'course_id' => $courseId,
            'branch_id' => $branchId,
            'code' => 'STATUS-BATCH-'.uniqid(),
            'name' => 'Status Batch',
            'starts_on' => '2026-01-01',
            'expected_ends_on' => '2026-12-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('enrollments')->insertGetId([
            'enrollment_code' => 'ENR-STATUS-'.uniqid(),
            'student_id' => $studentId,
            'course_id' => $courseId,
            'branch_id' => $branchId,
            'batch_id' => $batchId,
            'enrollment_date' => '2026-01-01',
            'expected_completion_on' => '2026-12-01',
            'completion_deadline_on' => '2027-02-01',
            'status' => $status,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertStatusAccepted(string $table, string $status): void
    {
        if ($table !== 'student_stage_records') {
            DB::table($table)->insert(['status' => $status]);
            return;
        }

        $enrollmentId = $this->createMinimalEnrollment('ongoing');
        $stageId = DB::table('course_stages')->insertGetId([
            'course_id' => DB::table('enrollments')->where('id', $enrollmentId)->value('course_id'),
            'code' => 'STAGE-'.uniqid(),
            'name' => 'Status Stage',
            'sequence_number' => 1,
            'duration_months' => 12,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('student_stage_records')->insert([
            'enrollment_id' => $enrollmentId,
            'course_stage_id' => $stageId,
            'batch_id' => DB::table('enrollments')->where('id', $enrollmentId)->value('batch_id'),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertExamAttemptAccepted(string $status): void
    {
        $stageId = $this->createMinimalStageRecord();
        DB::table('exam_records')->insert([
            'student_stage_record_id' => $stageId,
            'exam_name' => 'Exam '.uniqid(),
            'attempt_status' => $status,
            'attempt_number' => 1,
            'exam_date' => '2026-06-01',
            'result_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertExamResultAccepted(string $status): void
    {
        $stageId = $this->createMinimalStageRecord();
        DB::table('exam_records')->insert([
            'student_stage_record_id' => $stageId,
            'exam_name' => 'Exam '.uniqid(),
            'attempt_status' => 'appeared',
            'attempt_number' => 1,
            'exam_date' => '2026-06-01',
            'result_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertCertificateStatusAccepted(string $status): void
    {
        $certificateId = $this->createMinimalCertificate($status);
        $this->assertDatabaseHas('certificates', ['id' => $certificateId, 'status' => $status]);
    }

    private function createMinimalStageRecord(): int
    {
        $enrollmentId = $this->createMinimalEnrollment('ongoing');
        $enrollment = DB::table('enrollments')->where('id', $enrollmentId)->first();
        $stageId = DB::table('course_stages')->insertGetId([
            'course_id' => $enrollment->course_id,
            'code' => 'STAGE-'.uniqid(),
            'name' => 'Exam Stage',
            'sequence_number' => 1,
            'duration_months' => 12,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('student_stage_records')->insertGetId([
            'enrollment_id' => $enrollmentId,
            'course_stage_id' => $stageId,
            'batch_id' => $enrollment->batch_id,
            'status' => 'ongoing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMinimalCertificate(?string $status = 'pending'): int
    {
        $enrollmentId = $this->createMinimalEnrollment('ongoing');
        $templateId = DB::table('certificate_templates')->insertGetId([
            'institute_id' => $this->instituteId,
            'name' => 'Status Template '.uniqid(),
            'version_number' => 1,
            'template_file_path' => 'test/template.pdf',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('certificates')->insertGetId([
            'enrollment_id' => $enrollmentId,
            'certificate_template_id' => $templateId,
            'recipient_name' => 'Certificate Student',
            'course_title' => 'Certificate Course',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
