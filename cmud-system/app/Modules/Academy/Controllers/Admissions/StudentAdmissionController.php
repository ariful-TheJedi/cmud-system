<?php

namespace App\Modules\Academy\Controllers\Admissions;

use App\Http\Controllers\Controller;
use App\Modules\Academy\Requests\StudentAdmissionRequest;
use App\Modules\Academy\Services\Admissions\StudentAdmissionService;
use Illuminate\Http\JsonResponse;

class StudentAdmissionController extends Controller
{
    public function store(StudentAdmissionRequest $request, StudentAdmissionService $admissions): JsonResponse
    {
        $result = $admissions->admit($request->validated());

        return response()->json([
            'data' => [
                'student' => [
                    'student_code' => $result['student']->student_code,
                    'full_name' => $result['student']->full_name,
                ],
                'enrollment' => [
                    'enrollment_code' => $result['enrollment']->enrollment_code,
                ],
            ],
        ], 201);
    }
}
