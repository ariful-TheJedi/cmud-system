<?php

namespace App\Modules\Academy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Modules\Academy\Http\Requests\StudentEnrollmentRequest;
use App\Modules\Academy\Services\StudentEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class StudentEnrollmentController extends Controller
{
    public function store(
        StudentEnrollmentRequest $request,
        string $studentCode,
        StudentEnrollmentService $enrollments,
    ): JsonResponse {
        $student = Student::query()
            ->where('student_code', $studentCode)
            ->firstOrFail();
        $data = $request->validated();

        $validator = Validator::make($data, []);
        $validator->after(function ($validator) use ($data, $student): void {
            $course = Course::query()->find($data['course_id']);
            $batch = Batch::query()->with('branch')->find($data['batch_id']);

            if ($course && $course->institute_id !== $student->institute_id) {
                $validator->errors()->add('course_id', 'The course must belong to the student’s institute.');
            }

            if ($course && $batch && $batch->course_id !== $course->id) {
                $validator->errors()->add('batch_id', 'The batch must belong to the selected course.');
            }

            if ($course && $batch?->branch && $batch->branch->institute_id !== $course->institute_id) {
                $validator->errors()->add('batch_id', 'The batch branch must belong to the selected course institute.');
            }
        });
        $validator->validate();

        $enrollment = $enrollments->enroll($student, $data);

        return response()->json([
            'data' => [
                'student_code' => $student->student_code,
                'enrollment' => [
                    'enrollment_code' => $enrollment->enrollment_code,
                    'course_id' => $enrollment->course_id,
                    'batch_id' => $enrollment->batch_id,
                    'branch_id' => $enrollment->branch_id,
                    'enrollment_date' => $enrollment->enrollment_date?->toDateString(),
                    'status' => $enrollment->status,
                ],
            ],
        ], 201);
    }
}
