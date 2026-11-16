<?php

namespace App\Modules\Academy\Services;

use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentEnrollmentService
{
    /** @param array{course_id: int, batch_id: int, enrollment_date: string} $data */
    public function enroll(Student $student, array $data): Enrollment
    {
        return DB::transaction(function () use ($student, $data): Enrollment {
            $course = Course::query()->findOrFail($data['course_id']);
            $batch = Batch::query()->findOrFail($data['batch_id']);
            $enrollmentDate = Carbon::parse($data['enrollment_date']);
            $startsOn = $batch->starts_on;
            $expectedCompletion = $startsOn->copy()->addMonths($course->completion_period_months);

            return $student->enrollments()->create([
                'enrollment_code' => 'ENR-'.Str::upper((string) Str::uuid()),
                'course_id' => $course->id,
                'branch_id' => $batch->branch_id,
                'batch_id' => $batch->id,
                'enrollment_date' => $enrollmentDate,
                'starts_on' => $startsOn,
                'expected_completion_on' => $expectedCompletion,
                'completion_deadline_on' => $expectedCompletion,
                'status' => 'active',
                'is_active' => true,
            ]);
        });
    }
}
