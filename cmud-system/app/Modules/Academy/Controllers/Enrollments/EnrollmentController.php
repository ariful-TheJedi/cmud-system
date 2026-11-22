<?php

namespace App\Modules\Academy\Controllers\Enrollments;

use App\Http\Controllers\Controller;
use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Modules\Academy\Requests\BatchTransferRequest;
use App\Modules\Academy\Requests\EnrollmentAssignmentRequest;
use App\Modules\Academy\Requests\EnrollmentStatusRequest;
use App\Modules\Academy\Services\Enrollments\StudentEnrollmentService;
use Illuminate\Http\JsonResponse;

class EnrollmentController extends Controller
{
    public function updateAssignment(
        EnrollmentAssignmentRequest $request,
        Enrollment $enrollment,
        StudentEnrollmentService $enrollments,
    ): JsonResponse {
        $course = Course::query()->findOrFail($request->integer('course_id'));
        $batch = Batch::query()->findOrFail($request->integer('batch_id'));
        $updated = $enrollments->updateAssignment(
            $enrollment,
            $course,
            $batch,
            $request->validated('reason'),
        );

        return response()->json(['data' => $updated->load(['course:id,code,name', 'batch:id,code,name'])]);
    }

    public function transferBatch(
        BatchTransferRequest $request,
        Enrollment $enrollment,
        StudentEnrollmentService $enrollments,
    ): JsonResponse {
        $batch = Batch::query()->findOrFail($request->integer('batch_id'));
        $updated = $enrollments->transferBatch($enrollment, $batch, $request->validated('reason'));

        return response()->json(['data' => $updated->load(['course:id,code,name', 'batch:id,code,name'])]);
    }

    public function updateStatus(
        EnrollmentStatusRequest $request,
        Enrollment $enrollment,
        StudentEnrollmentService $enrollments,
    ): JsonResponse {
        $updated = $enrollments->updateStatus(
            $enrollment,
            $request->validated('status'),
            $request->validated('reason'),
        );

        return response()->json(['data' => $updated]);
    }

    public function history(
        Enrollment $enrollment,
        StudentEnrollmentService $enrollments,
    ): JsonResponse {
        return response()->json(['data' => $enrollments->history($enrollment)]);
    }
}
