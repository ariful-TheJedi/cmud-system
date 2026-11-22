<?php

namespace App\Modules\Academy\Controllers\AcademicProgress;

use App\Http\Controllers\Controller;
use App\Models\Academy\Enrollment;
use App\Modules\Academy\Services\AcademicProgress\StudentAcademicOverviewService;
use App\Modules\Academy\Services\Students\StudentSearchService;
use Illuminate\Http\JsonResponse;

class StudentAcademicOverviewController extends Controller
{
    public function student(
        string $studentCode,
        StudentSearchService $students,
        StudentAcademicOverviewService $academics,
    ): JsonResponse {
        return response()->json([
            'data' => $academics->forStudent($students->findByCode($studentCode)),
        ]);
    }

    public function enrollment(
        Enrollment $enrollment,
        StudentAcademicOverviewService $academics,
    ): JsonResponse {
        return response()->json(['data' => $academics->forEnrollment($enrollment)]);
    }
}
