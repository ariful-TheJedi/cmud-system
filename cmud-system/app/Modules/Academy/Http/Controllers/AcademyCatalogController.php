<?php

namespace App\Modules\Academy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\Subject;
use App\Models\Institute\Branch;
use App\Modules\Academy\Services\AcademyCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

/**
 * Validates HTTP input and formats responses for Academy catalog operations.
 * Catalog queries and writes are delegated to AcademyCatalogService.
 */
class AcademyCatalogController extends Controller
{
    /** List courses with pagination and the relationships needed by the catalog UI. */
    public function courses(Request $request, AcademyCatalogService $catalog): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 25), 1), 500);

        return response()->json($catalog->courses($perPage));
    }

    /** Return one course with its modules and batches. */
    public function showCourse(Course $course): JsonResponse
    {
        return response()->json(['data' => $course->load(['institute:id,name', 'subjects:id,course_id,code,name', 'batches:id,course_id,branch_id,code,name,status,starts_on,capacity', 'batches.branch:id,name'])]);
    }

    /** Validate and create a course; modules are created through the subject endpoints. */
    public function storeCourse(Request $request, AcademyCatalogService $catalog): JsonResponse
    {
        $data = $request->validate([
            'institute_id' => ['required', 'integer', 'exists:institutes,id'],
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:65535'],
            'completion_period_months' => ['required', 'integer', 'min:1', 'max:65535'],
            'course_mode' => ['required', Rule::in(['online', 'offline', 'dual'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => $catalog->createCourse($data)], 201);
    }

    /** Update supplied course fields without changing its owned modules. */
    public function updateCourse(Request $request, Course $course, AcademyCatalogService $catalog): JsonResponse
    {
        $data = $request->validate([
            'institute_id' => ['sometimes', 'integer', 'exists:institutes,id'],
            'code' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'duration_months' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'completion_period_months' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'course_mode' => ['sometimes', Rule::in(['online', 'offline', 'dual'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => $catalog->updateCourse($course, $data)]);
    }

    /** Delete a course only when it has no dependent academic or financial records. */
    public function destroyCourse(Course $course, AcademyCatalogService $catalog): JsonResponse
    {
        if (! $catalog->deleteCourse($course)) {
            return response()->json(['message' => 'This course is in use and cannot be deleted.'], 409);
        }

        return response()->json(['message' => 'Course deleted.']);
    }

    /** List batches with course and branch labels. */
    public function batches(AcademyCatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->batches());
    }

    /** Return one batch with its course and branch. */
    public function showBatch(Batch $batch): JsonResponse
    {
        return response()->json(['data' => $batch->load(['course:id,code,name', 'branch:id,name'])]);
    }

    /** Validate and create a batch. */
    public function storeBatch(Request $request, AcademyCatalogService $catalog): JsonResponse
    {
        $data = $request->validate($this->batchRules());
        $this->ensureBatchBranchMatchesCourse($data['course_id'], $data['branch_id']);

        return response()->json(['data' => $catalog->createBatch($data)], 201);
    }

    /** Validate and update only the supplied batch fields. */
    public function updateBatch(Request $request, Batch $batch, AcademyCatalogService $catalog): JsonResponse
    {
        $data = $request->validate($this->batchRules(true));
        $this->ensureBatchBranchMatchesCourse(
            $data['course_id'] ?? $batch->course_id,
            $data['branch_id'] ?? $batch->branch_id,
        );

        return response()->json(['data' => $catalog->updateBatch($batch, $data)]);
    }

    /** Delete a batch only when no enrollments, stage records, or groups use it. */
    public function destroyBatch(Batch $batch, AcademyCatalogService $catalog): JsonResponse
    {
        if (! $catalog->deleteBatch($batch)) {
            return response()->json(['message' => 'This batch is in use and cannot be deleted.'], 409);
        }

        return response()->json(['message' => 'Batch deleted.']);
    }

    /** List course modules with their owning course. */
    public function subjects(Request $request, AcademyCatalogService $catalog): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 25), 1), 500);

        return response()->json($catalog->subjects($perPage));
    }

    /** Return one module with its course and assigned teachers. */
    public function showSubject(Subject $subject): JsonResponse
    {
        return response()->json(['data' => $subject->load(['course:id,code,name', 'teachers:id,teacher_code,full_name'])]);
    }

    /** Validate and create a module owned by one course. */
    public function storeSubject(Request $request, AcademyCatalogService $catalog): JsonResponse
    {
        $data = $request->validate($this->subjectRules());

        return response()->json(['data' => $catalog->createSubject($data)], 201);
    }

    /** Update module fields, including its single owning course. */
    public function updateSubject(Request $request, Subject $subject, AcademyCatalogService $catalog): JsonResponse
    {
        $data = $request->validate($this->subjectRules(true, $subject));

        return response()->json(['data' => $catalog->updateSubject($subject, $data)]);
    }

    /** Delete a module and its teacher assignment pivot rows. */
    public function destroySubject(Subject $subject, AcademyCatalogService $catalog): JsonResponse
    {
        $catalog->deleteSubject($subject);

        return response()->json(['message' => 'Subject deleted.']);
    }

    /**
     * Build create/update validation rules for batch fields.
     * The partial mode permits updating only supplied fields while preserving date consistency checks.
     *
     * @return array<string, array<int, mixed>>
     */
    private function batchRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'course_id' => [$required, 'integer', 'exists:courses,id'],
            'branch_id' => [$required, 'integer', 'exists:branches,id'],
            'code' => [$required, 'string', 'max:255'],
            'name' => [$required, 'string', 'max:255'],
            'admission_opens_on' => ['sometimes', 'nullable', 'date'],
            'admission_closes_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:admission_opens_on'],
            'starts_on' => [$required, 'date'],
            'expected_ends_on' => [$required, 'date', 'after_or_equal:starts_on'],
            'actual_ends_on' => ['sometimes', 'nullable', 'date'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => [$required, 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * A batch belongs to one course and runs at a branch in that course's institute.
     * The schema enforces both foreign keys; this adds the cross-table institute rule.
     *
     * @throws ValidationException When the chosen branch belongs to another institute.
     */
    private function ensureBatchBranchMatchesCourse(int|string $courseId, int|string $branchId): void
    {
        $courseInstituteId = Course::query()->whereKey($courseId)->value('institute_id');
        $branchInstituteId = Branch::query()->whereKey($branchId)->value('institute_id');

        if ((int) $courseInstituteId !== (int) $branchInstituteId) {
            throw ValidationException::withMessages([
                'branch_id' => 'The selected branch must belong to the course institute.',
            ]);
        }
    }

    /**
     * Build subject validation rules and ignore the current record during code uniqueness checks.
     *
     * @return array<string, array<int, mixed>>
     */
    private function subjectRules(bool $partial = false, ?Subject $subject = null): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:255', Rule::unique('subjects', 'code')->ignore($subject?->id)],
            'course_id' => [$required, 'integer', 'exists:courses,id'],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
