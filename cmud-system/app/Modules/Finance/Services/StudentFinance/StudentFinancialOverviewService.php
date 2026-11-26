<?php

namespace App\Modules\Finance\Services\StudentFinance;

use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Academy\StudentStageRecord;
use App\Models\Finance\StudentExpense;
use App\Models\Finance\StudentExpensePayment;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Security\User;
use Illuminate\Support\Carbon;

class StudentFinancialOverviewService
{
    /**
     * Read current tuition and student-expense balances for follow-up and eligibility decisions.
     *
     * @return array{
     *   student_code: string,
     *   totals: array<string, string>,
     *   enrollments: array<int, array{
     *     enrollment_id: int,
     *     enrollment_code: string,
     *     enrollment_status: string,
     *     course: array<string, mixed>|null,
     *     total_course_fee: string,
     *     additional_fees: string,
     *     approved_waiver: string,
     *     payable_fee: string,
     *     total_paid: string,
     *     outstanding_due: string,
     *     financial_status: string|null
     *   }>
     * }
     */
    public function forStudent(Student $student, ?User $viewer = null): array
    {
        $expensesByEnrollment = [];
        $expenses = $student->expenses()->with(['payments', 'stageRecord.enrollment']);
        if ($viewer !== null && ! $viewer->is_super_admin) {
            $expenses->where('branch_id', $viewer->branch_id);
        }
        foreach ($expenses->get() as $expense) {
            $enrollmentId = $expense->enrollment_id ?? $expense->stageRecord?->enrollment_id;
            if ($enrollmentId !== null) {
                $expensesByEnrollment[(int) $enrollmentId][] = $expense;
            }
        }

        /** @var array<int, array{
         *   enrollment_id: int,
         *   enrollment_code: string,
         *   enrollment_status: string,
         *   course: array<string, mixed>|null,
         *   total_course_fee: string,
         *   additional_fees: string,
         *   approved_waiver: string,
         *   payable_fee: string,
         *   total_paid: string,
         *   outstanding_due: string,
         *   financial_status: string|null
         * }> $summaries
         */
        $summaries = [];
        $totals = [
            'total_course_fee' => 0,
            'additional_fees' => 0,
            'approved_waiver' => 0,
            'payable_fee' => 0,
            'total_paid' => 0,
            'outstanding_due' => 0,
        ];

        $enrollments = $student->enrollments()->with(['course:id,code,name', 'tuitionPayments']);
        if ($viewer !== null && ! $viewer->is_super_admin) {
            $enrollments->where('branch_id', $viewer->branch_id);
        }
        $visibleEnrollments = $enrollments->orderByDesc('id')->get();
        if ($viewer !== null && ! $viewer->is_super_admin && $visibleEnrollments->isEmpty()) {
            abort(404, 'Student not found.');
        }
        foreach ($visibleEnrollments as $enrollment) {
            $tuition = $enrollment->hasTuition() ? $enrollment : null;
            $courseFee = 0;
            $additionalFees = 0;
            $waiver = 0;
            $paid = 0;
            $due = 0;
            $hasBill = false;
            $overdue = false;

            // Direct enrollment receipts can exist even when no tuition bill was issued.
            foreach ($enrollment->tuitionPayments as $payment) {
                if ($payment->getRawOriginal('status') === 'confirmed') {
                    $paid += $this->toCents($payment->amount);
                }
            }

            if ($tuition instanceof Enrollment && ! $this->isCancelled($tuition)) {
                $hasBill = true;
                $courseFee = $this->toCents($tuition->tuition_original_amount);
                $waiver = $this->tuitionWaiverCents($tuition);
                $due = max(0, $courseFee - $waiver - $paid);
                $overdue = $due > 0 && $this->isPastDue($tuition->getRawOriginal('tuition_due_on'));
            }

            $expenseDue = 0;
            foreach ($expensesByEnrollment[(int) $enrollment->id] ?? [] as $expense) {
                $balance = $this->expenseBalance($expense);
                $hasBill = $hasBill || ! $this->isCancelled($expense);
                $additionalFees += $balance['payable_cents'] + $balance['waiver_cents'];
                $expenseDue += $balance['due_cents'];
                $paid += $balance['paid_cents'];
                $waiver += $balance['waiver_cents'];
                $overdue = $overdue || (
                    $balance['due_cents'] > 0
                    && $this->isPastDue($expense->getRawOriginal('due_on'))
                );
            }
            $due += $expenseDue;
            $payable = max(0, $courseFee + $additionalFees - $waiver);

            $totals['total_course_fee'] += $courseFee;
            $totals['additional_fees'] += $additionalFees;
            $totals['approved_waiver'] += $waiver;
            $totals['payable_fee'] += $payable;
            $totals['total_paid'] += $paid;
            $totals['outstanding_due'] += $due;

            $summaries[] = [
                'enrollment_id' => $enrollment->id,
                'enrollment_code' => $enrollment->enrollment_code,
                'enrollment_status' => $enrollment->getRawOriginal('status'),
                'course' => $enrollment->course ? [
                    'id' => $enrollment->course->id,
                    'code' => $enrollment->course->code,
                    'name' => $enrollment->course->name,
                ] : null,
                'total_course_fee' => $this->formatCents($courseFee),
                'additional_fees' => $this->formatCents($additionalFees),
                'approved_waiver' => $this->formatCents($waiver),
                'payable_fee' => $this->formatCents($payable),
                'total_paid' => $this->formatCents($paid),
                'outstanding_due' => $this->formatCents($due),
                'financial_status' => $hasBill
                    ? $this->financialStatus($paid, $due, $overdue)
                    : null,
            ];
        }

        return [
            'student_code' => $student->student_code,
            'totals' => array_map(fn (int $amount): string => $this->formatCents($amount), $totals),
            'enrollments' => $summaries,
        ];
    }

