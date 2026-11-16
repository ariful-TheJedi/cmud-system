<?php

namespace App\Modules\Academy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\CourseStage;
use App\Models\Academy\Student;
use App\Models\Institute\Branch;
use App\Modules\Academy\Http\Requests\StudentSearchRequest;
use App\Modules\Academy\Http\Resources\StudentDetailResource;
use App\Modules\Academy\Http\Resources\StudentResource;
use App\Modules\Academy\Services\StudentSearchService;

class StudentController extends Controller
{
    public function index(
        StudentSearchRequest $request,
        StudentSearchService $search,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $filters = $request->validated();
        $students = $search->query($filters)
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        $resource = StudentResource::collection($students);

        // Lookup data is optional so API clients that only need student results
        // do not pay for filter-option queries on every request.
        if (! $request->boolean('include_filters')) {
            return $resource;
        }

        return $resource->additional([
            'filters' => [
                'courses' => Course::query()->orderBy('name')->get(['id', 'name']),
                'batches' => Batch::query()->orderBy('name')->get(['id', 'name', 'course_id']),
                'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'stages' => CourseStage::query()->where('is_active', true)->orderBy('sequence_number')->get(['id', 'name', 'course_id']),
            ],
        ]);
    }

    public function show(string $studentCode): StudentDetailResource
    {
        $student = Student::query()
            ->where('student_code', $studentCode)
            ->firstOrFail();

        $student->load([
            'institute:id,name',
            'admissionBranch:id,name',
            'medicalCollege:id,name',
            'enrollments' => fn ($query) => $query->orderByDesc('id'),
            'enrollments.course:id,code,name',
            'enrollments.branch:id,name',
            'enrollments.batch:id,name',
            'enrollments.tuition.payments' => fn ($query) => $query->orderByDesc('paid_at'),
            'enrollments.tuition.payments.paymentMethod:id,name',
            'expenses' => fn ($query) => $query->orderByDesc('id'),
            'expenses.expenseType:id,name',
            'expenses.branch:id,name',
            'expenses.payments' => fn ($query) => $query->orderByDesc('paid_at'),
            'expenses.payments.paymentMethod:id,name',
        ]);

        return new StudentDetailResource($student);
    }
}
