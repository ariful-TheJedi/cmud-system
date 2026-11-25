<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academy\Services\Students\StudentSearchService;
use App\Modules\Finance\Requests\StudentPaymentHistoryRequest;
use App\Modules\Finance\Services\StudentBill\StudentPaymentHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentPaymentHistoryController extends Controller
{
    public function index(
        StudentPaymentHistoryRequest $request,
        string $studentCode,
        StudentSearchService $students,
        StudentPaymentHistoryService $payments,
    ): JsonResponse {
        return response()->json(
            $payments->list($students->findByCode($studentCode), $request->validated(), $request->user()),
        );
    }

    public function summary(
        Request $request,
        string $studentCode,
        StudentSearchService $students,
        StudentPaymentHistoryService $payments,
    ): JsonResponse {
        return response()->json([
            'data' => $payments->summary($students->findByCode($studentCode), $request->user()),
        ]);
    }

    public function show(
        Request $request,
        string $paymentKey,
        StudentPaymentHistoryService $payments,
    ): JsonResponse {
        return response()->json([
            'data' => $payments->find($paymentKey, $request->user()),
        ]);
    }
}
