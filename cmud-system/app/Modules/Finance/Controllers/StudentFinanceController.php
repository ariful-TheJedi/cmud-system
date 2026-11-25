<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Enrollment;
use App\Models\Finance\StudentExpense;
use App\Modules\Finance\Requests\StudentBillingSearchRequest;
use App\Modules\Finance\Requests\StudentExpenseCashPaymentRequest;
use App\Modules\Finance\Requests\StudentExpenseOptionsRequest;
use App\Modules\Finance\Requests\StudentExpenseStoreRequest;
use App\Modules\Finance\Requests\StudentPaymentRegisterRequest;
use App\Modules\Finance\Requests\StudentTuitionCashPaymentRequest;
use App\Modules\Finance\Services\StudentFinance\StudentChargeRegisterService;
use App\Modules\Finance\Services\StudentFinance\StudentFinanceService;
use App\Modules\Finance\Services\StudentFinance\StudentPaymentRegisterService;
use App\Modules\Finance\Services\StudentFinance\StudentTuitionPaymentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** HTTP adapter for student billing operations scoped to one enrollment. */
class StudentFinanceController extends Controller
{
    /** Return course and batch filter choices from the branch-aware service. */
    public function registerOptions(StudentPaymentRegisterRequest $request, StudentPaymentRegisterService $register): JsonResponse
    {
        return response()->json(['data' => $register->filterOptions(
            $request->user(),
            $request->validated('course_id'),
        )]);
    }

    /** Return one paginated row per recorded tuition or expense payment. */
    public function register(StudentPaymentRegisterRequest $request, StudentPaymentRegisterService $register): JsonResponse
    {
        return $this->registerResponse($register->index($request->user(), $request->validated()));
    }

    /** Return charge obligations separately for the cash-payment selection workflow. */
    public function chargeRegister(StudentPaymentRegisterRequest $request, StudentChargeRegisterService $register): JsonResponse
    {
        return $this->registerResponse($register->index($request->user(), $request->validated()));
    }

    /** @param LengthAwarePaginator<array-key, array<string, mixed>> $page */
    /** Keep register pagination metadata consistent for payment rows and charge choices. */
    private function registerResponse(LengthAwarePaginator $page): JsonResponse
    {
        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function search(StudentBillingSearchRequest $request, StudentFinanceService $billing): JsonResponse
    {
        return response()->json(['data' => $billing->searchStudent($request->validated('student_id'), $request->user())]);
    }

    public function enrollments(string $studentCode, Request $request, StudentFinanceService $billing): JsonResponse
    {
        return response()->json(['data' => $billing->studentEnrollments($studentCode, $request->user())]);
    }

    public function overview(Enrollment $enrollment, Request $request, StudentFinanceService $billing): JsonResponse
    {
        return response()->json(['data' => $billing->billingOverview($enrollment, $request->user())]);
    }

    public function tuition(Enrollment $enrollment, Request $request, StudentFinanceService $billing): JsonResponse
    {
        $tuition = $billing->tuition($enrollment, $request->user());
        if ($tuition === null) {
            return response()->json(['message' => 'No tuition obligation exists for this enrollment.'], 404);
        }

        return response()->json(['data' => $tuition]);
    }

    public function paymentOptions(Enrollment $enrollment, Request $request, StudentFinanceService $billing): JsonResponse
    {
        return response()->json(['data' => $billing->paymentOptions($enrollment, $request->user())]);
    }

    public function storeTuitionPayment(
        StudentTuitionCashPaymentRequest $request,
        Enrollment $enrollment,
        StudentTuitionPaymentService $payments,
    ): JsonResponse {
        $data = $payments->recordCashPayment($enrollment, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Student tuition payment recorded successfully.',
            'data' => $data,
        ], $data['idempotent_replay'] ? 200 : 201);
    }

    public function expenseOptions(StudentExpenseOptionsRequest $request, Enrollment $enrollment, StudentFinanceService $billing): JsonResponse
    {
        return response()->json(['data' => $billing->expenseOptions(
            $enrollment,
            $request->user(),
            $request->validated('issued_on'),
            $request->validated('student_stage_record_id'),
        )]);
    }

    public function expenses(Enrollment $enrollment, Request $request, StudentFinanceService $billing): JsonResponse
    {
        return response()->json(['data' => $billing->expenses($enrollment, $request->user())]);
    }

    public function storeExpense(StudentExpenseStoreRequest $request, Enrollment $enrollment, StudentFinanceService $billing): JsonResponse
    {
        $data = $billing->createExpense($enrollment, $request->validated(), $request->user());

        return response()->json([
            'message' => isset($data['payment']) ? 'Student bill and payment recorded successfully.' : 'Student expense issued successfully.',
            'data' => $data,
        ], ($data['idempotent_replay'] ?? false) ? 200 : 201);
    }

    public function showExpense(Enrollment $enrollment, StudentExpense $expense, Request $request, StudentFinanceService $billing): JsonResponse
    {
        return response()->json(['data' => $billing->expense($enrollment, $expense, $request->user())]);
    }

    public function storeExpensePayment(
        StudentExpenseCashPaymentRequest $request,
        Enrollment $enrollment,
        StudentExpense $expense,
        StudentFinanceService $billing,
    ): JsonResponse {
        $data = $billing->recordExpenseCashPayment($enrollment, $expense, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Student expense payment recorded successfully.',
            'data' => $data,
        ], $data['idempotent_replay'] ? 200 : 201);
    }

    public function payments(Enrollment $enrollment, Request $request, StudentFinanceService $billing): JsonResponse
    {
        return response()->json(['data' => $billing->paymentHistory($enrollment, $request->user())]);
    }
}
