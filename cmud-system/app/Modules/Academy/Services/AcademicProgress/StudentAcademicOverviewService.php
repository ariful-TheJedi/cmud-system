<?php

namespace App\Modules\Academy\Services\AcademicProgress;

use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;

class StudentAcademicOverviewService
{
    public function __construct(private AcademicProgressService $academics) {}

    /** @return array{student_code: string, enrollments: array<int, array<string, mixed>>} */
    public function forStudent(Student $student): array
    {
        return [
            'student_code' => $student->student_code,
            'enrollments' => $student->enrollments()
                ->with('course:id,code,name')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Enrollment $enrollment): array => $this->summarizeEnrollment($enrollment))
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function forEnrollment(Enrollment $enrollment): array
    {
        $overview = $this->academics->overview($enrollment);

        return [
            'enrollment' => [
                'id' => $enrollment->id,
                'enrollment_code' => $enrollment->enrollment_code,
                'status' => $enrollment->getRawOriginal('status'),
                'course' => $enrollment->course()->first(['id', 'code', 'name']),
            ],
            ...$overview,
        ];
    }

    /** @return array<string, mixed> */
    private function summarizeEnrollment(Enrollment $enrollment): array
    {
        $overview = $this->academics->overview($enrollment);
        $stages = $overview['stages'];
        $completedStages = 0;
        foreach ($stages as $stage) {
            if (($stage['status'] ?? null) === 'completed') {
                $completedStages++;
            }
        }

        return [
            'enrollment_code' => $enrollment->enrollment_code,
            'status' => $enrollment->getRawOriginal('status'),
            'course' => $enrollment->course ? [
                'id' => $enrollment->course->id,
                'code' => $enrollment->course->code,
                'name' => $enrollment->course->name,
            ] : null,
            'progress' => [
                'completed_stages' => $completedStages,
                'total_stages' => count($stages),
                'percentage' => count($stages) === 0
                    ? 0
                    : (int) round($completedStages / count($stages) * 100),
            ],
            'stages' => $stages,
            'completion' => $overview['completion'],
            'certificate_eligibility' => $overview['certificate_eligibility'],
        ];
    }
}
