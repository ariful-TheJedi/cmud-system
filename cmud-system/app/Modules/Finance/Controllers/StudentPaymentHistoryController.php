<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academy\Services\StudentSearchService;
use App\Modules\Finance\Requests\StudentPaymentHistoryRequest;
use App\Modules\Finance\Services\StudentPaymentHistoryService;
use Illuminate\Http\JsonResponse;

class StudentPaymentHistoryController extends Controller
{
    public function index(
        StudentPaymentHistoryRequest $request,
        string $studentCode,
        StudentSearchService $students,
        StudentPaymentHistoryService $payments,
    ): JsonResponse {
        return response()->json(
            $payments->list($students->findByCode($studentCode), $request->validated()),
        );
    }

    public function summary(
        string $studentCode,
        StudentSearchService $students,
        StudentPaymentHistoryService $payments,
    ): JsonResponse {
        return response()->json([
            'data' => $payments->summary($students->findByCode($studentCode)),
        ]);
    }

    public function show(
        string $paymentKey,
        StudentPaymentHistoryService $payments,
    ): JsonResponse {
        return response()->json([
            'data' => $payments->find($paymentKey),
        ]);
    }
}
