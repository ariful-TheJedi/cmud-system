<?php

namespace Database\Seeders\Development;

use App\Models\Academy\Student;
use App\Modules\Academy\Services\EnrollmentService\StudentEnrollmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

/** Seeds the current student's progress and a second enrollment for automatic certificate verification. */
class EnrollmentOverviewSeeder extends Seeder
{
    use UsesCoreSeedData;

    private const STUDENT_CODE = '202611001';

    private const ENROLLMENT_CODE = 'ENR-E3915C70-8950-418A-B55D-F898FF8E19EC';

    private const COURSE_CODE = 'DEV-COURSE-DMU';

    private const AUTO_CERTIFICATE_COURSE_CODE = 'DEV-COURSE-BLS';

    private const AUTO_CERTIFICATE_BATCH_CODE = 'DEV-BATCH-BLS-OVERVIEW-01';

    private const AUTO_CERTIFICATE_TEMPLATE_NAME = 'CMUD Overview BLS Certificate Template';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Enrollment overview data may only be seeded in local or testing environments.');
        }

        DB::transaction(function (): void {
            $studentId = $this->findId('students', ['student_code' => self::STUDENT_CODE]);
            $instituteId = (int) DB::table('students')
                ->where('id', $studentId)
                ->value('institute_id');
            $courseId = $this->findId('courses', [
                'institute_id' => $instituteId,
                'code' => self::COURSE_CODE,
            ]);
            $enrollmentId = $this->findId('enrollments', [
                'enrollment_code' => self::ENROLLMENT_CODE,
                'student_id' => $studentId,
                'course_id' => $courseId,
            ]);
            $enrollment = DB::table('enrollments')->where('id', $enrollmentId)->first();

            if ($enrollment === null || $enrollment->batch_id === null) {
                throw new RuntimeException('The current enrollment must have a linked batch before seeding stage progress.');
            }

            $courseStages = DB::table('course_stages')
                ->where('course_id', $courseId)
                ->where('is_active', true)
                ->orderBy('sequence_number')
                ->get();

            if ($courseStages->isEmpty()) {
                throw new RuntimeException('The current enrollment course has no active stages to seed.');
            }

            foreach ($courseStages as $courseStage) {
                $this->seedStageRecord(
                    $enrollmentId,
                    (int) $courseStage->id,
                    (int) $enrollment->batch_id,
                    (int) $courseStage->sequence_number,
                    (string) $enrollment->expected_completion_on,
                );
            }

            $this->seedIneligibleCertificate($enrollmentId, $instituteId, $studentId, $courseId);
            $this->seedAutomaticCertificateEnrollment(
                $studentId,
                $instituteId,
                (int) $enrollment->branch_id,
            );
        });
    }

    private function seedStageRecord(
        int $enrollmentId,
        int $courseStageId,
        int $batchId,
        int $sequenceNumber,
        string $enrollmentExpectedCompletion,
    ): void {
        $stage = match ($sequenceNumber) {
            1 => [
                'status' => 'completed',
                'started_on' => '2025-01-15',
                'expected_completion_on' => '2025-07-15',
                'completed_on' => '2025-07-15',
            ],
            2 => [
                'status' => 'ongoing',
                'started_on' => '2025-07-16',
                'expected_completion_on' => '2026-01-15',
                'completed_on' => null,
            ],
            default => [
                'status' => 'pending',
                'started_on' => null,
                'expected_completion_on' => $enrollmentExpectedCompletion,
                'completed_on' => null,
            ],
        };

        $this->saveRow('student_stage_records', [
            'enrollment_id' => $enrollmentId,
            'course_stage_id' => $courseStageId,
        ], [
            'batch_id' => $batchId,
            ...$stage,
            'notes' => $this->scenarioNote(
                'SD-ACA-012-STAGE-'.$sequenceNumber,
                'Fictional enrollment overview stage; no examination attempt is seeded.',
            ),
        ]);
    }

    private function seedIneligibleCertificate(
        int $enrollmentId,
        int $instituteId,
        int $studentId,
        int $courseId,
    ): void {
        $templateId = $this->findId('certificate_templates', [
            'institute_id' => $instituteId,
            'name' => 'CMUD Core Demo Certificate Template',
            'version_number' => 1,
        ]);
        $certificateNotes = $this->scenarioNote(
            'SD-ACA-012-CERTIFICATE',
            'Certificate is not eligible while the enrollment and mandatory stages are incomplete.',
        );

        $this->saveRow('certificates', ['notes' => $certificateNotes], [
            'enrollment_id' => $enrollmentId,
            'certificate_template_id' => $templateId,
            'certificate_number' => null,
            'recipient_name' => DB::table('students')->where('id', $studentId)->value('full_name'),
            'course_title' => DB::table('courses')->where('id', $courseId)->value('name'),
            'issued_on' => null,
            'certificate_file_path' => null,
            'printed_at' => null,
            'delivered_at' => null,
            'received_by' => null,
            'delivery_method' => null,
            'delivery_reference' => null,
            'status' => 'not_eligible',
            'void_reason' => null,
        ]);
    }

    private function seedAutomaticCertificateEnrollment(
        int $studentId,
        int $instituteId,
        int $branchId,
    ): void {
        $courseId = $this->findId('courses', [
            'institute_id' => $instituteId,
            'code' => self::AUTO_CERTIFICATE_COURSE_CODE,
        ]);
        $batch = $this->saveRow('batches', [
            'course_id' => $courseId,
            'code' => self::AUTO_CERTIFICATE_BATCH_CODE,
        ], [
            'branch_id' => $branchId,
            'name' => 'Life Support Overview Cohort 01',
            'admission_opens_on' => '2026-11-01',
            'admission_closes_on' => '2026-12-31',
            'starts_on' => '2027-01-15',
            'expected_ends_on' => '2027-07-15',
            'actual_ends_on' => null,
            'capacity' => 30,
            'status' => 'active',
            'notes' => $this->scenarioNote(
                'SD-ACA-013-BATCH',
                'Fictional cohort for verifying enrollment-created certificates.',
            ),
        ]);
        $template = $this->saveRow('certificate_templates', [
            'institute_id' => $instituteId,
            'course_id' => $courseId,
            'name' => self::AUTO_CERTIFICATE_TEMPLATE_NAME,
            'version_number' => 1,
        ], [
            'template_file_path' => 'certificates/dev-overview-bls-template.pdf',
            'description' => $this->scenarioNote(
                'SD-ACA-013-TEMPLATE',
                'Fictional course-specific template for the automatic enrollment certificate scenario.',
            ),
            'is_active' => true,
        ]);

        $existingEnrollment = DB::table('enrollments')
            ->where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->where('batch_id', $batch->id)
            ->first();

        if ($existingEnrollment !== null) {
            $certificateExists = DB::table('certificates')
                ->where('enrollment_id', $existingEnrollment->id)
                ->where('certificate_template_id', $template->id)
                ->where('status', 'not_eligible')
                ->exists();

            if (! $certificateExists) {
                throw new RuntimeException('The seeded automatic certificate enrollment is missing its not-eligible certificate.');
            }

            return;
        }

        $enrollment = app(StudentEnrollmentService::class)->enroll(
            Student::query()->findOrFail($studentId),
            [
                'course_id' => $courseId,
                'batch_id' => (int) $batch->id,
                'enrollment_date' => '2026-11-20',
            ],
        );

        $certificateExists = DB::table('certificates')
            ->where('enrollment_id', $enrollment->id)
            ->where('certificate_template_id', $template->id)
            ->where('status', 'not_eligible')
            ->exists();

        if (! $certificateExists) {
            throw new RuntimeException('Enrollment did not create its expected not-eligible certificate.');
        }
    }
}
