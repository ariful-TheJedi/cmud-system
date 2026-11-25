<?php

namespace App\Modules\Finance\Services\StudentFinance;

use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Academy\StudentStageRecord;
use App\Models\Finance\FinancialAccount;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\StudentExpense;
use App\Models\Finance\StudentExpensePayment;
use App\Models\Finance\StudentExpensePriceVersion;
use App\Models\Finance\StudentExpenseType;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Security\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Own enrollment-scoped student billing reads, expense issuance, and cash collection. */
class StudentFinanceService
{
    public function __construct(private readonly StudentFinancialOverviewService $overview) {}

    /** Find a student by exact Student ID and return only enrollments visible to this branch. */
    /** @return array{student: array<string, mixed>, enrollments: array<int, array<string, mixed>>} */
    public function searchStudent(string $studentCode, User $viewer): array
    {
        $student = Student::query()->where('student_code', $studentCode)->firstOrFail();
        $enrollmentQuery = $student->enrollments();
        if (! $viewer->is_super_admin) {
            $enrollmentQuery->where('branch_id', $viewer->branch_id);
        }
        $enrollments = $enrollmentQuery->with([
            'course:id,code,name',
            'batch:id,code,name',
            'branch:id,name',
            'stageRecords.courseStage:id,code,name',
        ])
            ->orderByDesc('id')->get()
            ->map(fn (Enrollment $enrollment): array => $this->formatEnrollment($enrollment))
            ->values()->all();

        if ($enrollments === [] && ! $viewer->is_super_admin) {
            abort(404, 'Student not found.');
        }

        return [
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'full_name' => $student->full_name,
                'phone' => $student->phone,
                'email' => $student->email,
            ],
            'enrollments' => $enrollments,
        ];
    }

    /** List the selected student's enrollment records under the same branch scope. */
    /** @return array{student: array<string, mixed>, enrollments: array<int, array<string, mixed>>} */
    public function studentEnrollments(string $studentCode, User $viewer): array
    {
        return $this->searchStudent($studentCode, $viewer);
    }

    /** Return one enrollment's independent balance summary and itemized obligations. */
    /** @return array<string, mixed> */
    public function billingOverview(Enrollment $enrollment, User $viewer): array
    {
        $this->assertEnrollmentAccess($enrollment, $viewer);
        $summary = $this->overview->forEnrollment($enrollment) ?? [];
        $tuition = $summary['bill'] ?? null;
        $otherBills = $summary['other_bills'] ?? [];
        $tuitionAmount = $tuition !== null && ! $tuition['is_cancelled']
            ? $this->toCents($tuition['original_amount']) : 0;
        $tuitionWaiver = $tuitionAmount > 0 ? $this->toCents($tuition['approved_waiver']) : 0;
        // Payment totals are derived from confirmed ledger rows, never from a stored status label.
        $tuitionRecord = $enrollment->hasTuition() ? $enrollment : null;
        $tuitionActive = $tuition !== null && ! $tuition['is_cancelled'];
        $tuitionPaid = ! $tuitionActive || $tuitionRecord === null ? 0 : $this->confirmedTuitionPaid((int) $tuitionRecord->id);
        $activeExpenses = collect($otherBills)->reject(fn (array $bill): bool => $bill['is_cancelled']);
        $expenseAmount = $activeExpenses->sum(fn (array $bill): int => $this->toCents($bill['amount']));
        $expensePayable = $activeExpenses->sum(fn (array $bill): int => $this->toCents($bill['payable_amount']));
        $expensePaid = $activeExpenses->sum(fn (array $bill): int => collect($bill['payments'])
            ->filter(fn (array $payment): bool => $payment['status'] === 'confirmed')
            ->sum(fn (array $payment): int => $this->toCents($payment['amount'])));
        $expenseDue = $activeExpenses->sum(fn (array $bill): int => $this->toCents($bill['outstanding_due']));
        $tuitionPayable = max(0, $tuitionAmount - $tuitionWaiver);
        $tuitionDue = max(0, $tuitionPayable - $tuitionPaid);

        return [
            'enrollment' => $this->formatEnrollment($enrollment->loadMissing([
                'course:id,code,name',
                'batch:id,code,name',
                'branch:id,name',
                'stageRecords.courseStage:id,code,name',
            ])),
            'totals' => [
                'tuition_amount' => $this->formatCents($tuitionAmount),
                'tuition_paid' => $this->formatCents($tuitionPaid),
                'tuition_due' => $this->formatCents($tuitionDue),
                'other_expenses' => $this->formatCents($expenseAmount),
                'other_expenses_paid' => $this->formatCents($expensePaid),
                'other_expenses_due' => $this->formatCents($expenseDue),
                'total_obligations' => $this->formatCents($tuitionPayable + $expensePayable),
                'total_paid' => $this->formatCents($tuitionPaid + $expensePaid),
                'outstanding_balance' => $this->formatCents($tuitionDue + $expenseDue),
            ],
            'tuition' => $tuition,
            'expenses' => $otherBills,
        ];
    }

    /** Return the tuition obligation and its complete confirmed/pending ledger history. */
    /** @return array<string, mixed>|null */
    public function tuition(Enrollment $enrollment, User $viewer): ?array
    {
        $this->assertEnrollmentAccess($enrollment, $viewer);
        $tuition = $enrollment->hasTuition() ? $enrollment->load([
            'tuitionPayments.paymentMethod:id,name',
            'tuitionPayments.financialAccount:id,name',
            'tuitionPayments.recorder:id,name',
        ]) : null;
        if ($tuition === null) {
            return null;
        }

        $paid = $this->confirmedTuitionPaid((int) $tuition->id);
        $gross = $this->toCents($tuition->tuition_original_amount);
        $waiver = $tuition->getRawOriginal('tuition_waiver_approved_at') && $tuition->getRawOriginal('tuition_waiver_approved_by')
            ? $this->toCents($tuition->tuition_waiver_amount) : 0;
        $cancelled = $this->isCancelled($tuition);
        if ($cancelled) {
            $waiver = 0;
        }
        $payable = $cancelled ? 0 : max(0, $gross - $waiver);

        return [
            'id' => $tuition->id,
            'tuition_number' => $tuition->tuition_number,
            'status' => $tuition->getRawOriginal('tuition_status'),
            'is_cancelled' => $cancelled,
            'issued_on' => $tuition->getRawOriginal('tuition_issued_on'),
            'due_on' => $tuition->getRawOriginal('tuition_due_on'),
            'amount' => $this->formatCents($gross),
            'approved_waiver' => $this->formatCents($waiver),
            'payable_amount' => $this->formatCents($payable),
            'paid_amount' => $this->formatCents($paid),
            'outstanding_balance' => $this->formatCents(max(0, $payable - $paid)),
            'payments' => $tuition->tuitionPayments->map(fn (StudentTuitionPayment $payment): array => $this->formatPayment($payment))->all(),
        ];
    }

    /** List approved prices currently applicable to the selected enrollment. */
    /** @return array<int, array<string, mixed>> */
    public function expenseOptions(
        Enrollment $enrollment,
        User $viewer,
        ?string $issuedOn = null,
        ?int $stageRecordId = null,
    ): array {
        $this->assertEnrollmentAccess($enrollment, $viewer);
        $enrollment->loadMissing('course');
        $stageRecord = $this->stageRecordForEnrollment($enrollment, $stageRecordId);
        $types = StudentExpenseType::query()->where('institute_id', $enrollment->course->institute_id)
            ->where('is_active', true)->with(['priceVersions' => fn (HasMany $query) => $this->applicablePriceQuery(
                $query,
                $enrollment,
                Carbon::parse($issuedOn ?? now()->toDateString())->toDateString(),
                $stageRecord?->course_stage_id,
            )])
            ->orderBy('name')->get();

        return $types->map(fn (StudentExpenseType $type): array => [
            'id' => $type->id,
            'code' => $type->code,
            'name' => $type->name,
            'description' => $type->description,
            'prices' => $this->sortPrices($type->priceVersions)->map(fn (StudentExpensePriceVersion $price): array => [
                'id' => $price->id,
                'amount' => (string) $price->amount,
                'effective_from' => $price->getRawOriginal('effective_from'),
                'effective_to' => $price->getRawOriginal('effective_to'),
                'branch_id' => $price->branch_id,
                'course_id' => $price->course_id,
                'course_stage_id' => $price->course_stage_id,
            ])->values()->all(),
        ])->all();
    }

    /** Provide active payment methods and branch-compatible accounts for this enrollment. */
    /** @return array{financial_accounts: array<int, array<string, mixed>>, payment_methods: array<int, array<string, mixed>>} */
    public function paymentOptions(Enrollment $enrollment, User $viewer): array
    {
        $this->assertEnrollmentAccess($enrollment, $viewer);
        $enrollment->loadMissing('course');

        return [
            'financial_accounts' => FinancialAccount::query()->where('institute_id', $enrollment->course->institute_id)
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $enrollment->branch_id))
                ->orderBy('name')->get(['id', 'name', 'code', 'account_type', 'branch_id'])->toArray(),
            'payment_methods' => PaymentMethod::query()->where('institute_id', $enrollment->course->institute_id)
                ->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'requires_transaction_reference', 'requires_sender_phone'])->toArray(),
        ];
    }

    /** Issue an additional bill, optionally collecting cash in the same transaction.
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createExpense(Enrollment $enrollment, array $data, User $recorder): array
    {
        $this->assertEnrollmentAccess($enrollment, $recorder);
        if (! isset($data['payment'])) {
            return $this->issueExpense($enrollment, $data, $recorder);
        }

        return DB::transaction(function () use ($enrollment, $data, $recorder): array {
            // Lock the enrollment so retries cannot issue the same paid bill twice.
            Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            $number = 'SE-'.Str::upper(substr(hash('sha256', 'enrollment|'.$enrollment->id.'|'.$data['payment']['idempotency_key']), 0, 26));
            $expense = StudentExpense::query()->where('expense_number', $number)->first();
            if ($expense === null) {
                $issued = $this->issueExpense($enrollment, $data, $recorder, $number);
                $expense = StudentExpense::query()->findOrFail($issued['id']);
            } else {
                $this->assertSameExpenseSubmission($expense, $data);
            }
            $result = $this->recordExpenseCashPayment($enrollment, $expense, $data['payment'], $recorder);

            return [...$result['expense'], 'payment' => $result['payment'], 'idempotent_replay' => $result['idempotent_replay']];
        });
    }

    /** @param array<string, mixed> $data */
    private function assertSameExpenseSubmission(StudentExpense $expense, array $data): void
    {
        $changed = (int) $expense->student_expense_type_id !== (int) $data['student_expense_type_id']
            || (int) $expense->student_expense_price_version_id !== (int) $data['price_version_id']
            || (int) $expense->student_stage_record_id !== (int) ($data['student_stage_record_id'] ?? 0)
            || $this->toThousandths($expense->quantity) !== $this->toThousandths($data['quantity'] ?? '1.000')
            || (string) ($expense->notes ?? '') !== (string) ($data['notes'] ?? '')
            || (isset($data['description']) && $expense->description !== $data['description'])
            || ($expense->getRawOriginal('due_on') ?? null) !== ($data['due_on'] ?? null)
            || (isset($data['issued_on']) && $expense->getRawOriginal('issued_on') !== Carbon::parse($data['issued_on'])->toDateString());
        if ($changed) {
            throw ValidationException::withMessages(['payment.idempotency_key' => 'This request key was already used for another bill.']);
        }
    }

    /** Create an enrollment-linked expense using an approved historical price snapshot. */
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function issueExpense(Enrollment $enrollment, array $data, User $recorder, ?string $expenseNumber = null): array
    {
        $this->assertEnrollmentAccess($enrollment, $recorder);
        $enrollment->loadMissing('course');
        $issuedOn = Carbon::parse((string) ($data['issued_on'] ?? now()))->toDateString();
        if (isset($data['due_on']) && Carbon::parse((string) $data['due_on'])->startOfDay()
            ->isBefore(Carbon::parse($issuedOn)->startOfDay())) {
            throw ValidationException::withMessages(['due_on' => 'The due date cannot be before the issue date.']);
        }
        $stageRecord = $this->stageRecordForEnrollment($enrollment, $data['student_stage_record_id'] ?? null);
        $price = StudentExpensePriceVersion::query()->whereKey($data['price_version_id'])
            ->where('student_expense_type_id', $data['student_expense_type_id'])
            ->where('status', 'approved')->whereDate('effective_from', '<=', $issuedOn)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $issuedOn))
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $enrollment->branch_id))
            ->where(fn (Builder $query) => $query->whereNull('course_id')->orWhere('course_id', $enrollment->course_id))
            ->where(fn (Builder $query) => $query->whereNull('course_stage_id')
                ->when($stageRecord === null, fn (Builder $scope) => $scope->whereNull('course_stage_id'),
                    fn (Builder $scope) => $scope->orWhere('course_stage_id', $stageRecord->course_stage_id)))
            ->first();
        if ($price === null) {
            throw ValidationException::withMessages(['price_version_id' => 'Choose an approved price effective for this enrollment and issue date.']);
        }

        $type = StudentExpenseType::query()->whereKey($data['student_expense_type_id'])
            ->where('institute_id', $enrollment->course->institute_id)->where('is_active', true)->first();
        if ($type === null) {
            throw ValidationException::withMessages(['student_expense_type_id' => 'Choose an active expense type for this institute.']);
        }

        $expense = DB::transaction(fn (): StudentExpense => StudentExpense::query()->create([
            'expense_number' => $expenseNumber ?? 'SE-'.now()->format('Y').'-'.Str::upper((string) Str::ulid()),
            'student_id' => $enrollment->student_id,
            'enrollment_id' => $enrollment->id,
            'student_stage_record_id' => $stageRecord?->id,
            'branch_id' => $enrollment->branch_id,
            'student_expense_type_id' => $type->id,
            'student_expense_price_version_id' => $price->id,
            'description' => $data['description'] ?? $type->name,
            'quantity' => $data['quantity'] ?? '1.000',
            'unit_amount' => $price->amount,
            'issued_on' => $issuedOn,
            'due_on' => $data['due_on'] ?? null,
            'status' => 'issued',
            'recorded_by' => $recorder->getAuthIdentifier(),
            'notes' => $data['notes'] ?? null,
        ]));

        return $this->expense($enrollment, $expense, $recorder);
    }

    /** Return only expenses directly attached to this enrollment. */
    /** @return array<int, array<string, mixed>> */
    public function expenses(Enrollment $enrollment, User $viewer): array
    {
        $this->assertEnrollmentAccess($enrollment, $viewer);

        return StudentExpense::query()->where(fn (Builder $query) => $query
            ->where('enrollment_id', $enrollment->id)
            ->orWhereHas('stageRecord', fn (Builder $stages) => $stages->where('enrollment_id', $enrollment->id)))
            ->with([
                'expenseType:id,name,code',
                'payments.paymentMethod:id,name',
                'payments.financialAccount:id,name',
                'payments.recorder:id,name',
            ])
            ->orderByDesc('id')->get()->map(fn (StudentExpense $expense): array => $this->formatExpense($expense))->all();
    }

    /** Return a selected expense only after proving it belongs to this enrollment. */
    /** @return array<string, mixed> */
    public function expense(Enrollment $enrollment, StudentExpense $expense, User $viewer): array
    {
        $this->assertEnrollmentAccess($enrollment, $viewer);
        if (! $this->expenseBelongsToEnrollment($expense, $enrollment)) {
            abort(404, 'Student expense not found.');
        }

        return $this->formatExpense($expense->loadMissing([
            'expenseType:id,name,code',
            'payments.paymentMethod:id,name',
            'payments.financialAccount:id,name',
            'payments.recorder:id,name',
        ]));
    }

    /** Record an enrollment-scoped cash receipt with row locking and idempotent retry handling. */
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function recordExpenseCashPayment(Enrollment $enrollment, StudentExpense $expense, array $data, User $recorder): array
    {
        $this->assertEnrollmentAccess($enrollment, $recorder);
        if (! $this->expenseBelongsToEnrollment($expense, $enrollment)) {
            abort(404, 'Student expense not found.');
        }

        $transactionResult = DB::transaction(function () use ($enrollment, $expense, $data, $recorder): array {
            // The row lock serializes payment attempts against this expense and blocks over-collection.
            $lockedExpense = StudentExpense::query()
                ->where(fn (Builder $query) => $query->where('enrollment_id', $enrollment->id)
                    ->orWhereHas('stageRecord', fn (Builder $stages) => $stages->where('enrollment_id', $enrollment->id)))
                ->lockForUpdate()->findOrFail($expense->id);
            $number = $this->paymentNumber('expense', $lockedExpense->id, (string) $data['idempotency_key']);
            $existing = StudentExpensePayment::query()->where('payment_number', $number)->first();
            if ($existing !== null) {
                $enrollment->loadMissing('course');
                $expectedMethodId = $data['payment_method_id']
                    ?? PaymentMethod::query()->where('institute_id', $enrollment->course->institute_id)
                        ->whereRaw('LOWER(name) = ?', ['cash'])->value('id');
                $this->assertSameSubmission($existing, [
                    ...$data,
                    'payment_method_id' => $expectedMethodId,
                ]);

                return ['payment_id' => (int) $existing->id, 'idempotent_replay' => true];
            }
            if ($lockedExpense->getRawOriginal('cancelled_at') !== null || $lockedExpense->getRawOriginal('status') !== 'issued') {
                throw ValidationException::withMessages(['expense' => 'Payment can only be recorded for an issued expense.']);
            }

            [$method, $account] = $this->paymentConfiguration(
                $enrollment,
                (int) $data['financial_account_id'],
                isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
            );
            $this->validateMethodRequirements($method, $data);
            $gross = $this->expenseGrossCents($lockedExpense);
            $waiver = $lockedExpense->getRawOriginal('waiver_approved_at') && $lockedExpense->getRawOriginal('waiver_approved_by')
                ? $this->toCents($lockedExpense->waiver_amount) : 0;
            $paid = $this->toCents($lockedExpense->payments()->where('status', 'confirmed')->sum('amount'));
            $amount = $this->toCents($data['amount']);
            if ($amount <= 0 || $amount > max(0, $gross - $waiver - $paid)) {
                throw ValidationException::withMessages(['amount' => 'The payment cannot exceed the expense balance.']);
            }

            $payment = StudentExpensePayment::query()->create([
                'student_expense_id' => $lockedExpense->id,
                'payment_number' => $number,
                'payment_method_id' => $method->id,
                'financial_account_id' => $account->id,
                'amount' => $this->formatCents($amount),
                'paid_at' => $data['paid_at'] ?? now(),
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payer_phone' => $data['payer_phone'] ?? null,
                'status' => 'confirmed',
                'verification_method' => 'manual',
                'verified_by' => $recorder->getAuthIdentifier(),
                'verified_at' => now(),
                'recorded_by' => $recorder->getAuthIdentifier(),
                'notes' => $data['notes'] ?? null,
            ]);

            return ['payment_id' => (int) $payment->id, 'idempotent_replay' => false];
        });
        $paymentId = $transactionResult['payment_id'];

        return [
            'expense' => $this->expense($enrollment, $expense->fresh(), $recorder),
            'payment' => $this->formatPayment(StudentExpensePayment::query()->with('paymentMethod:id,name')
                ->findOrFail($paymentId)),
            'idempotent_replay' => $transactionResult['idempotent_replay'],
        ];
    }

    /** Combine both payment ledgers while keeping every row tied to this enrollment. */
    /** @return array<int, array<string, mixed>> */
    public function paymentHistory(Enrollment $enrollment, User $viewer): array
    {
        $this->assertEnrollmentAccess($enrollment, $viewer);
        $tuition = $enrollment->hasTuition() ? $enrollment->load(['tuitionPayments.paymentMethod:id,name', 'tuitionPayments.recorder:id,name']) : null;
        $tuitionPayments = $tuition?->tuitionPayments->map(fn (StudentTuitionPayment $payment): array => [
            ...$this->formatPayment($payment), 'source' => 'tuition', 'obligation_number' => $tuition->tuition_number,
        ]) ?? collect();
        $expensePayments = StudentExpensePayment::query()->whereHas('expense', fn (Builder $query) => $query
            ->where('enrollment_id', $enrollment->id)
            ->orWhereHas('stageRecord', fn (Builder $stages) => $stages->where('enrollment_id', $enrollment->id)))
            ->with(['expense:id,expense_number', 'paymentMethod:id,name', 'recorder:id,name'])->get()->map(fn (StudentExpensePayment $payment): array => [
                ...$this->formatPayment($payment), 'source' => 'expense', 'obligation_number' => $payment->expense?->expense_number,
            ]);

        return $tuitionPayments->concat($expensePayments)->sortByDesc('paid_at')->values()->all();
    }

    /** Enforce the persistent branch boundary in the business layer. */
    private function assertEnrollmentAccess(Enrollment $enrollment, User $viewer): void
    {
        if (! $this->canAccess($enrollment, $viewer)) {
            abort(404, 'Enrollment not found.');
        }
    }

    private function canAccess(Enrollment $enrollment, User $viewer): bool
    {
        return $viewer->is_super_admin || ($viewer->branch_id !== null && (int) $viewer->branch_id === (int) $enrollment->branch_id);
    }

    /** Keep legacy stage-linked charges visible under their owning enrollment. */
    private function expenseBelongsToEnrollment(StudentExpense $expense, Enrollment $enrollment): bool
    {
        return (int) $expense->enrollment_id === (int) $enrollment->id
            || (int) $expense->stageRecord?->enrollment_id === (int) $enrollment->id;
    }

    /** @return array<string, mixed> */
    private function formatEnrollment(Enrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'enrollment_code' => $enrollment->enrollment_code,
            'enrollment_date' => $enrollment->getRawOriginal('enrollment_date'),
            'status' => $enrollment->getRawOriginal('status'),
            'course' => $enrollment->course ? ['id' => $enrollment->course->id, 'code' => $enrollment->course->code, 'name' => $enrollment->course->name] : null,
            'batch' => $enrollment->batch ? ['id' => $enrollment->batch->id, 'code' => $enrollment->batch->code, 'name' => $enrollment->batch->name] : null,
            'branch' => $enrollment->branch ? ['id' => $enrollment->branch->id, 'name' => $enrollment->branch->name] : null,
            'stage_records' => $enrollment->stageRecords->map(fn (StudentStageRecord $stage): array => [
                'id' => $stage->id,
                'status' => $stage->getRawOriginal('status'),
                'course_stage' => $stage->courseStage ? [
                    'id' => $stage->courseStage->id,
                    'code' => $stage->courseStage->code,
                    'name' => $stage->courseStage->name,
                ] : null,
            ])->values()->all(),
            'financial_status' => $this->overview->forEnrollment($enrollment)['financial_status'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function formatExpense(StudentExpense $expense): array
    {
        $gross = $this->expenseGrossCents($expense);
        $cancelled = $this->isCancelled($expense);
        $waiver = $expense->getRawOriginal('waiver_approved_at') && $expense->getRawOriginal('waiver_approved_by')
            ? $this->toCents($expense->waiver_amount) : 0;
        $paid = $this->toCents($expense->payments->where('status', 'confirmed')->sum('amount'));

        return [
            'id' => $expense->id,
            'expense_number' => $expense->expense_number,
            'student_expense_type' => $expense->expenseType ? ['id' => $expense->expenseType->id, 'code' => $expense->expenseType->code, 'name' => $expense->expenseType->name] : null,
            'description' => $expense->description,
            'status' => $expense->getRawOriginal('status'),
            'is_cancelled' => $cancelled,
            'issued_on' => $expense->getRawOriginal('issued_on'),
            'due_on' => $expense->getRawOriginal('due_on'),
            'quantity' => (string) $expense->quantity,
            'unit_amount' => (string) $expense->unit_amount,
            'amount' => $this->formatCents($gross),
            'approved_waiver' => $this->formatCents($waiver),
            'payable_amount' => $this->formatCents($cancelled ? 0 : max(0, $gross - $waiver)),
            'paid_amount' => $this->formatCents($paid),
            'outstanding_balance' => $this->formatCents($cancelled ? 0 : max(0, $gross - $waiver - $paid)),
            'payments' => $expense->payments->map(fn (StudentExpensePayment $payment): array => $this->formatPayment($payment))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function formatPayment(StudentTuitionPayment|StudentExpensePayment $payment): array
    {
        return [
            'id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'amount' => (string) $payment->amount,
            'paid_at' => Carbon::parse((string) $payment->getRawOriginal('paid_at'))->toIso8601String(),
            'status' => $payment->getRawOriginal('status'),
            'payment_method' => $payment->paymentMethod?->name,
            'financial_account_id' => $payment->financial_account_id,
            'transaction_reference' => $payment->transaction_reference,
            'notes' => $payment->notes,
            'recorded_by_name' => $payment->recorder?->name,
        ];
    }

    /** @return array{0: PaymentMethod, 1: FinancialAccount} */
    private function paymentConfiguration(Enrollment $enrollment, int $accountId, ?int $methodId): array
    {
        $enrollment->loadMissing('course');
        $account = FinancialAccount::query()->whereKey($accountId)->where('institute_id', $enrollment->course->institute_id)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $enrollment->branch_id))->first();
        if ($account === null) {
            throw ValidationException::withMessages(['financial_account_id' => 'Select an active financial account for this institute and branch.']);
        }
        $methods = PaymentMethod::query()->where('institute_id', $enrollment->course->institute_id)->where('is_active', true);
        $method = $methodId !== null
            ? $methods->whereKey($methodId)->first()
            : $methods->whereRaw('LOWER(name) = ?', ['cash'])->first();
        if ($method === null) {
            throw ValidationException::withMessages(['payment_method_id' => 'Select an active payment method for this institute.']);
        }

        return [$method, $account];
    }

    /** @param array<string, mixed> $data */
    private function validateMethodRequirements(PaymentMethod $method, array $data): void
    {
        if ($method->requires_transaction_reference && trim((string) ($data['transaction_reference'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'transaction_reference' => 'A transaction reference is required for this payment method.',
            ]);
        }
        if ($method->requires_sender_phone && trim((string) ($data['payer_phone'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'payer_phone' => 'A sender phone number is required for this payment method.',
            ]);
        }
    }

    /** @param HasMany<StudentExpensePriceVersion, StudentExpenseType> $query
     * @return HasMany<StudentExpensePriceVersion, StudentExpenseType>
     */
    private function applicablePriceQuery(
        HasMany $query,
        Enrollment $enrollment,
        string $issuedOn,
        ?int $courseStageId,
    ): HasMany {
        return $query->where('status', 'approved')->whereDate('effective_from', '<=', $issuedOn)
            ->where(fn (Builder $scope) => $scope->whereNull('effective_to')->orWhereDate('effective_to', '>=', $issuedOn))
            ->where(fn (Builder $scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $enrollment->branch_id))
            ->where(fn (Builder $scope) => $scope->whereNull('course_id')->orWhere('course_id', $enrollment->course_id))
            ->where(fn (Builder $scope) => $scope->whereNull('course_stage_id')
                ->when($courseStageId === null, fn (Builder $filtered) => $filtered->whereNull('course_stage_id'),
                    fn (Builder $filtered) => $filtered->orWhere('course_stage_id', $courseStageId)));
    }

    /** Ensure a stage-specific expense can only target a stage in this enrollment. */
    private function stageRecordForEnrollment(Enrollment $enrollment, ?int $stageRecordId): ?StudentStageRecord
    {
        if ($stageRecordId === null) {
            return null;
        }

        $stageRecord = StudentStageRecord::query()->whereKey($stageRecordId)
            ->where('enrollment_id', $enrollment->id)->first();
        if ($stageRecord === null) {
            throw ValidationException::withMessages([
                'student_stage_record_id' => 'Select a course stage that belongs to this enrollment.',
            ]);
        }

        return $stageRecord;
    }

    /** @param EloquentCollection<int, StudentExpensePriceVersion> $prices
     * @return Collection<int, StudentExpensePriceVersion>
     */
    private function sortPrices(EloquentCollection $prices): Collection
    {
        return $prices->sortByDesc(fn (StudentExpensePriceVersion $price): array => [
            (int) ($price->branch_id !== null), (int) ($price->course_id !== null), (int) ($price->course_stage_id !== null),
            $price->getRawOriginal('effective_from'), $price->version_number,
        ], SORT_REGULAR);
    }

    private function confirmedTuitionPaid(int $tuitionId): int
    {
        if ($tuitionId === 0) {
            return 0;
        }

        return $this->toCents(StudentTuitionPayment::query()->where('enrollment_id', $tuitionId)->where('status', 'confirmed')->sum('amount'));
    }

    private function paymentNumber(string $kind, int $obligationId, string $key): string
    {
        $prefix = $kind === 'expense' ? 'SEP' : 'STP';

        return $prefix.'-'.Str::upper(substr(hash('sha256', $kind.'|'.$obligationId.'|'.$key), 0, 26));
    }

    /** @param array<string, mixed> $data */
    private function assertSameSubmission(StudentTuitionPayment|StudentExpensePayment $payment, array $data): void
    {
        $paidAtChanged = array_key_exists('paid_at', $data)
            && Carbon::parse((string) $data['paid_at'])->getTimestamp()
                !== Carbon::parse((string) $payment->getRawOriginal('paid_at'))->getTimestamp();

        if ($this->toCents($payment->amount) !== $this->toCents($data['amount'])
            || (int) $payment->financial_account_id !== (int) $data['financial_account_id']
            || (int) $payment->payment_method_id !== (int) ($data['payment_method_id'] ?? 0)
            || (string) ($payment->transaction_reference ?? '') !== (string) ($data['transaction_reference'] ?? '')
            || (string) ($payment->payer_phone ?? '') !== (string) ($data['payer_phone'] ?? '')
            || (string) ($payment->notes ?? '') !== (string) ($data['notes'] ?? '')
            || $paidAtChanged) {
            throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key has already been used for a different payment.']);
        }
    }

    private function expenseGrossCents(StudentExpense $expense): int
    {
        $quantityMilli = $this->toThousandths($expense->quantity);
        $unitCents = $this->toCents($expense->unit_amount);

        return intdiv(($quantityMilli * $unitCents) + 500, 1000);
    }

    private function isCancelled(Enrollment|StudentExpense $obligation): bool
    {
        $prefix = $obligation instanceof Enrollment ? 'tuition_' : '';

        return $obligation->getRawOriginal($prefix.'cancelled_at') !== null
            || $obligation->getRawOriginal($prefix.'status') === 'cancelled';
    }

    private function toCents(mixed $value): int
    {
        $decimal = trim((string) $value);
        $negative = str_starts_with($decimal, '-');
        $decimal = ltrim($decimal, '+-');
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function toThousandths(mixed $value): int
    {
        $decimal = trim((string) $value);
        $negative = str_starts_with($decimal, '-');
        $decimal = ltrim($decimal, '+-');
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $milli = ((int) $whole * 1000) + (int) str_pad(substr($fraction, 0, 3), 3, '0');

        return $negative ? -$milli : $milli;
    }

    private function formatCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