    /**
     * @return array{
     *   enrollment_id: int,
     *   enrollment_code: string,
     *   enrollment_status: string,
     *   course: array<string, mixed>|null,
     *   total_course_fee: string,
     *   additional_fees: string,
     *   approved_waiver: string,
     *   payable_fee: string,
     *   total_paid: string,
     *   outstanding_due: string,
     *   financial_status: string|null,
     *   bill: array{
     *     tuition_number: string,
     *     status: string,
     *     is_cancelled: bool,
     *     original_amount: string,
     *     approved_waiver: string,
     *     payable_amount: string,
     *     due_on: string|null,
     *     payments: array<int, array{amount: string, status: string}>
     *   }|null,
     *   other_bills: array<int, array{
     *     expense_number: string,
     *     description: string,
     *     status: string,
     *     is_cancelled: bool,
     *     issued_on: string,
     *     due_on: string|null,
     *     amount: string,
     *     approved_waiver: string,
     *     payable_amount: string,
     *     outstanding_due: string,
     *     payments: array<int, array{amount: string, status: string}>
     *   }>
     * }|null
     */
    public function forEnrollment(Enrollment $enrollment, ?User $viewer = null): ?array
    {
        if ($viewer !== null && ! $viewer->is_super_admin
            && ($viewer->branch_id === null || (int) $viewer->branch_id !== (int) $enrollment->branch_id)) {
            abort(404, 'Enrollment not found.');
        }
        $summary = collect($this->forStudent($enrollment->student)['enrollments'])
            ->firstWhere('enrollment_id', $enrollment->id);

        if ($summary === null) {
            return null;
        }

        $enrollment->load(['tuitionPayments' => fn ($query) => $query->orderByDesc('paid_at'), 'tuitionPayments.paymentMethod:id,name']);
        $tuition = $enrollment->hasTuition() ? $enrollment : null;
        $tuitionPayments = $enrollment->tuitionPayments->map(fn (StudentTuitionPayment $payment): array => [
            'payment_number' => $payment->payment_number,
            'amount' => $this->formatCents($this->toCents($payment->amount)),
            'paid_at' => Carbon::parse((string) $payment->getRawOriginal('paid_at'))->toIso8601String(),
            'status' => (string) $payment->getRawOriginal('status'),
            'transaction_reference' => $payment->transaction_reference,
            'receipt_path' => $payment->proof_file_path,
            'payment_method' => $payment->paymentMethod?->name,
        ])->all();
        $expenses = StudentExpense::query()
            ->where(fn ($query) => $query
                ->where('enrollment_id', $enrollment->id)
                ->orWhereHas('stageRecord', fn ($stageRecords) => $stageRecords
                    ->where('enrollment_id', $enrollment->id)))
            ->with(['payments' => fn ($query) => $query->orderByDesc('paid_at'), 'payments.paymentMethod:id,name'])
            ->orderBy('id')
            ->get();

        return [
            ...$summary,
            'bill' => $tuition ? [
                'tuition_number' => $tuition->tuition_number,
                'status' => (string) $tuition->getRawOriginal('tuition_status'),
                'is_cancelled' => $this->isCancelled($tuition),
                'original_amount' => (string) $tuition->tuition_original_amount,
                'approved_waiver' => $this->formatCents($this->tuitionWaiverCents($tuition)),
                'payable_amount' => $this->formatCents($this->isCancelled($tuition) ? 0 : max(
                    0,
                    $this->toCents($tuition->tuition_original_amount) - $this->tuitionWaiverCents($tuition),
                )),
                'due_on' => $tuition->getRawOriginal('tuition_due_on'),
                'payments' => $tuitionPayments,
            ] : null,
            'unbilled_tuition_payments' => $tuition === null ? $tuitionPayments : [],
            'other_bills' => $expenses->map(function (StudentExpense $expense): array {
                $balance = $this->expenseBalance($expense);

                return [
                    'expense_number' => $expense->expense_number,
                    'description' => $expense->description,
                    'status' => (string) $expense->getRawOriginal('status'),
                    'is_cancelled' => $this->isCancelled($expense),
                    'issued_on' => $expense->getRawOriginal('issued_on'),
                    'due_on' => $expense->getRawOriginal('due_on'),
                    'amount' => $this->formatCents($this->expenseGrossCents($expense)),
                    'approved_waiver' => $this->formatCents($balance['waiver_cents']),
                    'payable_amount' => $this->formatCents($balance['payable_cents']),
                    'outstanding_due' => $this->formatCents($balance['due_cents']),
                    'payments' => $expense->payments->map(fn (StudentExpensePayment $payment): array => [
                        'payment_number' => $payment->payment_number,
                        'amount' => $this->formatCents($this->toCents($payment->amount)),
                        'paid_at' => Carbon::parse((string) $payment->getRawOriginal('paid_at'))->toIso8601String(),
                        'status' => (string) $payment->getRawOriginal('status'),
                        'transaction_reference' => $payment->transaction_reference,
                        'receipt_path' => $payment->proof_file_path,
                        'payment_method' => $payment->paymentMethod?->name,
                    ])->all(),
                ];
            })->all(),
        ];
    }

