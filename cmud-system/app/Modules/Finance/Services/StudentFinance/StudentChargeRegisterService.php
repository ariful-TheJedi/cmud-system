<?php

namespace App\Modules\Finance\Services\StudentFinance;

use App\Models\Security\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Provide a paginated student-charge register limited to the viewer's branch scope. */
class StudentChargeRegisterService
{
    /**
     * @param  array{search?: string|null, course_id?: int|null, batch_id?: int|null, invoice_id?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function index(User $viewer, array $filters): LengthAwarePaginator
    {
        $tuition = $this->tuitionCharges($viewer, $filters);
        $expenses = $this->expenseCharges($viewer, $filters);
        $union = $tuition->unionAll($expenses);

        // Keep outstanding obligations separate from the payment-record register.
        return DB::query()
            ->fromSub($union, 'student_charges')
            ->orderByDesc('issued_on')
            ->orderByDesc('charge_id')
            ->orderBy('charge_type')
            ->paginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100))
            ->through(fn (object $row): array => $this->formatRow($row));
    }

    private function tuitionCharges(User $viewer, array $filters): Builder
    {
        $query = DB::table('enrollments as enrollment')->whereNotNull('enrollment.tuition_original_amount')
            ->join('students as student', 'student.id', '=', 'enrollment.student_id')
            ->leftJoin('courses as course', 'course.id', '=', 'enrollment.course_id')
            ->leftJoin('batches as batch', 'batch.id', '=', 'enrollment.batch_id')
            ->leftJoin('branches as branch', 'branch.id', '=', 'enrollment.branch_id')
            ->selectRaw("'tuition' as charge_type")
            ->addSelect([
                'enrollment.id as charge_id',
                'enrollment.tuition_number as charge_number',
                'enrollment.tuition_issued_on as issued_on',
                'enrollment.tuition_status as obligation_status',
                'enrollment.tuition_cancelled_at as cancelled_at',
                'enrollment.tuition_original_amount as gross_amount',
                'enrollment.tuition_waiver_amount as waiver_amount',
                'enrollment.tuition_waiver_approved_at as waiver_approved_at',
                'enrollment.tuition_waiver_approved_by as waiver_approved_by',
            ])
            ->selectRaw("'Tuition' as expense_type_name, 'Course tuition' as description")
            ->addSelect([
                'student.id as student_pk',
                'student.student_code',
                'student.full_name as student_name',
                'enrollment.id as enrollment_pk',
                'enrollment.enrollment_code',
                'course.name as course_name',
                'batch.name as batch_name',
                'branch.name as branch_name',
            ])
            ->selectSub(
                DB::table('student_tuition_payments as payment')
                    ->selectRaw('COALESCE(SUM(payment.amount), 0)')
                    ->whereColumn('payment.enrollment_id', 'enrollment.id')
                    ->where('payment.status', 'confirmed'),
                'confirmed_paid',
            );

        $this->applyViewerScope($query, $viewer);
        $this->applyRegisterFilters($query, $filters, 'enrollment.tuition_number');

        return $query;
    }

    private function expenseCharges(User $viewer, array $filters): Builder
    {
        $query = DB::table('student_expenses as expense')
            ->leftJoin('student_stage_records as stage_record', 'stage_record.id', '=', 'expense.student_stage_record_id')
            ->join('enrollments as enrollment', function ($join): void {
                // Legacy stage-only charges resolve through their owning stage enrollment.
                $join->on('enrollment.id', '=', DB::raw('COALESCE(expense.enrollment_id, stage_record.enrollment_id)'));
            })
            ->join('students as student', 'student.id', '=', 'enrollment.student_id')
            ->leftJoin('courses as course', 'course.id', '=', 'enrollment.course_id')
            ->leftJoin('batches as batch', 'batch.id', '=', 'enrollment.batch_id')
            ->leftJoin('branches as branch', 'branch.id', '=', 'enrollment.branch_id')
            ->leftJoin('student_expense_types as expense_type', 'expense_type.id', '=', 'expense.student_expense_type_id')
            ->selectRaw("'expense' as charge_type")
            ->addSelect([
                'expense.id as charge_id',
                'expense.expense_number as charge_number',
                'expense.issued_on',
                'expense.status as obligation_status',
                'expense.cancelled_at',
            ])
            ->selectRaw('ROUND(expense.quantity * expense.unit_amount, 2) as gross_amount')
            ->addSelect([
                'expense.waiver_amount',
                'expense.waiver_approved_at',
                'expense.waiver_approved_by',
                'expense_type.name as expense_type_name',
                'expense.description',
                'student.id as student_pk',
                'student.student_code',
                'student.full_name as student_name',
                'enrollment.id as enrollment_pk',
                'enrollment.enrollment_code',
                'course.name as course_name',
                'batch.name as batch_name',
                'branch.name as branch_name',
            ])
            ->selectSub(
                DB::table('student_other_payments as payment')
                    ->selectRaw('COALESCE(SUM(payment.amount), 0)')
                    ->whereColumn('payment.student_expense_id', 'expense.id')
                    ->where('payment.status', 'confirmed'),
                'confirmed_paid',
            );

        $this->applyViewerScope($query, $viewer);
        $this->applyRegisterFilters($query, $filters, 'expense.expense_number');

        return $query;
    }

    /** Apply the same optional search filters to tuition and expense charge queries. */
    private function applyRegisterFilters(Builder $query, array $filters, string $invoiceColumn): void
    {
        if (($filters['search'] ?? '') !== '') {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $scope) use ($search): void {
                $scope->where('student.student_code', 'like', '%'.$search.'%')
                    ->orWhere('student.full_name', 'like', '%'.$search.'%');
            });
        }
        if (! empty($filters['course_id'])) {
            $query->where('course.id', $filters['course_id']);
        }
        if (! empty($filters['batch_id'])) {
            $query->where('batch.id', $filters['batch_id'])
                ->whereColumn('batch.course_id', 'enrollment.course_id');
        }
        if (($filters['invoice_id'] ?? '') !== '') {
            $invoiceId = trim((string) $filters['invoice_id']);
            $query->where(function (Builder $scope) use ($invoiceId, $invoiceColumn): void {
                $scope->where($invoiceColumn, 'like', '%'.$invoiceId.'%')
                    ->orWhere('enrollment.enrollment_code', 'like', '%'.$invoiceId.'%');
            });
        }
    }

    private function applyViewerScope(Builder $query, User $viewer): void
    {
        if (! $viewer->is_super_admin) {
            // A regular account without a branch assignment must not see institute-wide records.
            if ($viewer->branch_id === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('enrollment.branch_id', $viewer->branch_id);
        }
    }

    /** @return array<string, mixed> */
    private function formatRow(object $row): array
    {
        // Query-builder projections are dynamic objects, so data_get safely reads their selected aliases.
        $chargeType = (string) data_get($row, 'charge_type');
        $amount = $this->toCents(data_get($row, 'gross_amount'));
        $waiverApproved = data_get($row, 'waiver_approved_at') !== null
            && data_get($row, 'waiver_approved_by') !== null;
        $waiver = $waiverApproved ? $this->toCents(data_get($row, 'waiver_amount')) : 0;
        $cancelled = data_get($row, 'cancelled_at') !== null
            || data_get($row, 'obligation_status') === 'cancelled';
        $payable = $cancelled ? 0 : max(0, $amount - $waiver);
        $paid = $this->toCents(data_get($row, 'confirmed_paid'));
        $due = max(0, $payable - $paid);

        return [
            'id' => (int) data_get($row, 'charge_id'),
            'kind' => $chargeType,
            'number' => data_get($row, 'charge_number'),
            'category' => $chargeType === 'tuition' ? 'Tuition' : (data_get($row, 'expense_type_name') ?? 'Other expense'),
            'description' => $chargeType === 'tuition' ? 'Course tuition' : data_get($row, 'description'),
            'issued_on' => (string) data_get($row, 'issued_on'),
            'status' => $cancelled ? 'cancelled' : ($due === 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid')),
            'is_cancelled' => $cancelled,
            'amount' => $this->formatCents($amount),
            'approved_waiver' => $this->formatCents($waiver),
            'payable_amount' => $this->formatCents($payable),
            'paid_amount' => $this->formatCents($paid),
            'outstanding_balance' => $this->formatCents($due),
            'payments' => [],
            'student' => [
                'id' => (int) data_get($row, 'student_pk'),
                'student_code' => data_get($row, 'student_code'),
                'full_name' => data_get($row, 'student_name'),
            ],
            'enrollment' => [
                'id' => (int) data_get($row, 'enrollment_pk'),
                'enrollment_code' => data_get($row, 'enrollment_code'),
                'course_name' => data_get($row, 'course_name'),
                'batch_name' => data_get($row, 'batch_name'),
                'branch_name' => data_get($row, 'branch_name'),
            ],
        ];
    }

    private function toCents(mixed $amount): int
    {
        $decimal = trim((string) $amount);
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function formatCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
