<?php

namespace App\Modules\Academy\Controllers\Communications;

use App\Http\Controllers\Controller;
use App\Models\Academy\StudentNotification;
use App\Modules\Academy\Requests\StudentNotificationRequest;
use App\Modules\Academy\Services\Communications\StudentNotificationService;
use App\Modules\Academy\Services\Students\StudentSearchService;
use App\Modules\Finance\Services\StudentBill\StudentFinancialOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentNotificationController extends Controller
{
    public function index(
        Request $request,
        string $studentCode,
        StudentSearchService $students,
        StudentNotificationService $notifications,
    ): JsonResponse {
        return response()->json(
            $notifications->list($students->findByCode($studentCode), $request->integer('per_page', 25)),
        );
    }

    public function store(
        StudentNotificationRequest $request,
        string $studentCode,
        StudentSearchService $students,
        StudentNotificationService $notifications,
    ): JsonResponse {
        return response()->json([
            'data' => $notifications->create(
                $students->findByCode($studentCode),
                $request->validated(),
            ),
        ], 201);
    }

    public function synchronize(
        string $studentCode,
        StudentSearchService $students,
        StudentNotificationService $notifications,
        StudentFinancialOverviewService $finances,
    ): JsonResponse {
        return response()->json([
            'data' => $notifications->synchronize(
                $students->findByCode($studentCode),
                $finances,
            ),
        ]);
    }

    public function markRead(
        string $studentCode,
        StudentNotification $notification,
        StudentSearchService $students,
        StudentNotificationService $notifications,
    ): JsonResponse {
        return response()->json([
            'data' => $notifications->markRead($students->findByCode($studentCode), $notification),
        ]);
    }
}
