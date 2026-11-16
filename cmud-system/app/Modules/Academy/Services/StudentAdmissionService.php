<?php

namespace App\Modules\Academy\Services;

use App\Models\Academy\Batch;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Institute\Branch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentAdmissionService
{
    /** @param array<string, mixed> $data
     * @return array{student: Student, enrollment: Enrollment}
     */
    public function admit(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $batch = Batch::query()->findOrFail($data['batch_id']);
            $course = $batch->course()->firstOrFail();
            $branch = Branch::query()->findOrFail($data['admission_branch_id']);
            $admissionDate = Carbon::parse($data['admission_date'] ?? now()->toDateString());
            $startsOn = $batch->starts_on;
            $expectedCompletion = $startsOn->copy()->addMonths($course->completion_period_months);
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

            $enrollment = $student->enrollments()->create([
                'enrollment_code' => 'ENR-'.Str::upper((string) Str::uuid()),
                'course_id' => $course->id,
                'branch_id' => $branch->id,
                'batch_id' => $batch->id,
                'enrollment_date' => $admissionDate,
                'starts_on' => $startsOn,
                'expected_completion_on' => $expectedCompletion,
                'completion_deadline_on' => $expectedCompletion,
                'status' => 'active',
                'is_active' => true,
            ]);

            return ['student' => $student, 'enrollment' => $enrollment];
        });
    }
}
