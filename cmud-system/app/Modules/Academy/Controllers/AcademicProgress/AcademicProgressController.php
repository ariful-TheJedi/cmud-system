<?php

namespace App\Modules\Academy\Controllers\AcademicProgress;

use App\Http\Controllers\Controller;
use App\Models\Academy\Enrollment;
use App\Models\Academy\ExamRecord;
use App\Models\Academy\StudentAcademicException;
use App\Models\Academy\StudentStageRecord;
use App\Modules\Academy\Requests\AcademicExceptionDecisionRequest;
use App\Modules\Academy\Requests\AcademicExceptionRequest;
use App\Modules\Academy\Requests\ExamAttemptRequest;
use App\Modules\Academy\Requests\ExamResultRequest;
use App\Modules\Academy\Requests\StageStatusRequest;
use App\Modules\Academy\Services\AcademicProgress\AcademicProgressService;
use Illuminate\Http\JsonResponse;

class AcademicProgressController extends Controller
{
    public function show(Enrollment $enrollment, AcademicProgressService $academics): JsonResponse
    {
        return response()->json(['data' => $academics->overview($enrollment)]);
    }

    public function updateStageStatus(
        StageStatusRequest $request,
        Enrollment $enrollment,
        StudentStageRecord $stageRecord,
        AcademicProgressService $academics,
    ): JsonResponse {
        $record = $academics->updateStageStatus(
            $enrollment,
            $stageRecord,
            (string) $request->validated('status'),
            $request->validated('reason'),
        );

        return response()->json(['data' => $record]);
    }

    public function advance(
        Enrollment $enrollment,
        StudentStageRecord $stageRecord,
        AcademicProgressService $academics,
    ): JsonResponse {
        return response()->json([
            'data' => $academics->advanceToNextStage($enrollment, $stageRecord),
        ]);
    }

    public function requestException(
        AcademicExceptionRequest $request,
        Enrollment $enrollment,
        AcademicProgressService $academics,
    ): JsonResponse {
        return response()->json(['data' => $academics->requestException($enrollment, $request->validated())], 201);
    }

    public function decideException(
        AcademicExceptionDecisionRequest $request,
        StudentAcademicException $exception,
        AcademicProgressService $academics,
    ): JsonResponse {
        $exception = $academics->decideException(
            $exception,
            (string) $request->validated('status'),
            $request->validated('decision_note'),
        );

        return response()->json(['data' => $exception]);
    }

    public function examEligibility(
        Enrollment $enrollment,
        StudentStageRecord $stageRecord,
        AcademicProgressService $academics,
    ): JsonResponse {
        return response()->json([
            'data' => $academics->examEligibility($enrollment, $stageRecord),
        ]);
    }

    public function recordExamAttempt(
        ExamAttemptRequest $request,
        Enrollment $enrollment,
        StudentStageRecord $stageRecord,
        AcademicProgressService $academics,
    ): JsonResponse {
        $data = $request->validated();

        return response()->json([
            'data' => $academics->recordExamAttempt($enrollment, $stageRecord, [
                'exam_name' => (string) $data['exam_name'],
                'exam_date' => (string) $data['exam_date'],
                'total_marks' => isset($data['total_marks']) ? (float) $data['total_marks'] : null,
            ]),
        ], 201);
    }

    public function publishExamResult(
        ExamResultRequest $request,
        ExamRecord $exam,
        AcademicProgressService $academics,
    ): JsonResponse {
        $data = $request->validated();

        return response()->json([
            'data' => $academics->updateExamResult($exam, [
                'result_status' => (string) $data['result_status'],
                'obtained_marks' => isset($data['obtained_marks']) ? (float) $data['obtained_marks'] : null,
                'grade' => isset($data['grade']) ? (string) $data['grade'] : null,
                'remarks' => isset($data['remarks']) ? (string) $data['remarks'] : null,
            ]),
        ]);
    }

    public function complete(Enrollment $enrollment, AcademicProgressService $academics): JsonResponse
    {
        return response()->json(['data' => $academics->completeEnrollment($enrollment)]);
    }

    public function certificateEligibility(
        Enrollment $enrollment,
        AcademicProgressService $academics,
    ): JsonResponse {
        return response()->json(['data' => $academics->certificateEligibility($enrollment)]);
    }
}
