<?php

namespace App\Modules\Finance\Services\StudentFinance;

use App\Models\Academy\Student;
use App\Models\Finance\StudentExpensePayment;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Security\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class StudentPaymentHistoryService
{
    /** @param array<string, mixed> $filters
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function list(Student $student, array $filters, User $viewer): array
    {
        $rows = collect([
            ...$this->tuitionPayments($student, $filters, $viewer),
            ...$this->expensePayments($student, $filters, $viewer),
        ])->sortByDesc('paid_at')->values();
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);

        return [
            'data' => $rows->forPage($page, $perPage)->values()->all(),
            'total' => $rows->count(),
        ];
    }

    /** @return array{valid_payment_count: int, valid_amount: string} */
    public function summary(Student $student, User $viewer): array
    {
        $rows = collect([
            ...$this->tuitionPayments($student, [], $viewer),
            ...$this->expensePayments($student, [], $viewer),
        ])->where('is_valid', true);
        $amountCents = $rows->sum(fn (array $payment): int => $this->toCents($payment['amount']));

        return [
            'valid_payment_count' => $rows->count(),
            'valid_amount' => number_format($amountCents / 100, 2, '.', ''),
        ];
    }

    /** @return array<string, mixed> */
    public function find(string $paymentKey, User $viewer): array
    {
        if (preg_match('/^tuition-(\d+)$/', $paymentKey, $matches) === 1) {
            $payment = StudentTuitionPayment::query()
                ->whereKey((int) $matches[1])
                ->with(['enrollment.course', 'paymentMethod'])
                ->firstOrFail();
            $this->assertBranchAccess((int) $payment->enrollment?->branch_id, $viewer);

            return $this->formatTuitionPayment($payment);
        }

        if (preg_match('/^expense-(\d+)$/', $paymentKey, $matches) === 1) {
            $payment = StudentExpensePayment::query()
                ->whereKey((int) $matches[1])
                ->with(['expense.enrollment.course', 'expense.stageRecord.enrollment.course', 'paymentMethod'])
                ->firstOrFail();
            $expense = $payment->expense;
            if ($expense === null) {
                abort(404, 'Student payment not found.');
            }
            $enrollment = $expense->enrollment ?? $expense->stageRecord?->enrollment;
            $branchId = $enrollment->branch_id ?? $expense->branch_id;
            $this->assertBranchAccess((int) $branchId, $viewer);

            return $this->formatExpensePayment($payment);
        }

        throw ValidationException::withMessages([
            'payment_key' => 'The payment key must identify a tuition or expense payment.',
        ]);
    }

    /** @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function tuitionPayments(Student $student, array $filters, User $viewer): array
    {
        $query = StudentTuitionPayment::query()
            ->whereHas('enrollment', fn ($enrollment) => $enrollment
                ->where('student_id', $student->id)
                ->when(! $viewer->is_super_admin, fn ($query) => $query->where('branch_id', $viewer->branch_id)))
            ->with(['enrollment.course', 'paymentMethod']);
        $this->applyFilters($query, $filters, 'enrollment');

        return $query->get()->map(fn (StudentTuitionPayment $payment): array => $this->formatTuitionPayment($payment))->all();
    }

    /** @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function expensePayments(Student $student, array $filters, User $viewer): array
    {
        $query = StudentExpensePayment::query()
            ->whereHas('expense', fn ($expense) => $expense->where('student_id', $student->id)
                ->when(! $viewer->is_super_admin, fn ($query) => $query->where('branch_id', $viewer->branch_id)))
            ->with(['expense.enrollment.course', 'expense.stageRecord.enrollment.course', 'paymentMethod']);
        $this->applyFilters($query, $filters, 'expense.enrollment', true);

        return $query->get()->map(fn (StudentExpensePayment $payment): array => $this->formatExpensePayment($payment))->all();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(
        Builder $query,
        array $filters,
        string $enrollmentRelation,
        bool $expenseCanUseStageEnrollment = false,
    ): void {
        if (isset($filters['course_id'])) {
            if ($expenseCanUseStageEnrollment) {
                $query->whereHas('expense', fn ($expense) => $expense
                    ->whereHas('enrollment', fn ($enrollment) => $enrollment->where('course_id', $filters['course_id']))
                    ->orWhereHas('stageRecord.enrollment', fn ($enrollment) => $enrollment->where('course_id', $filters['course_id'])));
            } else {
                $query->whereHas($enrollmentRelation, fn ($enrollment) => $enrollment->where('course_id', $filters['course_id']));
            }
        }
        if (isset($filters['payment_method_id'])) {
            $query->where('payment_method_id', $filters['payment_method_id']);
        }
        if (isset($filters['date_from'])) {
            $query->whereDate('paid_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('paid_at', '<=', $filters['date_to']);
        }
    }

    /** @return array<string, mixed> */
    private function formatTuitionPayment(StudentTuitionPayment $payment): array
    {
        $enrollment = $payment->enrollment;

        return $this->formatPayment(
            'tuition-'.$payment->id,
            'tuition',
            $payment->payment_number,
            $payment->amount,
            Carbon::parse((string) $payment->getRawOriginal('paid_at'))->toIso8601String(),
            $payment->getRawOriginal('status'),
            $payment->transaction_reference,
            $payment->proof_file_path,
            $payment->paymentMethod?->name,
            $enrollment?->enrollment_code,
            $enrollment?->course?->id,
            $enrollment?->course?->name,
        );
    }

    /** @return array<string, mixed> */
    private function formatExpensePayment(StudentExpensePayment $payment): array
    {
        $expense = $payment->expense;
        $enrollment = $expense?->enrollment;
        if ($enrollment === null && $expense !== null) {
            $enrollment = $expense->stageRecord?->enrollment;
        }

        return $this->formatPayment(
            'expense-'.$payment->id,
            'expense',
            $payment->payment_number,
            $payment->amount,
            Carbon::parse((string) $payment->getRawOriginal('paid_at'))->toIso8601String(),
            $payment->getRawOriginal('status'),
            $payment->transaction_reference,
            $payment->proof_file_path,
            $payment->paymentMethod?->name,
            $enrollment?->enrollment_code,
            $enrollment?->course?->id,
            $enrollment?->course?->name,
        );
    }

    /** @return array<string, mixed> */
    private function formatPayment(
        string $key,
        string $source,
        string $number,
        mixed $amount,
        ?string $paidAt,
        string $status,
        ?string $transactionReference,
        ?string $receiptPath,
        ?string $paymentMethod,
        ?string $enrollmentCode,
        ?int $courseId,
        ?string $courseName,
    ): array {
        return [
            'payment_key' => $key,
            'source' => $source,
            'payment_number' => $number,
            'amount' => (string) $amount,
            'paid_at' => $paidAt,
            'status' => $status,
            'is_valid' => $status === 'confirmed',
            'transaction_reference' => $transactionReference,
            'payment_method' => $paymentMethod,
            'receipt' => $receiptPath === null ? null : ['path' => $receiptPath],
            'enrollment_code' => $enrollmentCode,
            'course_id' => $courseId,
            'course_name' => $courseName,
        ];
    }

    private function toCents(mixed $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim((string) $value), 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /** Hide a payment receipt unless its enrollment belongs to the viewer's branch. */
    private function assertBranchAccess(int $branchId, User $viewer): void
    {
        if (! $viewer->is_super_admin && ($viewer->branch_id === null || $branchId !== (int) $viewer->branch_id)) {
            abort(404, 'Student payment not found.');
        }
    }
}
