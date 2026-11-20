<?php

namespace App\Modules\Academy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Student;
use App\Modules\Academy\Requests\StudentSearchRequest;
use App\Modules\Academy\Services\StudentSearchService;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    public function index(StudentSearchRequest $request, StudentSearchService $students): JsonResponse
    {
        $filters = $request->validated();
        $paginator = $students->query($filters)
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString()
            ->through(fn (Student $student): array => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'full_name' => $student->full_name,
                'phone' => $student->phone,
                'phone_numbers' => $student->phone_numbers,
                'status' => $student->status,
                'admission_date' => $student->getRawOriginal('admission_date'),
                'admission_branch' => $student->admissionBranch ? [
                    'id' => $student->admissionBranch->id,
                    'name' => $student->admissionBranch->name,
                ] : null,
                'latest_enrollment' => $student->latestEnrollment ? [
                    'id' => $student->latestEnrollment->id,
                    'enrollment_code' => $student->latestEnrollment->enrollment_code,
                    'status' => $student->latestEnrollment->status,
                    'enrollment_date' => $student->latestEnrollment->getRawOriginal('enrollment_date'),
                    'course' => $student->latestEnrollment->course,
                    'branch' => $student->latestEnrollment->branch,
                    'batch' => $student->latestEnrollment->batch,
                ] : null,
            ]);
        $response = [
            'data' => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'links' => $paginator->linkCollection(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];

        if ($request->boolean('include_filters')) {
            $response['filters'] = $students->filterOptions();
        }

        return response()->json($response);
    }

    public function show(string $studentCode, StudentSearchService $students): JsonResponse
    {
        return response()->json(['data' => $students->findWithDetailsByCode($studentCode)]);
    }
}
