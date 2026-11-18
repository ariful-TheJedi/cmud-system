<?php

namespace App\Modules\Academy\Services;

use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Owns persistence and relationship synchronization for the Academy catalog.
 * Request parsing and HTTP response formatting stay in the controller.
 */
class AcademyCatalogService
{
    /** Return a paginated course list with the catalog relationships used by callers. */
    public function courses(int $perPage = 25): LengthAwarePaginator
    {
        return Course::query()
            ->with(['institute:id,name', 'subjects:id,course_id,code,name', 'batches:id,course_id,branch_id,code,name,status'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    /** Return a paginated batch list with its course and branch labels. */
    public function batches(): LengthAwarePaginator
    {
        return Batch::query()
            ->with(['course:id,code,name', 'branch:id,name'])
            ->orderByDesc('starts_on')
            ->paginate(25);
    }

    /** Return paginated course modules with their single owning course. */
    public function subjects(int $perPage = 25): LengthAwarePaginator
    {
        return Subject::query()
            ->with('course:id,code,name')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function createCourse(array $data): Course
    {
        return DB::transaction(function () use ($data): Course {
            // Course creation is independent; modules are assigned through their course_id.
            $course = Course::query()->create($data);

            return $course->load(['institute:id,name', 'subjects:id,course_id,code,name']);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateCourse(Course $course, array $data): Course
    {
        return DB::transaction(function () use ($course, $data): Course {
            $course->update($data);

            return $course->refresh()->load(['institute:id,name', 'subjects:id,course_id,code,name']);
        });
    }

    /**
     * Delete an unused course and its catalog-only links.
     *
     * @return bool False when academic or financial history still references it.
     */
    public function deleteCourse(Course $course): bool
    {
        if ($course->stages()->exists() || $course->batches()->exists() || $course->enrollments()->exists()
            || $course->certificateTemplates()->exists() || $course->communicationGroups()->exists()
            || $course->priceVersions()->exists() || $course->studentExpensePriceVersions()->exists()
            || $course->subjects()->exists()) {
            return false;
        }

        DB::transaction(function () use ($course): void {
            $course->delete();
        });

        return true;
    }

    /** @param array<string, mixed> $data */
    public function createBatch(array $data): Batch
    {
        return Batch::query()->create($data)->load(['course:id,code,name', 'branch:id,name']);
    }

    /** @param array<string, mixed> $data */
    public function updateBatch(Batch $batch, array $data): Batch
    {
        $batch->update($data);

        return $batch->refresh()->load(['course:id,code,name', 'branch:id,name']);
    }

    /** Return false when the batch is referenced by academic records or groups. */
    public function deleteBatch(Batch $batch): bool
    {
        if ($batch->enrollments()->exists() || $batch->studentStageRecords()->exists() || $batch->communicationGroups()->exists()) {
            return false;
        }

        $batch->delete();

        return true;
    }

    /** @param array<string, mixed> $data */
    public function createSubject(array $data): Subject
    {
        $subject = Subject::query()->create($data);

        return $subject->load('course:id,code,name');
    }

    /** @param array<string, mixed> $data */
    public function updateSubject(Subject $subject, array $data): Subject
    {
        $subject->update($data);

        return $subject->refresh()->load('course:id,code,name');
    }

    public function deleteSubject(Subject $subject): void
    {
        DB::transaction(function () use ($subject): void {
            $subject->delete();
        });
    }
}
