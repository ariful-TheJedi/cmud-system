<?php

namespace App\Modules\Academy\Services;

use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\CourseStage;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Institute\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentSearchService
{
    public function findByCode(string $studentCode): Student
    {
        return Student::query()
            ->where('student_code', $studentCode)
            ->firstOrFail();
    }

    public function findWithDetailsByCode(string $studentCode): Student
    {
        return Student::query()
            ->where('student_code', $studentCode)
            ->with([
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
            ])
            ->firstOrFail();
    }

    /**
     * Build a paginated-search query without joining one-to-many records into the
     * student result set. Enrollment filters use EXISTS clauses, so each student
     * remains a single result even when they have several matching enrollments.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Student>
     */
    public function query(array $filters): Builder
    {
        $query = Student::query()->with([
            'admissionBranch:id,name',
            'latestEnrollment' => fn (HasOne $enrollmentQuery) => $this->applyEnrollmentFilters($enrollmentQuery->getQuery(), $filters),
            'latestEnrollment.course:id,name',
            'latestEnrollment.branch:id,name',
            'latestEnrollment.batch:id,name',
        ]);

        foreach (['student_code', 'name', 'phone'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $this->applyTextFilter($query, $field, (string) $filters[$field]);
            }

        }

        if (filled($filters['search'] ?? null)) {
            $term = (string) $filters['search'];
            $query->where(function (Builder $searchQuery) use ($term): void {
                $this->applyTextFilter($searchQuery, 'student_code', $term);
                $searchQuery->orWhere(function (Builder $nameQuery) use ($term): void {
                    $this->applyNameFilter($nameQuery, $term);
                });
                $searchQuery->orWhere(function (Builder $phoneQuery) use ($term): void {
                    $this->applyPhoneFilter($phoneQuery, $term);
                });
            });
        }

        if (isset($filters['student_status'])) {
            $query->where('status', $filters['student_status']);
        }

        if (isset($filters['institute_id'])) {
            $query->where('institute_id', $filters['institute_id']);
        }

        if (isset($filters['admission_date'])) {
            $query->whereDate('admission_date', $filters['admission_date']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('admission_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('admission_date', '<=', $filters['date_to']);
        }

        if (isset($filters['admission_month'])) {
            $query->whereMonth('admission_date', $filters['admission_month']);
        }

        if (isset($filters['admission_year'])) {
            $query->whereYear('admission_date', $filters['admission_year']);
        }

        if (($filters['enrollment_status'] ?? null) === 'none') {
            $query->whereDoesntHave('enrollments');

            if (isset($filters['branch_id'])) {
                $query->where('admission_branch_id', $filters['branch_id']);
            }
        } elseif ($this->hasEnrollmentFilters($filters)) {
            $query->whereHas('enrollments', function (Builder $enrollmentQuery) use ($filters): void {
                $this->applyEnrollmentFilters($enrollmentQuery, $filters);
            });
        } elseif (isset($filters['branch_id'])) {
            // With no enrollment-specific criterion, a branch may be the original
            // admission branch or a branch associated with any enrollment.
            $query->where(function (Builder $branchQuery) use ($filters): void {
                $branchQuery->where('admission_branch_id', $filters['branch_id'])
                    ->orWhereHas('enrollments', fn (Builder $enrollmentQuery) => $enrollmentQuery
                        ->where('branch_id', $filters['branch_id']));
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        return $query
            ->orderBy($sortBy, $sortDirection)
            ->orderBy('id', $sortDirection);
    }

    /** @return array<string, mixed> */
    public function filterOptions(): array
    {
        return [
            'courses' => Course::query()->orderBy('name')->get(['id', 'name']),
            'batches' => Batch::query()->orderBy('name')->get(['id', 'name', 'course_id']),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'stages' => CourseStage::query()
                ->where('is_active', true)
                ->orderBy('sequence_number')
                ->get(['id', 'name', 'course_id']),
        ];
    }

    /** @param Builder<Enrollment> $query
     * @param  array<string, mixed>  $filters
     */
    private function applyEnrollmentFilters(Builder $query, array $filters): void
    {
        foreach (['course_id', 'batch_id', 'branch_id', 'enrollment_status'] as $field) {
            if (isset($filters[$field]) && ! ($field === 'enrollment_status' && $filters[$field] === 'none')) {
                $column = $field === 'enrollment_status' ? 'status' : $field;
                $query->where($column, $filters[$field]);
            }
        }

        if (isset($filters['stage_id'])) {
            $query->whereHas('stageRecords', fn (Builder $stageQuery) => $stageQuery
                ->where('course_stage_id', $filters['stage_id']));
        }
    }

    /** @param array<string, mixed> $filters */
    private function hasEnrollmentFilters(array $filters): bool
    {
        return collect(['course_id', 'batch_id', 'branch_id', 'enrollment_status', 'stage_id'])
            ->contains(fn (string $field): bool => isset($filters[$field]));
    }

    /**
     * Apply a partial, case-insensitive student code, name, or phone match.
     *
     * @param  Builder<Student>  $query
     */
    private function applyTextFilter(Builder $query, string $field, string $value): void
    {
        if ($field === 'name') {
            $this->applyNameFilter($query, $value);

            return;
        }

        if ($field === 'phone') {
            $this->applyPhoneFilter($query, $value);

            return;
        }

        $query->whereRaw('LOWER(student_code) LIKE ?', ['%'.mb_strtolower($value).'%']);
    }

    /**
     * Every supplied name word must occur somewhere in the full name.
     *
     * @param  Builder<Student>  $query
     */
    private function applyNameFilter(Builder $query, string $value): void
    {
        foreach (preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $query->whereRaw('LOWER(full_name) LIKE ?', ['%'.mb_strtolower($word).'%']);
        }
    }

    /**
     * Search all persisted phone representations without loading profile data.
     *
     * @param  Builder<Student>  $query
     */
    private function applyPhoneFilter(Builder $query, string $value): void
    {
        $pattern = '%'.mb_strtolower($value).'%';
        $query->where(function (Builder $phoneQuery) use ($pattern): void {
            $phoneQuery->whereRaw('LOWER(phone) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(alternative_phone, ?)) LIKE ?', ['', $pattern])
                ->orWhereRaw('LOWER(CAST(phone_numbers AS TEXT)) LIKE ?', [$pattern]);
        });
    }
}
