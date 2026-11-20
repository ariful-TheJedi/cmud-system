<?php

namespace App\Modules\Academy\Services;

use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Institute\Branch;
use App\Modules\Academy\Services\EnrollmentService\StudentEnrollmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentAdmissionService
{
    public function __construct(private StudentEnrollmentService $enrollments) {}

    /** @param array<string, mixed> $data
     * @return array{student: Student, enrollment: Enrollment}
     */
    public function admit(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $batch = Batch::query()
                ->whereKey((int) $data['batch_id'])
                ->firstOrFail();
            $course = Course::query()
                ->whereKey($batch->course_id)
                ->firstOrFail();
            $branch = Branch::query()->findOrFail((int) $data['admission_branch_id']);

            if ((int) $branch->institute_id !== (int) $course->institute_id) {
                throw ValidationException::withMessages([
                    'admission_branch_id' => 'The admission branch must belong to the batch course institute.',
                ]);
            }

            if (! $branch->is_active) {
                throw ValidationException::withMessages([
                    'admission_branch_id' => 'The admission branch must be active.',
                ]);
            }

            if ((int) $batch->branch_id !== (int) $branch->id) {
                throw ValidationException::withMessages([
                    'batch_id' => 'The batch must be offered at the selected admission branch.',
                ]);
            }

            if (isset($data['course_id']) && (int) $data['course_id'] !== (int) $course->id) {
                throw ValidationException::withMessages([
                    'course_id' => 'The course must match the selected batch.',
                ]);
            }
            $admissionDate = Carbon::parse($data['admission_date'] ?? now()->toDateString());
            $phoneNumbers = array_values(array_unique($data['phone_numbers'] ?? array_filter([
                $data['phone'] ?? null,
                $data['alternative_phone'] ?? null,
            ])));

            $studentFields = array_intersect_key($data, array_flip([
                'admission_branch_id', 'full_name', 'father_name', 'mother_name',
                'date_of_birth', 'gender', 'email', 'present_address',
                'permanent_address', 'medical_college_id', 'qualification', 'graduation_year',
                'bmdc_number', 'notes',
            ]));

            $student = Student::query()->create([
                ...$studentFields,
                'institute_id' => $course->institute_id,
                'student_code' => $data['student_code'],
                'admission_date' => $admissionDate,
                'phone' => $phoneNumbers[0],
                'alternative_phone' => $phoneNumbers[1] ?? null,
                'phone_numbers' => $phoneNumbers,
                'status' => 'active',
            ]);

            $enrollment = $this->enrollments->enroll($student, [
                'course_id' => $course->id,
                'batch_id' => $batch->id,
                'enrollment_date' => $admissionDate->toDateString(),
            ]);

            return ['student' => $student, 'enrollment' => $enrollment];
        });
    }
}
