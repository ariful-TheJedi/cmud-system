<?php

namespace App\Modules\Finance\Services\StudentFinance;

use App\Models\Security\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** List individual tuition and expense payments while enforcing the viewer's branch scope. */
class StudentPaymentRegisterService
{
    /** @return array{courses: list<array{id: int, name: string}>, batches: list<array{id: int, name: string}>} */
    public function filterOptions(User $viewer, ?int $courseId = null): array
    {
        $courses = DB::table('enrollments as enrollment')
            ->join('courses as course', 'course.id', '=', 'enrollment.course_id')
            ->select('course.id', 'course.name')
            ->distinct()
            ->orderBy('course.name');
        $this->applyViewerScope($courses, $viewer);

        $batches = DB::table('enrollments as enrollment')
            ->join('batches as batch', 'batch.id', '=', 'enrollment.batch_id')
            ->select('batch.id', 'batch.name')
            ->whereNotNull('enrollment.batch_id')
            ->whereColumn('batch.course_id', 'enrollment.course_id')
            ->when($courseId !== null, fn (Builder $query) => $query->where('batch.course_id', $courseId))
            ->distinct()
            ->orderBy('batch.name');
        $this->applyViewerScope($batches, $viewer);

        return [
            'courses' => $courses->get()->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => $row->name])->all(),
            'batches' => $batches->get()->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => $row->name])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<array-key, array<string, mixed>>
     */
    public function index(User $viewer, array $filters): LengthAwarePaginator
    {
        $tuitionPayments = $this->tuitionPayments($viewer, $filters);
        $expensePayments = $this->expensePayments($viewer, $filters);
        $records = $tuitionPayments->unionAll($expensePayments);

        // Paginate payment rows in SQL so one enrollment with many receipts gets one row per receipt.
        return DB::query()
            ->fromSub($records, 'student_payment_records')
            ->orderByDesc('paid_at')
            ->orderByDesc('payment_id')
            ->orderBy('payment_source')
            ->paginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100))
            ->through(fn (object $row): array => $this->formatRecord($row));
    }

    /** @param array<string, mixed> $filters */
    private function tuitionPayments(User $viewer, array $filters): Builder
    {
        $query = DB::table('student_tuition_payments as payment')
            ->join('enrollments as enrollment', 'enrollment.id', '=', 'payment.enrollment_id')
            ->join('students as student', 'student.id', '=', 'enrollment.student_id')
            ->leftJoin('courses as course', 'course.id', '=', 'enrollment.course_id')
            ->leftJoin('batches as batch', 'batch.id', '=', 'enrollment.batch_id')
            ->leftJoin('branches as branch', 'branch.id', '=', 'enrollment.branch_id')
            ->leftJoin('payment_methods as method', 'method.id', '=', 'payment.payment_method_id')
            ->leftJoin('users as recorder', 'recorder.id', '=', 'payment.recorded_by')
            ->selectRaw("'tuition' as payment_source")
            ->addSelect([
                'payment.id as payment_id',
                'payment.payment_number',
                'payment.amount as payment_amount',
                'payment.paid_at',
                'payment.status as payment_status',
                'payment.transaction_reference',
                'method.name as payment_method',
                'recorder.name as recorded_by_name',
                'student.id as student_id',
                'student.student_code',
                'student.full_name as student_name',
                'enrollment.id as enrollment_id',
                'enrollment.enrollment_code',
                'course.name as course_name',
                'batch.name as batch_name',
                'branch.name as branch_name',

            ])
            ->selectRaw("NULL as expense_id, 'Tuition' as category, 'Course tuition payment' as description");

        $this->applyViewerScope($query, $viewer);
        $this->applyFilters($query, $filters);

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function expensePayments(User $viewer, array $filters): Builder
    {
        $query = DB::table('student_other_payments as payment')
            ->join('student_expenses as expense', 'expense.id', '=', 'payment.student_expense_id')
            ->leftJoin('student_stage_records as stage_record', 'stage_record.id', '=', 'expense.student_stage_record_id')
            ->join('enrollments as enrollment', function ($join): void {
                // Legacy stage-only expenses resolve to the enrollment that owns their stage record.
                $join->on('enrollment.id', '=', DB::raw('COALESCE(expense.enrollment_id, stage_record.enrollment_id)'));
            })
            ->join('students as student', 'student.id', '=', 'enrollment.student_id')
            ->leftJoin('courses as course', 'course.id', '=', 'enrollment.course_id')
            ->leftJoin('batches as batch', 'batch.id', '=', 'enrollment.batch_id')
            ->leftJoin('branches as branch', 'branch.id', '=', 'enrollment.branch_id')
            ->leftJoin('payment_methods as method', 'method.id', '=', 'payment.payment_method_id')
            ->leftJoin('users as recorder', 'recorder.id', '=', 'payment.recorded_by')
            ->leftJoin('student_expense_types as expense_type', 'expense_type.id', '=', 'expense.student_expense_type_id')
            ->selectRaw("'expense' as payment_source")
            ->addSelect([
                'payment.id as payment_id',
                'payment.payment_number',
                'payment.amount as payment_amount',
                'payment.paid_at',
                'payment.status as payment_status',
                'payment.transaction_reference',
                'method.name as payment_method',
                'recorder.name as recorded_by_name',
                'student.id as student_id',
                'student.student_code',
                'student.full_name as student_name',
                'enrollment.id as enrollment_id',
                'enrollment.enrollment_code',
                'course.name as course_name',
                'batch.name as batch_name',
                'branch.name as branch_name',
                'expense.id as expense_id',
                'expense_type.name as category',
                'expense.description as description',
            ]);

        $this->applyViewerScope($query, $viewer);
        $this->applyFilters($query, $filters);

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (($filters['search'] ?? '') !== '') {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $scope) use ($search): void {
                $scope->whereRaw('LOWER(student.student_code) LIKE ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereRaw('LOWER(student.full_name) LIKE ?', ['%'.mb_strtolower($search).'%']);
            });
        }
        if (! empty($filters['course_id'])) {
            $query->where('course.id', $filters['course_id']);
        }
        if (! empty($filters['batch_id'])) {
            $query->where('batch.id', $filters['batch_id'])
                ->whereColumn('batch.course_id', 'enrollment.course_id');
        }
        if (! empty($filters['student_id'])) {
            $query->where('student.student_code', trim($filters['student_id']));
        }
        if (! empty($filters['source'])) {
            $query->whereRaw('? = ?', [$filters['source'], $query->from === 'student_tuition_payments as payment' ? 'tuition' : 'expense']);
        }
        if (($filters['invoice_id'] ?? '') !== '') {
            $query->where('payment.payment_number', 'like', '%'.trim($filters['invoice_id']).'%');
        }
    }

    private function applyViewerScope(Builder $query, User $viewer): void
    {
        if ($viewer->is_super_admin) {
            return;
        }
        if ($viewer->branch_id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('enrollment.branch_id', $viewer->branch_id);
    }

    /** Return actual payment data and enrollment context without obligation totals or due calculations. */
    /** @return array<string, mixed> */
    private function formatRecord(object $row): array
    {
        $source = (string) data_get($row, 'payment_source');
        $id = (int) data_get($row, 'payment_id');

        return [
            'id' => $id,
            'payment_key' => $source.'-'.$id,
            'source' => $source,
            'payment_number' => data_get($row, 'payment_number'),
            'amount' => $this->formatCents($this->toCents(data_get($row, 'payment_amount'))),
            'paid_at' => (string) data_get($row, 'paid_at'),
            'status' => data_get($row, 'payment_status'),
            'payment_method' => data_get($row, 'payment_method'),
            'transaction_reference' => data_get($row, 'transaction_reference'),
            'recorded_by_name' => data_get($row, 'recorded_by_name'),
            'student' => [
                'id' => (int) data_get($row, 'student_id'),
                'student_code' => data_get($row, 'student_code'),
                'full_name' => data_get($row, 'student_name'),
            ],
            'enrollment' => [
                'id' => (int) data_get($row, 'enrollment_id'),
                'enrollment_code' => data_get($row, 'enrollment_code'),
                'course_name' => data_get($row, 'course_name'),
                'batch_name' => data_get($row, 'batch_name'),
                'branch_name' => data_get($row, 'branch_name'),
            ],
            'category' => data_get($row, 'category') ?? 'Other payment',
            'description' => data_get($row, 'description'),
            'expense_id' => data_get($row, 'expense_id'),
        ];
    }

    private function toCents(mixed $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim((string) $value), 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function formatCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
