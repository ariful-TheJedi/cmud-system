<?php

use App\Models\Academy\Enrollment;
use App\Modules\Academy\Services\CertificateOverviewService;
use App\Modules\Academy\Services\EnrollmentService\AcademicProgressService;
use Database\Seeders\Development\CoreDevelopmentSeeder;
use Database\Seeders\Development\EnrollmentOverviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds current enrollment progress and a second course with an automatic certificate', function (): void {
    $this->seed(CoreDevelopmentSeeder::class);
    seedCurrentStudentEnrollmentForOverview();
    $this->seed(EnrollmentOverviewSeeder::class);

    $enrollment = Enrollment::query()
        ->where('enrollment_code', 'ENR-E3915C70-8950-418A-B55D-F898FF8E19EC')
        ->firstOrFail();
    $academic = app(AcademicProgressService::class)->overview($enrollment);
    $certificates = app(CertificateOverviewService::class)->forEnrollment($enrollment);
    $automaticEnrollment = Enrollment::query()
        ->where('student_id', $enrollment->student_id)
        ->whereHas('course', fn ($query) => $query->where('code', 'DEV-COURSE-BLS'))
        ->whereHas('batch', fn ($query) => $query->where('code', 'DEV-BATCH-BLS-OVERVIEW-01'))
        ->firstOrFail();
    $automaticCertificate = $automaticEnrollment->certificates()->firstOrFail();

    expect($academic['stages'])->toHaveCount(3)
        ->and(array_column($academic['stages'], 'status'))->toBe(['completed', 'ongoing', 'pending'])
        ->and(collect($academic['stages'])->every(fn (array $stage): bool => $stage['exams'] === []))->toBeTrue()
        ->and($academic['certificate_eligibility']['status'])->toBe('not_eligible')
        ->and($academic['certificate_eligibility']['outstanding_requirements'])
        ->toContain('The enrollment must be completed before a certificate can be issued.')
        ->and($certificates)->toHaveCount(1)
        ->and($certificates[0]['status'])->toBe('not_eligible')
        ->and($certificates[0]['certificate_number'])->toBeNull()
        ->and($automaticEnrollment->student_id)->toBe($enrollment->student_id)
        ->and($automaticEnrollment->course->code)->toBe('DEV-COURSE-BLS')
        ->and($automaticCertificate->getRawOriginal('status'))->toBe('not_eligible')
        ->and($automaticCertificate->certificateTemplate->course_id)->toBe($automaticEnrollment->course_id);

    $this->seed(EnrollmentOverviewSeeder::class);

    $stageIds = array_column($academic['stages'], 'id');
    expect(DB::table('student_stage_records')->where('enrollment_id', $enrollment->id)->count())->toBe(3)
        ->and(DB::table('exam_records')->whereIn('student_stage_record_id', $stageIds)->count())->toBe(0)
        ->and(DB::table('certificates')
            ->where('notes', 'like', '%CMUD-CORE:SD-ACA-012-CERTIFICATE%')
            ->count())->toBe(1)
        ->and($automaticEnrollment->certificates()->count())->toBe(1)
        ->and(Enrollment::query()
            ->where('student_id', $enrollment->student_id)
            ->whereHas('course', fn ($query) => $query->where('code', 'DEV-COURSE-BLS'))
            ->whereHas('batch', fn ($query) => $query->where('code', 'DEV-BATCH-BLS-OVERVIEW-01'))
            ->count())->toBe(1);
});

function seedCurrentStudentEnrollmentForOverview(): void
{
    $sourceStudent = DB::table('students')
        ->where('student_code', 'DEV-CORE-STU-004')
        ->first();
    $studentAttributes = (array) $sourceStudent;
    unset($studentAttributes['id']);
    $studentAttributes['student_code'] = '202611001';
    $studentAttributes['phone'] = '+880000009901';
    $studentAttributes['email'] = 'overview.current.student@example.invalid';

    $studentId = DB::table('students')->insertGetId($studentAttributes);
    $courseId = DB::table('courses')
        ->where('code', 'DEV-COURSE-DMU')
        ->value('id');
    $batchId = DB::table('batches')
        ->where('code', 'DEV-BATCH-DMU-01')
        ->value('id');

    DB::table('enrollments')->insert([
        'enrollment_code' => 'ENR-E3915C70-8950-418A-B55D-F898FF8E19EC',
        'student_id' => $studentId,
        'course_id' => $courseId,
        'branch_id' => $studentAttributes['admission_branch_id'],
        'batch_id' => $batchId,
        'enrollment_date' => '2026-11-18',
        'starts_on' => '2025-01-15',
        'expected_completion_on' => '2027-01-15',
        'completion_deadline_on' => '2027-06-15',
        'completed_on' => null,
        'status' => 'ongoing',
        'is_active' => true,
        'notes' => 'Test-only current student enrollment.',
        'created_at' => '2026-11-18 00:00:00',
        'updated_at' => '2026-11-18 00:00:00',
    ]);
}
