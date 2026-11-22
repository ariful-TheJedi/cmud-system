<?php

namespace App\Modules\Academy\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Modules\Academy\Requests\StudentUpdateRequest;
use App\Modules\Academy\Services\Students\StudentProfileService;
use Illuminate\Http\JsonResponse;

class StudentUpdateController extends Controller
{
    public function edit(string $studentCode, StudentProfileService $profiles): JsonResponse
    {
        $editData = $profiles->editData($studentCode);

        return response()->json([
            'data' => $editData['student'],
            'branches' => $editData['branches'],
        ]);
    }

    public function update(
        StudentUpdateRequest $request,
        string $studentCode,
        StudentProfileService $profiles,
    ): JsonResponse {
        return response()->json([
            'data' => $profiles->update($studentCode, $request->validated()),
        ]);
    }
}
