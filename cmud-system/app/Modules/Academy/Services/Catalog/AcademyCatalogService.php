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
    /** Return courses with their reusable direct modules and stage assignments. */
    /** @return LengthAwarePaginator<int, Course> */
    public function courses(int $perPage = 15): LengthAwarePaginator
    {
        $courses = Course::query()
            ->with(['institute:id,name', 'subjects:id,code,name', 'stages.subjects:id,code,name', 'batches:id,course_id,branch_id,code,name,status'])
            ->orderBy('name')
            ->paginate($perPage);

        // The catalog's subjects field represents all modules used by the course, including stage links.
        $courses->getCollection()->each(function (Course $course): void {
            $stageSubjects = $course->stages->flatMap(fn (CourseStage $stage) => $stage->subjects);
            $course->setRelation('subjects', $course->subjects->merge($stageSubjects)->unique('id')->values());
        });

        return $courses;
    }

    public function courseDetails(Course $course): Course
    {
        return $course->load([
            'institute:id,name',
            'subjects:id,code,name',
            'stages:id,course_id,code,name,sequence_number,duration_months,is_mandatory,requires_exam,certificate_enabled,is_active',
            'stages.subjects:id,code,name',
            'batches' => fn ($query) => $query
                ->select('id', 'course_id', 'branch_id', 'code', 'name', 'status', 'starts_on', 'capacity')
                ->withCount(['currentlyEnrolledStudents as enrolled_students_count']),
            'batches.branch:id,name',
        ]);
    }

    /** Return a paginated batch list with its course and branch labels. */
    /** @return LengthAwarePaginator<int, Batch> */
    public function batches(int $perPage = 15): LengthAwarePaginator
    {
        return Batch::query()
            ->with(['course:id,code,name', 'branch:id,name'])
            ->withCount(['currentlyEnrolledStudents as enrolled_students_count'])
            ->orderByDesc('starts_on')
            ->paginate($perPage);
    }

    public function batchDetails(Batch $batch): Batch
    {
        return $batch->load(['course:id,code,name', 'branch:id,name'])
            ->loadCount(['currentlyEnrolledStudents as enrolled_students_count']);
    }

    /** @return LengthAwarePaginator<int, CourseStage> */
    public function stages(Course $course): LengthAwarePaginator
    {
        return $course->stages()
            ->with('subjects:id,code,name')
            ->orderBy('sequence_number')
            ->paginate(100);
    }

    public function stageDetails(Course $course, CourseStage $stage): CourseStage
    {
        $this->assertStageBelongsToCourse($course, $stage);

        return $stage->load(['course:id,code,name', 'subjects:id,code,name']);
    }

    /** Return independent modules with their course and stage assignments. */
    /** @return LengthAwarePaginator<int, Subject> */
    public function subjects(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Subject::query()
            ->when(filled($search), function ($query) use ($search): void {
                $term = '%'.mb_strtolower(trim((string) $search)).'%';

                // Search module details and linked course/stage names across every page.
                $query->where(function ($searchQuery) use ($term): void {
                    $searchQuery
                        ->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$term])
                        ->orWhereHas('courses', fn ($courseQuery) => $courseQuery
                            ->whereRaw('LOWER(courses.name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(courses.code) LIKE ?', [$term]))
                        ->orWhereHas('courseStages', fn ($stageQuery) => $stageQuery
                            ->whereRaw('LOWER(course_stages.name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(course_stages.code) LIKE ?', [$term])
                            ->orWhereHas('course', fn ($courseQuery) => $courseQuery
                                ->whereRaw('LOWER(courses.name) LIKE ?', [$term])
                                ->orWhereRaw('LOWER(courses.code) LIKE ?', [$term])));
                });
            })
            ->with(['courses:id,code,name', 'courseStages:id,course_id,code,name,sequence_number', 'courseStages.course:id,code,name'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function createCourse(array $data): Course
    {
        return DB::transaction(function () use ($data): Course {
            // Keep reusable module links in the course pivot, never on subject rows.
            $subjectIds = $data['subject_ids'] ?? null;
            unset($data['subject_ids']);

            $status = $data['status'] ?? (($data['is_active'] ?? true) ? 'active' : 'inactive');
            $data['status'] = $status;
            $data['is_active'] = $status === 'active';
            $data['is_available_for_enrollment'] = (bool) ($data['is_available_for_enrollment'] ?? $status === 'active');

            $course = Course::query()->create($data);
            if ($subjectIds !== null) {
                $course->subjects()->sync($subjectIds);
            }

            return $course->load(['institute:id,name', 'subjects:id,code,name']);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateCourse(Course $course, array $data): Course
    {
        return DB::transaction(function () use ($course, $data): Course {
            $subjectIds = $data['subject_ids'] ?? null;
            unset($data['subject_ids']);

            if (isset($data['status'])) {
                $data['is_active'] = $data['status'] === 'active';

                if ($data['status'] === 'discontinued') {
                    $data['is_available_for_enrollment'] = false;
                }
            } elseif (array_key_exists('is_active', $data)) {
                $data['status'] = $data['is_active'] ? 'active' : 'inactive';
            }

            $course->update($data);
            if ($subjectIds !== null) {
                $currentSubjectIds = $course->subjects()->pluck('subjects.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
                $requestedSubjectIds = collect($subjectIds)->map(fn ($id) => (int) $id)->sort()->values()->all();
                if ($course->enrollments()->exists() && $currentSubjectIds !== $requestedSubjectIds) {
                    throw ValidationException::withMessages([
                        'subject_ids' => 'Course module assignments cannot change after students are enrolled.',
                    ]);
                }
                $course->subjects()->sync($subjectIds);
            }

            return $course->refresh()->load(['institute:id,name', 'subjects:id,code,name']);
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
            $subjectIds = $data['subject_ids'] ?? [];
            unset($data['subject_ids']);
            Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($course->enrollments()->exists()) {
                throw ValidationException::withMessages([
                    'course_id' => 'Academic stages cannot be added after students are enrolled in the course.',
                ]);
            }
            $this->assertStageSequenceAvailable($course, (int) $data['sequence_number']);

            $stage = $course->stages()->create($data);
            $stage->subjects()->sync($subjectIds);

            return $stage->load('subjects:id,code,name');
        });
    }

    /** @param array<string, mixed> $data */
    public function updateStage(CourseStage $stage, array $data): CourseStage
    {
        return DB::transaction(function () use ($stage, $data): CourseStage {
            $subjectIds = $data['subject_ids'] ?? null;
            unset($data['subject_ids']);
            $course = Course::query()->whereKey($stage->course_id)->lockForUpdate()->firstOrFail();
            $structureFields = ['code', 'name', 'sequence_number', 'duration_months', 'is_mandatory', 'requires_exam', 'is_active'];
            $subjectAssignmentsChanged = $subjectIds !== null
                && $stage->subjects()->pluck('subjects.id')->map(fn ($id) => (int) $id)->sort()->values()->all()
                    !== collect($subjectIds)->map(fn ($id) => (int) $id)->sort()->values()->all();
            $structureChanged = collect($structureFields)->contains(
                    fn (string $field): bool => array_key_exists($field, $data)
                        && $data[$field] !== $stage->getAttribute($field),
                );
            if ($course->enrollments()->exists() && ($structureChanged || $subjectAssignmentsChanged)) {
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
            if ($subjectIds !== null) {
                $stage->subjects()->sync($subjectIds);
            }

            return $stage->refresh()->load('subjects:id,code,name');
        });
    }

    /** Keep stages referenced by student records; inactive stages no longer apply to new progress. */
    public function deleteStage(CourseStage $stage): bool
    {
        if ($stage->studentStageRecords()->exists() || $stage->course->enrollments()->exists()) {
            return false;
        }

        DB::transaction(function () use ($stage): void {
            // Remove stage-module links before deleting the stage protected by pivot foreign keys.
            $stage->subjects()->detach();
            $stage->delete();
        });

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
        return Subject::query()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function updateSubject(Subject $subject, array $data): Subject
    {
        $subject->update($data);

        return $subject->refresh();
    }

    public function deleteSubject(Subject $subject): bool
    {
        if ($subject->courses()->exists() || $subject->courseStages()->exists() || $subject->teachers()->exists()) {
            return false;
        }

        DB::transaction(function () use ($subject): void {
            $subject->delete();
        });

        return true;
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
