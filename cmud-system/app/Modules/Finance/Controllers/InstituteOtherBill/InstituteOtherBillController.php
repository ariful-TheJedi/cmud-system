<?php

namespace App\Modules\Finance\Controllers\InstituteOtherBill;

use App\Http\Controllers\Controller;
use App\Models\Finance\InstituteIncomeBill;
use App\Modules\Finance\Requests\InstituteOtherBill\InstituteOtherBillIndexRequest;
use App\Modules\Finance\Requests\InstituteOtherBill\InstituteOtherBillStoreRequest;
use App\Modules\Finance\Services\InstituteOtherBill\InstituteOtherBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** HTTP adapter for Institute Other Bill CRUD and register search. */
class InstituteOtherBillController extends Controller
{
    public function options(InstituteOtherBillService $service): JsonResponse
    {
        return response()->json(['data' => ['invoice_header' => $service->invoiceHeader()]]);
    }

    public function index(InstituteOtherBillIndexRequest $request, InstituteOtherBillService $service): JsonResponse
    {
        return response()->json($service->list($request->validated(), $request->user()));
    }

    public function show(Request $request, InstituteIncomeBill $bill, InstituteOtherBillService $service): JsonResponse
    {
        return response()->json(['data' => $service->show($bill, $request->user())]);
    }

    public function store(InstituteOtherBillStoreRequest $request, InstituteOtherBillService $service): JsonResponse
    {
        return response()->json([
            'message' => 'Institute other bill created successfully.',
            'data' => $service->create($request->validated(), $request->user()),
        ], Response::HTTP_CREATED);
    }

    public function update(
        InstituteOtherBillStoreRequest $request,
        InstituteIncomeBill $bill,
        InstituteOtherBillService $service,
    ): JsonResponse {
        return response()->json([
            'message' => 'Institute other bill updated successfully.',
            'data' => $service->update($bill, $request->validated(), $request->user()),
        ]);
    }

    public function destroy(Request $request, InstituteIncomeBill $bill, InstituteOtherBillService $service): Response
    {
        $service->delete($bill, $request->user());

        return response()->noContent();
    }
}
