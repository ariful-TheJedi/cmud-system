<?php

namespace App\Modules\Academy\Controllers\Enrollments;

use App\Http\Controllers\Controller;
use App\Modules\Academy\Requests\StudentEnrollmentRequest;
use App\Modules\Academy\Services\Enrollments\StudentEnrollmentService;
use App\Modules\Academy\Services\Students\StudentSearchService;
use Illuminate\Http\JsonResponse;

class StudentEnrollmentController extends Controller
{
    public function store(
        StudentEnrollmentRequest $request,
        string $studentCode,
        StudentSearchService $students,
        StudentEnrollmentService $enrollments,
    ): JsonResponse {
        $student = $students->findByCode($studentCode);
        $validated = $request->validated();
        $enrollment = $enrollments->enroll($student, [
            'course_id' => (int) $validated['course_id'],
            'batch_id' => (int) $validated['batch_id'],
            'enrollment_date' => (string) $validated['enrollment_date'],
        ]);

        return response()->json([
            'data' => [
                'student_code' => $student->student_code,
                'enrollment' => [
                    'id' => $enrollment->id,
                    'enrollment_code' => $enrollment->enrollment_code,
                    'course_id' => $enrollment->course_id,
                    'batch_id' => $enrollment->batch_id,
                    'branch_id' => $enrollment->branch_id,
                    'enrollment_date' => $enrollment->getRawOriginal('enrollment_date'),
                    'expected_completion_on' => $enrollment->getRawOriginal('expected_completion_on'),
                    'completion_deadline_on' => $enrollment->getRawOriginal('completion_deadline_on'),
                    'status' => $enrollment->getRawOriginal('status'),
                ],
            ],
        ], 201);
    }
}
