<?php

namespace App\Modules\Academy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\CommunicationGroup;
use App\Modules\Academy\Requests\GroupMembershipRequest;
use App\Modules\Academy\Requests\StudentCommunicationRequest;
use App\Modules\Academy\Services\EnrollmentService\AcademicProgressService;
use App\Modules\Academy\Services\StudentCommunicationService;
use App\Modules\Academy\Services\StudentSearchService;
use App\Modules\Finance\Services\StudentFinancialOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentCommunicationController extends Controller
{
    public function index(
        Request $request,
        string $studentCode,
        StudentSearchService $students,
        StudentCommunicationService $communications,
    ): JsonResponse {
        return response()->json(
            $communications->history($students->findByCode($studentCode), $request->integer('per_page', 25)),
        );
    }

    public function store(
        StudentCommunicationRequest $request,
        string $studentCode,
        StudentSearchService $students,
        StudentCommunicationService $communications,
    ): JsonResponse {
        $communication = $communications->record(
            $students->findByCode($studentCode),
            $request->validated(),
        );

        return response()->json(['data' => $communication], 201);
    }

    public function context(
        string $studentCode,
        StudentSearchService $students,
        StudentCommunicationService $communications,
        AcademicProgressService $academics,
        StudentFinancialOverviewService $finances,
    ): JsonResponse {
        return response()->json([
            'data' => $communications->contactContext(
                $students->findByCode($studentCode),
                $academics,
                $finances,
            ),
        ]);
    }

    public function memberships(
        Request $request,
        string $studentCode,
        StudentSearchService $students,
        StudentCommunicationService $communications,
    ): JsonResponse {
        return response()->json(
            $communications->groupMembershipHistory($students->findByCode($studentCode), $request->integer('per_page', 25)),
        );
    }

    public function availableGroups(
        string $studentCode,
        StudentSearchService $students,
        StudentCommunicationService $communications,
    ): JsonResponse {
        return response()->json([
            'data' => $communications->availableGroups($students->findByCode($studentCode)),
        ]);
    }

    public function joinGroup(
        GroupMembershipRequest $request,
        string $studentCode,
        CommunicationGroup $group,
        StudentSearchService $students,
        StudentCommunicationService $communications,
    ): JsonResponse {
        return response()->json([
            'data' => $communications->joinGroup(
                $students->findByCode($studentCode),
                $group,
                $request->validated('notes'),
            ),
        ], 201);
    }

    public function leaveGroup(
        GroupMembershipRequest $request,
        string $studentCode,
        CommunicationGroup $group,
        StudentSearchService $students,
        StudentCommunicationService $communications,
    ): JsonResponse {
        return response()->json([
            'data' => $communications->leaveGroup(
                $students->findByCode($studentCode),
                $group,
                $request->validated('notes'),
            ),
        ]);
    }
}