    /** @return array{payable_cents: int, paid_cents: int, due_cents: int} */
    public function stageFeeSummary(StudentStageRecord $stageRecord): array
    {
        $payable = 0;
        $paid = 0;
        foreach (StudentExpense::query()
            ->where('student_stage_record_id', $stageRecord->id)
            ->with('payments')
            ->get() as $expense) {
            $balance = $this->expenseBalance($expense);
            $payable += $balance['payable_cents'];
            $paid += $balance['paid_cents'];
        }

        return [
            'payable_cents' => $payable,
            'paid_cents' => $paid,
            'due_cents' => max(0, $payable - $paid),
        ];
    }

    /** @return array{payable_cents: int, paid_cents: int, due_cents: int, waiver_cents: int} */
    private function expenseBalance(StudentExpense $expense): array
    {
        if ($this->isCancelled($expense)) {
            return ['payable_cents' => 0, 'paid_cents' => 0, 'due_cents' => 0, 'waiver_cents' => 0];
        }

        $grossCents = $this->expenseGrossCents($expense);
        $waiverApproved = $expense->getRawOriginal('waiver_approved_by') !== null
            && $expense->getRawOriginal('waiver_approved_at') !== null;
        $waiver = $waiverApproved ? $this->toCents($expense->waiver_amount) : 0;
        $payable = max(0, $grossCents - $waiver);
        $paid = 0;

        foreach ($expense->payments as $payment) {
            if ($payment->getRawOriginal('status') === 'confirmed') {
                $paid += $this->toCents($payment->amount);
            }
        }

        return [
            'payable_cents' => $payable,
            'paid_cents' => $paid,
            'due_cents' => max(0, $payable - $paid),
            'waiver_cents' => $waiver,
        ];
    }

    private function isCancelled(Enrollment|StudentExpense $bill): bool
    {
        $prefix = $bill instanceof Enrollment ? 'tuition_' : '';

        return $bill->getRawOriginal($prefix.'cancelled_at') !== null
            || $bill->getRawOriginal($prefix.'status') === 'cancelled';
    }

    private function financialStatus(int $paidCents, int $dueCents, bool $overdue): string
    {
        if ($dueCents === 0) {
            return 'paid';
        }

        if ($overdue) {
            return 'overdue';
        }

        return $paidCents === 0 ? 'unpaid' : 'partial';
    }

    private function tuitionWaiverCents(Enrollment $tuition): int
    {
        $waiverApproved = $tuition->getRawOriginal('tuition_waiver_approved_by') !== null
            && $tuition->getRawOriginal('tuition_waiver_approved_at') !== null;

        return $waiverApproved ? $this->toCents($tuition->tuition_waiver_amount) : 0;
    }

    private function expenseGrossCents(StudentExpense $expense): int
    {
        $quantityMilli = $this->toThousandths($expense->quantity);
        $unitAmountCents = $this->toCents($expense->unit_amount);

        return intdiv(($quantityMilli * $unitAmountCents) + 500, 1000);
    }

    private function isPastDue(mixed $dueOn): bool
    {
        return $dueOn !== null && Carbon::parse((string) $dueOn)->isBefore(Carbon::today());
    }

    private function toThousandths(mixed $value): int
    {
        $number = trim((string) $value);
        $negative = str_starts_with($number, '-');
        $number = ltrim($number, '+-');
        [$whole, $fraction] = array_pad(explode('.', $number, 2), 2, '');
        $milli = ((int) $whole * 1000) + (int) str_pad(substr($fraction, 0, 3), 3, '0');

        return $negative ? -$milli : $milli;
    }

    private function toCents(mixed $amount): int
    {
        $value = trim((string) $amount);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
