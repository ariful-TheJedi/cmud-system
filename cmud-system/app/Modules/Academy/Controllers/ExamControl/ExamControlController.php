<?php

namespace App\Modules\Academy\Controllers\ExamControl;

use App\Http\Controllers\Controller;
use App\Models\Academy\StudentStageRecord;
use App\Modules\Academy\Requests\ExamControl\ExamControlBatchRequest;
use App\Modules\Academy\Requests\ExamControl\ExamControlSearchRequest;
use App\Modules\Academy\Requests\ExamControl\ExamControlStatusRequest;
use App\Modules\Academy\Services\ExamControl\ExamControlService;
use Illuminate\Http\JsonResponse;

class ExamControlController extends Controller
{
    public function batches(ExamControlBatchRequest $request, ExamControlService $examControl): JsonResponse
    {
        return response()->json(['data' => $examControl->batches($request->user(), $request->validated('search'))]);
    }

    public function students(ExamControlSearchRequest $request, ExamControlService $examControl): JsonResponse
    {
        $students = $examControl->students($request->validated(), $request->user());

        return response()->json([
            'data' => $students->items(),
            'meta' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
                'last_page' => $students->lastPage(),
            ],
        ]);
    }

    public function updateStatus(
        ExamControlStatusRequest $request,
        StudentStageRecord $stageRecord,
        ExamControlService $examControl,
    ): JsonResponse {
        return response()->json(['data' => $examControl->updateStatus($stageRecord, $request->validated())]);
    }
}
