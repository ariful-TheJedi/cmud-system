<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Enrollment;
use App\Modules\Academy\Services\Students\StudentSearchService;
use App\Modules\Finance\Services\StudentFinancialOverviewService;
use Illuminate\Http\JsonResponse;

class StudentFinancialOverviewController extends Controller
{
    public function student(
        string $studentCode,
        StudentSearchService $students,
        StudentFinancialOverviewService $finances,
    ): JsonResponse {
        return response()->json([
            'data' => $finances->forStudent($students->findByCode($studentCode)),
        ]);
    }

    public function enrollment(
        Enrollment $enrollment,
        StudentFinancialOverviewService $finances,
    ): JsonResponse {
        $summary = $finances->forEnrollment($enrollment);
        if ($summary === null) {
            return response()->json(['message' => 'No financial record exists for this enrollment.'], 404);
        }

        return response()->json(['data' => $summary]);
    }
}
