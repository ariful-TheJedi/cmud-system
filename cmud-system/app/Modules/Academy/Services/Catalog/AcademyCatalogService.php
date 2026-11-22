<?php

namespace App\Modules\Academy\Services\Catalog;

use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\CourseStage;
use App\Models\Academy\Subject;
use App\Models\Institute\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns persistence and relationship synchronization for the Academy catalog.
 * Request parsing and HTTP response formatting stay in the controller.
 */
class AcademyCatalogService
{
    /** Return a paginated course list with the catalog relationships used by callers. */
    /** @return LengthAwarePaginator<int, Course> */
    public function courses(int $perPage = 25): LengthAwarePaginator
    {
        return Course::query()
            ->with(['institute:id,name', 'subjects:id,course_id,code,name', 'batches:id,course_id,branch_id,code,name,status'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function courseDetails(Course $course): Course
    {
        return $course->load([
            'institute:id,name',
            'subjects:id,course_id,code,name',
            'stages:id,course_id,code,name,sequence_number,duration_months,is_mandatory,requires_exam,is_active',
            'batches:id,course_id,branch_id,code,name,status,starts_on,capacity',
            'batches.branch:id,name',
        ]);
    }

    /** Return a paginated batch list with its course and branch labels. */
    /** @return LengthAwarePaginator<int, Batch> */
    public function batches(): LengthAwarePaginator
    {
        return Batch::query()
            ->with(['course:id,code,name', 'branch:id,name'])
            ->orderByDesc('starts_on')
            ->paginate(25);
    }

    public function batchDetails(Batch $batch): Batch
    {
        return $batch->load(['course:id,code,name', 'branch:id,name']);
    }

    /** @return LengthAwarePaginator<int, CourseStage> */
    public function stages(Course $course): LengthAwarePaginator
    {
        return $course->stages()
            ->orderBy('sequence_number')
            ->paginate(100);
    }

    public function stageDetails(Course $course, CourseStage $stage): CourseStage
    {
        $this->assertStageBelongsToCourse($course, $stage);

        return $stage->load('course:id,code,name');
    }

    /** Return paginated course modules with their single owning course. */
    /** @return LengthAwarePaginator<int, Subject> */
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
            $status = $data['status'] ?? (($data['is_active'] ?? true) ? 'active' : 'inactive');
            $data['status'] = $status;
            $data['is_active'] = $status === 'active';
            $data['is_available_for_enrollment'] = (bool) ($data['is_available_for_enrollment'] ?? $status === 'active');

            $course = Course::query()->create($data);

            return $course->load(['institute:id,name', 'subjects:id,course_id,code,name']);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateCourse(Course $course, array $data): Course
    {
        return DB::transaction(function () use ($course, $data): Course {
            if (isset($data['status'])) {
                $data['is_active'] = $data['status'] === 'active';

                if ($data['status'] === 'discontinued') {
                    $data['is_available_for_enrollment'] = false;
                }
            } elseif (array_key_exists('is_active', $data)) {
                $data['status'] = $data['is_active'] ? 'active' : 'inactive';
            }

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
        $this->assertBatchBranchBelongsToCourse((int) $data['course_id'], (int) $data['branch_id']);

        return Batch::query()->create($data)->load(['course:id,code,name', 'branch:id,name']);
    }

    /** @param array<string, mixed> $data */
    public function updateBatch(Batch $batch, array $data): Batch
    {
        $courseId = (int) ($data['course_id'] ?? $batch->course_id);
        $branchId = (int) ($data['branch_id'] ?? $batch->branch_id);
        $this->assertBatchBranchBelongsToCourse($courseId, $branchId);

        if ($batch->enrollments()->exists()
            && ($courseId !== (int) $batch->course_id || $branchId !== (int) $batch->branch_id)) {
            throw ValidationException::withMessages([
                'batch_id' => 'A batch with enrollments cannot be moved to another course or branch.',
            ]);
        }

        if (isset($data['capacity']) && (int) $data['capacity'] > 0) {
            $activeCount = $batch->enrollments()
                ->whereIn('status', ['enrolled', 'ongoing', 'on_hold'])
                ->count();

            if ((int) $data['capacity'] < $activeCount) {
                throw ValidationException::withMessages([
                    'capacity' => 'Capacity cannot be lower than the current active enrollment count.',
                ]);
            }
        }

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
    public function createStage(Course $course, array $data): CourseStage
    {
        return DB::transaction(function () use ($course, $data): CourseStage {
            Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($course->enrollments()->exists()) {
                throw ValidationException::withMessages([
                    'course_id' => 'Academic stages cannot be added after students are enrolled in the course.',
                ]);
            }
            $this->assertStageSequenceAvailable($course, (int) $data['sequence_number']);

            return $course->stages()->create($data);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateStage(CourseStage $stage, array $data): CourseStage
    {
        return DB::transaction(function () use ($stage, $data): CourseStage {
            $course = Course::query()->whereKey($stage->course_id)->lockForUpdate()->firstOrFail();
            $structureFields = ['code', 'name', 'sequence_number', 'duration_months', 'is_mandatory', 'requires_exam', 'is_active'];
            if ($course->enrollments()->exists()
                && collect($structureFields)->contains(
                    fn (string $field): bool => array_key_exists($field, $data)
                        && $data[$field] !== $stage->getAttribute($field),
                )) {
                throw ValidationException::withMessages([
                    'stage' => 'Academic stage structure cannot change after students are enrolled in the course.',
                ]);
            }

            if (isset($data['sequence_number'])) {
                $this->assertStageSequenceAvailable(
                    $course,
                    (int) $data['sequence_number'],
                    $stage->id,
                );
            }

            $stage->update($data);

            return $stage->refresh();
        });
    }

    /** Keep stages referenced by student records; inactive stages no longer apply to new progress. */
    public function deleteStage(CourseStage $stage): bool
    {
        if ($stage->studentStageRecords()->exists() || $stage->course->enrollments()->exists()) {
            return false;
        }

        $stage->delete();

        return true;
    }

    public function assertStageBelongsToCourse(Course $course, CourseStage $stage): void
    {
        if ((int) $stage->course_id !== (int) $course->id) {
            throw ValidationException::withMessages([
                'stage' => 'The stage does not belong to this course.',
            ]);
        }
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

    private function assertBatchBranchBelongsToCourse(int $courseId, int $branchId): void
    {
        $courseInstituteId = Course::query()->whereKey($courseId)->value('institute_id');
        $branchInstituteId = Branch::query()->whereKey($branchId)->value('institute_id');

        if ($courseInstituteId === null || $branchInstituteId === null
            || (int) $courseInstituteId !== (int) $branchInstituteId) {
            throw ValidationException::withMessages([
                'branch_id' => 'The selected branch must belong to the course institute.',
            ]);
        }
    }

    private function assertStageSequenceAvailable(Course $course, int $sequenceNumber, ?int $ignoreStageId = null): void
    {
        $stages = $course->stages()->where('sequence_number', $sequenceNumber);

        if ($ignoreStageId !== null) {
            $stages->whereKeyNot($ignoreStageId);
        }

        if ($stages->exists()) {
            throw ValidationException::withMessages([
                'sequence_number' => 'Each course stage must have a unique sequence number within its course.',
            ]);
        }
    }
}
