<?php

namespace App\Modules\Academy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Certificate;
use App\Models\Academy\Enrollment;
use App\Modules\Academy\Services\CertificateOverviewService;
use App\Modules\Academy\Services\StudentSearchService;
use Illuminate\Http\JsonResponse;

class CertificateOverviewController extends Controller
{
    public function enrollment(
        Enrollment $enrollment,
        CertificateOverviewService $certificates,
    ): JsonResponse {
        return response()->json(['data' => $certificates->forEnrollment($enrollment)]);
    }

    public function student(
        string $studentCode,
        StudentSearchService $students,
        CertificateOverviewService $certificates,
    ): JsonResponse {
        return response()->json([
            'data' => $certificates->forStudent($students->findByCode($studentCode)),
        ]);
    }

    public function show(Certificate $certificate, CertificateOverviewService $certificates): JsonResponse
    {
        return response()->json(['data' => $certificates->find($certificate)]);
    }
}
