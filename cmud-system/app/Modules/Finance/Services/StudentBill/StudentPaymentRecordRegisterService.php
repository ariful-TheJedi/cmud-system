<?php

namespace App\Modules\Finance\Services\StudentBill;

use App\Models\Security\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** List individual tuition and expense payments while enforcing the viewer's branch scope. */
class StudentPaymentRecordRegisterService
{
    /**
     * @param  array{search?: string|null, course_id?: int|null, batch_id?: int|null, invoice_id?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
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

    private function tuitionPayments(User $viewer, array $filters): Builder
    {
        $query = DB::table('student_tuition_payments as payment')
            ->join('student_tuitions as tuition', 'tuition.id', '=', 'payment.student_tuition_id')
            ->join('enrollments as enrollment', 'enrollment.id', '=', 'tuition.enrollment_id')
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
                'tuition.id as charge_id',
                'tuition.tuition_number as charge_number',
                'tuition.issued_on as charge_issued_on',
            ])
            ->selectRaw("'Tuition' as charge_category, 'Course tuition' as charge_description")
            ->selectRaw('CASE WHEN tuition.waiver_approved_at IS NOT NULL AND tuition.waiver_approved_by IS NOT NULL THEN CASE WHEN tuition.original_amount > tuition.waiver_amount THEN tuition.original_amount - tuition.waiver_amount ELSE 0 END ELSE tuition.original_amount END as charge_total')
            ->selectSub(
                DB::table('student_tuition_payments as prior_payment')
                    ->selectRaw('COALESCE(SUM(prior_payment.amount), 0)')
                    ->whereColumn('prior_payment.student_tuition_id', 'tuition.id')
                    ->where('prior_payment.status', 'confirmed')
                    ->where(function (Builder $query): void {
                        $query->whereColumn('prior_payment.paid_at', '<', 'payment.paid_at')
                            ->orWhere(function (Builder $sameTime): void {
                                $sameTime->whereColumn('prior_payment.paid_at', '=', 'payment.paid_at')
                                    ->whereColumn('prior_payment.id', '<=', 'payment.id');
                            });
                    }),
                'paid_to_date',
            );

        $this->applyViewerScope($query, $viewer);
        $this->applyFilters($query, $filters, 'tuition.tuition_number');

        return $query;
    }

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
                'expense.id as charge_id',
                'expense.expense_number as charge_number',
                'expense.issued_on as charge_issued_on',
                'expense_type.name as charge_category',
                'expense.description as charge_description',
            ])
            ->selectRaw('ROUND(CASE WHEN expense.quantity * expense.unit_amount - CASE WHEN expense.waiver_approved_at IS NOT NULL AND expense.waiver_approved_by IS NOT NULL THEN expense.waiver_amount ELSE 0 END < 0 THEN 0 ELSE expense.quantity * expense.unit_amount - CASE WHEN expense.waiver_approved_at IS NOT NULL AND expense.waiver_approved_by IS NOT NULL THEN expense.waiver_amount ELSE 0 END END, 2) as charge_total')
            ->selectSub(
                DB::table('student_other_payments as prior_payment')
                    ->selectRaw('COALESCE(SUM(prior_payment.amount), 0)')
                    ->whereColumn('prior_payment.student_expense_id', 'expense.id')
                    ->where('prior_payment.status', 'confirmed')
                    ->where(function (Builder $query): void {
                        $query->whereColumn('prior_payment.paid_at', '<', 'payment.paid_at')
                            ->orWhere(function (Builder $sameTime): void {
                                $sameTime->whereColumn('prior_payment.paid_at', '=', 'payment.paid_at')
                                    ->whereColumn('prior_payment.id', '<=', 'payment.id');
                            });
                    }),
                'paid_to_date',
            );

        $this->applyViewerScope($query, $viewer);
        $this->applyFilters($query, $filters, 'expense.expense_number');

        return $query;
    }

    private function applyFilters(Builder $query, array $filters, string $chargeNumberColumn): void
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
            $query->where('batch.id', $filters['batch_id']);
        }
        if (($filters['invoice_id'] ?? '') !== '') {
            $invoiceId = trim((string) $filters['invoice_id']);
            $query->where(function (Builder $scope) use ($invoiceId, $chargeNumberColumn): void {
                $scope->where($chargeNumberColumn, 'like', '%'.$invoiceId.'%')
                    ->orWhere('payment.payment_number', 'like', '%'.$invoiceId.'%');
            });
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

    /** Format one payment row and its associated charge data for the record list and receipt. */
    private function formatRecord(object $row): array
    {
        $paid = $this->toCents(data_get($row, 'paid_to_date'));
        $chargeTotal = max(0, $this->toCents(data_get($row, 'charge_total')));
        $chargeDue = max(0, $chargeTotal - $paid);
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
            'charge' => [
                'id' => (int) data_get($row, 'charge_id'),
                'kind' => $source,
                'number' => data_get($row, 'charge_number'),
                'category' => data_get($row, 'charge_category') ?? 'Other expense',
                'description' => data_get($row, 'charge_description'),
                'issued_on' => (string) data_get($row, 'charge_issued_on'),
                'amount' => number_format($chargeTotal / 100, 2, '.', ''),
                'paid' => number_format($paid / 100, 2, '.', ''),
                'due' => number_format($chargeDue / 100, 2, '.', ''),
            ],
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
