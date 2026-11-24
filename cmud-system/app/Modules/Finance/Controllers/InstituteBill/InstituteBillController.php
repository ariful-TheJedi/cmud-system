<?php

namespace App\Modules\Finance\Controllers\InstituteBill;

use App\Http\Controllers\Controller;
use App\Models\Finance\InstituteExpense;
use App\Modules\Finance\Requests\InstituteBill\InstituteBillItemSearchRequest;
use App\Modules\Finance\Requests\InstituteBill\InstituteBillIndexRequest;
use App\Modules\Finance\Requests\InstituteBill\InstituteBillStoreRequest;
use App\Modules\Finance\Services\InstituteBill\InstituteBillNumberPolicy;
use App\Modules\Finance\Services\InstituteBill\InstituteBillService;
use Illuminate\Http\JsonResponse;

/** HTTP adapter for Institute Billing register, details, and creation. */
class InstituteBillController extends Controller
{
    public function index(InstituteBillIndexRequest $request, InstituteBillService $service): JsonResponse
    {
        $result = $service->list($request->validated());

        return response()->json([
            'data' => array_map(fn (InstituteExpense $bill): array => $service->show($bill), $result['bills']->items()),
            'meta' => [
                'current_page' => $result['bills']->currentPage(),
                'last_page' => $result['bills']->lastPage(),
                'per_page' => $result['bills']->perPage(),
                'total' => $result['bills']->total(),
            ],
        ]);
    }

    public function options(InstituteBillService $service): JsonResponse
    {
        return response()->json(['data' => $service->formOptions()]);
    }

    public function billingItems(InstituteBillItemSearchRequest $request, InstituteBillService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->searchBillingItems((string) ($request->validated()['search'] ?? '')),
        ]);
    }

    public function show(InstituteExpense $bill, InstituteBillService $service): JsonResponse
    {
        return response()->json(['data' => $service->show($bill)]);
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
                (int) $request->user()->getAuthIdentifier(),
                $numberPolicy,
            ),
        ], 201);
    }
}
