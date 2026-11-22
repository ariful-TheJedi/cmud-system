<?php

namespace App\Modules\Academy\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\CourseStage;
use App\Models\Academy\Subject;
use App\Modules\Academy\Requests\BatchRequest;
use App\Modules\Academy\Requests\CourseRequest;
use App\Modules\Academy\Requests\CourseStageRequest;
use App\Modules\Academy\Requests\SubjectRequest;
use App\Modules\Academy\Services\Catalog\AcademyCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademyCatalogController extends Controller
{
    public function courses(Request $request, AcademyCatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->courses($this->perPage($request)));
    }

    public function showCourse(Course $course, AcademyCatalogService $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->courseDetails($course)]);
    }

    public function storeCourse(CourseRequest $request, AcademyCatalogService $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->createCourse($request->validated())], 201);
    }

    public function updateCourse(
        CourseRequest $request,
        Course $course,
        AcademyCatalogService $catalog,
    ): JsonResponse {
        return response()->json(['data' => $catalog->updateCourse($course, $request->validated())]);
    }

    public function destroyCourse(Course $course, AcademyCatalogService $catalog): JsonResponse
    {
        if (! $catalog->deleteCourse($course)) {
            return response()->json(['message' => 'This course is in use and cannot be deleted.'], 409);
        }

        return response()->json(['message' => 'Course deleted.']);
    }

    public function stages(Course $course, AcademyCatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->stages($course));
    }

    public function showStage(
        Course $course,
        CourseStage $stage,
        AcademyCatalogService $catalog,
    ): JsonResponse {
        return response()->json(['data' => $catalog->stageDetails($course, $stage)]);
    }

    public function storeStage(
        CourseStageRequest $request,
        Course $course,
        AcademyCatalogService $catalog,
    ): JsonResponse {
        return response()->json(['data' => $catalog->createStage($course, $request->validated())], 201);
    }

    public function updateStage(
        CourseStageRequest $request,
        Course $course,
        CourseStage $stage,
        AcademyCatalogService $catalog,
    ): JsonResponse {
        $catalog->assertStageBelongsToCourse($course, $stage);

        return response()->json(['data' => $catalog->updateStage($stage, $request->validated())]);
    }

    public function destroyStage(
        Course $course,
        CourseStage $stage,
        AcademyCatalogService $catalog,
    ): JsonResponse {
        $catalog->assertStageBelongsToCourse($course, $stage);
        if (! $catalog->deleteStage($stage)) {
            return response()->json(['message' => 'This stage is used by student academic records.'], 409);
        }

        return response()->json(['message' => 'Course stage deleted.']);
    }

    public function batches(Request $request, AcademyCatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->batches());
    }

    public function showBatch(Batch $batch, AcademyCatalogService $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->batchDetails($batch)]);
    }

    public function storeBatch(BatchRequest $request, AcademyCatalogService $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->createBatch($request->validated())], 201);
    }

    public function updateBatch(
        BatchRequest $request,
        Batch $batch,
        AcademyCatalogService $catalog,
    ): JsonResponse {
        return response()->json(['data' => $catalog->updateBatch($batch, $request->validated())]);
    }

    public function destroyBatch(Batch $batch, AcademyCatalogService $catalog): JsonResponse
    {
        if (! $catalog->deleteBatch($batch)) {
            return response()->json(['message' => 'This batch is in use and cannot be deleted.'], 409);
        }

        return response()->json(['message' => 'Batch deleted.']);
    }

    public function subjects(Request $request, AcademyCatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->subjects($this->perPage($request)));
    }

    public function showSubject(Subject $subject): JsonResponse
    {
        return response()->json(['data' => $subject->load(['course:id,code,name', 'teachers:id,teacher_code,full_name'])]);
    }

    public function storeSubject(SubjectRequest $request, AcademyCatalogService $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->createSubject($request->validated())], 201);
    }

    public function updateSubject(
        SubjectRequest $request,
        Subject $subject,
        AcademyCatalogService $catalog,
    ): JsonResponse {
        return response()->json(['data' => $catalog->updateSubject($subject, $request->validated())]);
    }

    public function destroySubject(Subject $subject, AcademyCatalogService $catalog): JsonResponse
    {
        $catalog->deleteSubject($subject);

        return response()->json(['message' => 'Subject deleted.']);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 25), 1), 100);
    }
}
