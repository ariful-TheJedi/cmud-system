<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Enrollment;
use App\Modules\Academy\Services\Students\StudentSearchService;
use App\Modules\Finance\Services\StudentFinance\StudentFinancialOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentFinancialOverviewController extends Controller
{
    public function student(
        Request $request,
        string $studentCode,
        StudentSearchService $students,
        StudentFinancialOverviewService $finances,
    ): JsonResponse {
        return response()->json([
            'data' => $finances->forStudent($students->findByCode($studentCode), $request->user()),
        ]);
    }

    public function enrollment(
        Enrollment $enrollment,
        Request $request,
        StudentFinancialOverviewService $finances,
    ): JsonResponse {
        $summary = $finances->forEnrollment($enrollment, $request->user());
        if ($summary === null) {
            return response()->json(['message' => 'No financial record exists for this enrollment.'], 404);
        }

        return response()->json(['data' => $summary]);
    }
}
