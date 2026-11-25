<?php

namespace App\Modules\Finance\Controllers\InstituteBill;

use App\Http\Controllers\Controller;
use App\Models\Finance\InstituteExpense;
use App\Modules\Finance\Requests\InstituteBill\InstituteBillItemSearchRequest;
use App\Modules\Finance\Requests\InstituteBill\InstituteBillIndexRequest;
use App\Modules\Finance\Requests\InstituteBill\InstituteBillStoreRequest;
use App\Modules\Finance\Requests\InstituteBill\InstituteBillCashPaymentRequest;
use App\Modules\Finance\Requests\InstituteBill\InstituteBillStatusUpdateRequest;
use App\Modules\Finance\Services\InstituteBill\InstituteBillNumberPolicy;
use App\Modules\Finance\Services\InstituteBill\InstituteBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** HTTP adapter for Institute Billing register, details, and creation. */
class InstituteBillController extends Controller
{
    public function index(InstituteBillIndexRequest $request, InstituteBillService $service): JsonResponse
    {
        $user = $request->user();
        $result = $service->list($request->validated(), $user);

        return response()->json([
            'data' => array_map(fn (InstituteExpense $bill): array => $service->show($bill, $user), $result['bills']->items()),
            'meta' => [
                'current_page' => $result['bills']->currentPage(),
                'last_page' => $result['bills']->lastPage(),
                'per_page' => $result['bills']->perPage(),
                'total' => $result['bills']->total(),
            ],
        ]);
    }

    public function options(Request $request, InstituteBillService $service): JsonResponse
    {
        return response()->json(['data' => $service->formOptions($request->user())]);
    }

    public function billingItems(InstituteBillItemSearchRequest $request, InstituteBillService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->searchBillingItems((string) ($request->validated()['search'] ?? ''), $request->user()),
        ]);
    }

    public function show(Request $request, InstituteExpense $bill, InstituteBillService $service): JsonResponse
    {
        return response()->json(['data' => $service->show($bill, $request->user())]);
    }

    public function store(
        InstituteBillStoreRequest $request,
        InstituteBillService $service,
        InstituteBillNumberPolicy $numberPolicy,
    ): JsonResponse {
        return response()->json([
            'message' => 'Institute bill created successfully.',
            'data' => $service->create(
                $request->validated(),
                $request->user(),
                $numberPolicy,
            ),
        ], 201);
    }

    /** Apply an approval-state change and return the refreshed bill record. */
    public function updateStatus(
        InstituteBillStatusUpdateRequest $request,
        InstituteExpense $bill,
        InstituteBillService $service,
    ): JsonResponse {
        return response()->json([
            'message' => 'Institute bill status updated successfully.',
            'data' => $service->updateStatus(
                $bill,
                $request->validated(),
                $request->user(),
            ),
        ]);
    }

    /** Record a cash payment after the bill has been approved. */
    public function storeCashPayment(
        InstituteBillCashPaymentRequest $request,
        InstituteExpense $bill,
        InstituteBillService $service,
    ): JsonResponse {
        return response()->json([
            'message' => 'Cash payment recorded successfully.',
            'data' => $service->recordCashPayment(
                $bill,
                $request->validated(),
                $request->user(),
            ),
        ], 201);
    }
}
