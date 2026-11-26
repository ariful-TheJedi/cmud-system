<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Requests\StudentOtherPaymentStoreRequest;
use App\Modules\Finance\Requests\StudentPaymentSearchRequest;
use App\Modules\Finance\Requests\StudentTuitionPaymentStoreRequest;
use App\Modules\Finance\Services\StudentFinance\StudentPaymentService;
use Illuminate\Http\JsonResponse;

/** HTTP adapter for the student payment register, options, and receipt submissions. */
class StudentPaymentController extends Controller
{
    public function index(StudentPaymentSearchRequest $request, StudentPaymentService $service): JsonResponse
    {
        $payments = $service->register($request->validated(), $request->user());

        return response()->json([
            'data' => collect($payments->items())->map(fn ($row): array => $this->formatRegisterRow($row))->all(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /** Preserve the Academy module's scoped Student ID lookup while returning billing choices. */
    public function storeTuition(StudentTuitionPaymentStoreRequest $request, StudentPaymentService $service): JsonResponse
    {
        $result = $service->createTuitionPayment($request->validated(), $request->user());

        return response()->json([
            'message' => $result['idempotent_replay'] ? 'This payment was already recorded.' : 'Tuition payment recorded successfully.',
            'data' => [...$result['payment'], 'idempotent_replay' => $result['idempotent_replay']],
        ], $result['idempotent_replay'] ? 200 : 201);
    }

    public function storeOtherExpense(StudentOtherPaymentStoreRequest $request, StudentPaymentService $service): JsonResponse
    {
        $result = $service->createOtherPayment($request->validated(), $request->user());

        return response()->json([
            'message' => $result['idempotent_replay'] ? 'This bill and payment were already recorded.' : 'Student bill and payment recorded successfully.',
            'data' => $result,
        ], $result['idempotent_replay'] ? 200 : 201);
    }

    /** Translate SQL union columns to the stable nested shape used by the receipt UI. */
    private function formatRegisterRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'payment_key' => $row->source.'-'.$row->id,
            'source' => $row->source,
            'payment_number' => $row->payment_number,
            'amount' => (string) $row->amount,
            'paid_at' => $row->paid_at,
            'status' => $row->status,
            'payment_method' => $row->payment_method,
            'transaction_reference' => $row->transaction_reference,
            'payer_phone' => $row->payer_phone,
            'notes' => $row->notes,
            'recorded_by_name' => $row->recorded_by_name,
            'category' => $row->category,
            'description' => $row->description,
            'expense_id' => $row->expense_id ? (int) $row->expense_id : null,
            'student' => [
                'id' => (int) $row->student_pk,
                'student_code' => $row->student_code,
                'full_name' => $row->full_name,
            ],
            'branch' => ['id' => (int) $row->branch_id, 'name' => $row->branch_name],
            'enrollment' => [
                'id' => $row->enrollment_id === null ? null : (int) $row->enrollment_id,
                'enrollment_code' => $row->enrollment_code,
                'course_name' => $row->course_name,
                'batch_name' => $row->batch_name,
                'branch_name' => $row->branch_name,
                'branch_phone' => $row->branch_phone,
                'branch_email' => $row->branch_email,
                'branch_address' => $row->branch_address,
            ],
        ];
    }
}
