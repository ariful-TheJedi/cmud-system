<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Finance\StudentTuition;
use App\Modules\Finance\Requests\StudentTuitionCashPaymentRequest;
use App\Modules\Finance\Services\StudentBill\StudentTuitionPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** HTTP adapter for collecting cash against issued student tuition. */
class StudentTuitionPaymentController extends Controller
{
    /** Return the branch-accessible cash accounts available for this tuition. */
    public function options(
        Request $request,
        StudentTuition $tuition,
        StudentTuitionPaymentService $payments,
    ): JsonResponse {
        return response()->json(['data' => $payments->paymentOptions($tuition, $request->user())]);
    }

    /** Record a confirmed cash receipt and return the refreshed tuition balance. */
    public function storeCashPayment(
        StudentTuitionCashPaymentRequest $request,
        StudentTuition $tuition,
        StudentTuitionPaymentService $payments,
    ): JsonResponse {
        $data = $payments->recordCashPayment($tuition, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Student tuition cash payment recorded successfully.',
            'data' => $data,
        ], $data['idempotent_replay'] ? 200 : 201);
    }
}
