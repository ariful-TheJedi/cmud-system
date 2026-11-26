<?php

namespace App\Modules\Finance\Services\StudentFinance;

use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Finance\FinancialAccount;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\StudentExpense;
use App\Models\Finance\StudentExpensePayment;
use App\Models\Finance\StudentExpensePriceVersion;
use App\Models\Finance\StudentExpenseType;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Security\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Own student billing calculations, branch filtering, and receipt transactions. */
class StudentPaymentService
{
    /** Return a merged, branch-filtered receipt register with server-side filters. */
    public function register(array $filters, User $user): LengthAwarePaginator
    {
        $query = $this->registerQuery($user);
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (QueryBuilder $builder) use ($search): void {
                $builder->where('student_code', 'like', '%'.$search.'%')
                    ->orWhere('full_name', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['date'])) {
            $query->whereDate('paid_at', $filters['date']);
        }

        return $query->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(min((int) ($filters['per_page'] ?? 15), 100));
    }

    /** Resolve a visible student, enrollment choices, methods, and effective bill prices. */
    public function billingOptions(string $studentCode, User $user): array
    {
        $student = Student::query()
            ->where('student_code', $studentCode)
            ->when(! $user->is_super_admin, fn (Builder $query) => $query->whereHas('enrollments', fn (Builder $scope) => $scope->where('branch_id', $user->branch_id)))
            ->first();

        if ($student === null) {
            abort(404, 'Student was not found in your billing branch.');
        }

        $enrollments = $this->visibleEnrollments($student, $user);

        return [
            'student' => $this->formatStudent($student),
            'enrollments' => $enrollments->map(fn (Enrollment $enrollment): array => [
                ...$this->formatEnrollment($enrollment),
                'payment_methods' => PaymentMethod::query()
                    ->where('institute_id', $enrollment->course->institute_id)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get(['id', 'code', 'name', 'requires_transaction_reference', 'requires_sender_phone'])
                    ->map(fn (PaymentMethod $method): array => [
                        'id' => $method->id,
                        'code' => $method->code,
                        'name' => $method->name,
                        'requires_transaction_reference' => $method->requires_transaction_reference,
                        'requires_sender_phone' => $method->requires_sender_phone,
                    ])->all(),
                'expense_items' => $this->expenseOptions($enrollment),
            ])->values()->all(),
        ];
    }

    /** Record a tuition receipt directly against the selected enrollment. */
    public function createTuitionPayment(array $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user): array {
            $enrollment = $this->resolveEnrollment($data, $user);
            $duplicate = StudentTuitionPayment::query()
                ->where('payment_number', $this->receiptNumber('TUI', $data['idempotency_key'], $enrollment->id))
                ->first();

            if ($duplicate !== null) {
                return ['payment' => $this->formatPayment($duplicate, 'tuition'), 'idempotent_replay' => true];
            }

            $amount = $this->money($data['amount']);

            [$method, $account, $status, $verification] = $this->paymentMethodStatus($enrollment, $data, $user);
            $payment = StudentTuitionPayment::query()->create([
                'enrollment_id' => $enrollment->id,
                'payment_number' => $this->receiptNumber('TUI', $data['idempotency_key'], $enrollment->id),
                'payment_method_id' => $method->id,
                'financial_account_id' => $account?->id,
                'amount' => number_format($amount, 2, '.', ''),
                'paid_at' => $data['paid_at'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payer_phone' => $data['payer_phone'] ?? null,
                'status' => $status,
                'verification_method' => $verification['method'],
                'verified_at' => $verification['at'],
                'verified_by' => $verification['by'],
                'recorded_by' => $user->getAuthIdentifier(),
                'notes' => $data['notes'] ?? null,
            ]);

            return ['payment' => $this->formatPayment($payment, 'tuition'), 'idempotent_replay' => false];
        });
    }

    /** Create an other-expense bill and its receipt in one database transaction. */
    public function createOtherPayment(array $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user): array {
            $enrollment = $this->resolveEnrollment($data, $user);
            $receiptNumber = $this->receiptNumber('EXP', $data['idempotency_key'], $enrollment->id);
            $duplicate = StudentExpensePayment::query()->where('payment_number', $receiptNumber)->first();

            if ($duplicate !== null) {
                return [
                    'bill' => $this->formatExpense($duplicate->expense()->with(['expenseType', 'payments'])->firstOrFail()),
                    'payment' => $this->formatPayment($duplicate, 'expense'),
                    'idempotent_replay' => true,
                ];
            }

            $item = StudentExpenseType::query()
                ->whereKey($data['student_expense_type_id'])
                ->where('institute_id', $enrollment->course->institute_id)
                ->where('is_active', true)
                ->first();

            if ($item === null) {
                throw ValidationException::withMessages(['student_expense_type_id' => 'Choose an active student bill item for this institute.']);
            }

            $price = $this->effectivePrice($item, $enrollment, Carbon::parse($data['paid_at'])->toDateString());
            $amount = $this->money($data['amount']);
            $billAmount = $this->money($price->amount);

            if ($amount > $billAmount) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the other bill amount.']);
            }

            [$method, $account, $status, $verification] = $this->paymentMethodStatus($enrollment, $data, $user);
            $bill = StudentExpense::query()->create([
                'expense_number' => $this->receiptNumber('BILL', $data['idempotency_key'], $enrollment->id),
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'student_stage_record_id' => null,
                'branch_id' => $enrollment->branch_id,
                'student_expense_type_id' => $item->id,
                'student_expense_price_version_id' => $price->id,
                'description' => $item->name,
                'quantity' => '1.000',
                'unit_amount' => number_format($billAmount, 2, '.', ''),
                'waiver_amount' => '0.00',
                'issued_on' => Carbon::parse($data['paid_at'])->toDateString(),
                'status' => 'issued',
                'recorded_by' => $user->getAuthIdentifier(),
                'notes' => $data['notes'] ?? null,
            ]);

            $payment = StudentExpensePayment::query()->create([
                'student_expense_id' => $bill->id,
                'payment_number' => $receiptNumber,
                'payment_method_id' => $method->id,
                'financial_account_id' => $account?->id,
                'amount' => number_format($amount, 2, '.', ''),
                'paid_at' => $data['paid_at'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payer_phone' => $data['payer_phone'] ?? null,
                'status' => $status,
                'verification_method' => $verification['method'],
                'verified_at' => $verification['at'],
                'verified_by' => $verification['by'],
                'recorded_by' => $user->getAuthIdentifier(),
                'notes' => $data['notes'] ?? null,
            ]);

            return [
                'bill' => $this->formatExpense($bill->load(['expenseType', 'payments'])),
                'payment' => $this->formatPayment($payment, 'expense'),
                'idempotent_replay' => false,
            ];
        });
    }

    /** Build the SQL union first so scope, search, date, and pagination cover both ledgers. */
    private function registerQuery(User $user): QueryBuilder
    {
        $tuition = DB::table('student_tuition_payments as p')
            ->join('enrollments as e', 'e.id', '=', 'p.enrollment_id')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->join('branches as b', 'b.id', '=', 'e.branch_id')
            ->join('courses as c', 'c.id', '=', 'e.course_id')
            ->leftJoin('batches as ba', 'ba.id', '=', 'e.batch_id')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'p.payment_method_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.recorded_by')
            ->selectRaw("p.id, 'tuition' as source, p.payment_number, p.amount, p.paid_at, p.status, p.transaction_reference, p.payer_phone, p.notes, pm.name as payment_method, s.id as student_pk, s.student_code, s.full_name, b.id as branch_id, b.name as branch_name, b.phone as branch_phone, b.email as branch_email, b.address as branch_address, e.id as enrollment_id, e.enrollment_code, c.name as course_name, ba.name as batch_name, 'Tuition' as category, 'Course tuition' as description, NULL as expense_id, u.name as recorded_by_name");
        $this->applyPaymentBranchScope($tuition, $user, 'e.branch_id');

        $expenses = DB::table('student_other_payments as p')
            ->join('student_expenses as se', 'se.id', '=', 'p.student_expense_id')
            ->join('students as s', 's.id', '=', 'se.student_id')
            ->join('branches as b', 'b.id', '=', 'se.branch_id')
            ->leftJoin('enrollments as e', 'e.id', '=', 'se.enrollment_id')
            ->leftJoin('courses as c', 'c.id', '=', 'e.course_id')
            ->leftJoin('batches as ba', 'ba.id', '=', 'e.batch_id')
            ->leftJoin('student_expense_types as expense_type', 'expense_type.id', '=', 'se.student_expense_type_id')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'p.payment_method_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.recorded_by')
            ->selectRaw("p.id, 'expense' as source, p.payment_number, p.amount, p.paid_at, p.status, p.transaction_reference, p.payer_phone, p.notes, pm.name as payment_method, s.id as student_pk, s.student_code, s.full_name, b.id as branch_id, b.name as branch_name, b.phone as branch_phone, b.email as branch_email, b.address as branch_address, e.id as enrollment_id, e.enrollment_code, c.name as course_name, ba.name as batch_name, COALESCE(expense_type.name, 'Other expense') as category, se.description, se.id as expense_id, u.name as recorded_by_name")
            ->whereNull('se.cancelled_at');
        $this->applyPaymentBranchScope($expenses, $user, 'se.branch_id');

        return DB::query()->fromSub($tuition->unionAll($expenses), 'payments');
    }

    /** Filter source rows before they are combined and paginated. */
    private function applyPaymentBranchScope(QueryBuilder $query, User $user, string $branchColumn): void
    {
        if (! $user->is_super_admin) {
            $query->where($branchColumn, $user->branch_id);
        }
    }

    /** Load enrollments separately so every option follows the same branch scope. */
    private function visibleEnrollments(Student $student, User $user)
    {
        return $student->enrollments()
            ->whereIn('status', ['enrolled', 'ongoing'])
            ->where('is_active', true)
            ->when(! $user->is_super_admin, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
            ->with(['course', 'batch', 'branch'])
            ->orderByDesc('enrollment_date')->get();
    }

    /** Require explicit enrollment choice where a student has more than one billing context. */
    private function resolveEnrollment(array $data, User $user): Enrollment
    {
        $student = Student::query()
            ->where('student_code', $data['student_id'])
            ->when(! $user->is_super_admin, fn (Builder $query) => $query->whereHas('enrollments', fn (Builder $scope) => $scope->where('branch_id', $user->branch_id)))
            ->first();

        if ($student === null) {
            throw ValidationException::withMessages(['student_id' => 'Student was not found in your billing branch.']);
        }

        $query = $student->enrollments()
            ->when(! $user->is_super_admin, fn (Builder $builder) => $builder->where('branch_id', $user->branch_id))
            ->whereIn('status', ['enrolled', 'ongoing'])
            ->where('is_active', true)
            ->with(['course', 'branch']);

        if (! empty($data['enrollment_id'])) {
            $query->whereKey($data['enrollment_id']);
        }

        $enrollments = $query->lockForUpdate()->get();

        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages(['student_id' => 'Student has no eligible enrollment in your billing branch.']);
        }

        if ($enrollments->count() > 1) {
            throw ValidationException::withMessages(['enrollment_id' => 'Choose which enrollment should receive this payment.']);
        }

        return $enrollments->first();
    }

    /** List active bill items only where an approved price is effective for this enrollment. */
    private function expenseOptions(Enrollment $enrollment): array
    {
        return StudentExpenseType::query()
            ->where('institute_id', $enrollment->course->institute_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (StudentExpenseType $item) use ($enrollment): ?array {
                $price = $this->findEffectivePrice($item, $enrollment, now()->toDateString());
                if ($price === null) {
                    return null;
                }

                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => ['id' => $price->id, 'amount' => number_format($this->money($price->amount), 2, '.', '')],
                ];
            })->filter()->values()->all();
    }

    private function effectivePrice(StudentExpenseType $item, Enrollment $enrollment, string $date): StudentExpensePriceVersion
    {
        $price = $this->findEffectivePrice($item, $enrollment, $date);
        if ($price === null) {
            throw ValidationException::withMessages(['student_expense_type_id' => 'This bill item has no approved price effective on the payment date.']);
        }

        return $price;
    }

    /** Select the most specific applicable price, with branch-wide prices as fallback. */
    private function findEffectivePrice(StudentExpenseType $item, Enrollment $enrollment, string $date): ?StudentExpensePriceVersion
    {
        return StudentExpensePriceVersion::query()
            ->where('student_expense_type_id', $item->id)
            ->where('status', 'approved')
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $enrollment->branch_id))
            ->where(fn (Builder $query) => $query->whereNull('course_id')->orWhere('course_id', $enrollment->course_id))
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN course_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('effective_from')->orderByDesc('version_number')->first();
    }

    /** Cash has no account; mobile payments use the branch's active bank account. */
    private function paymentMethodStatus(Enrollment $enrollment, array $data, User $user): array
    {
        $method = PaymentMethod::query()
            ->whereKey($data['payment_method_id'])
            ->where('institute_id', $enrollment->course->institute_id)
            ->where('is_active', true)
            ->first();

        if ($method === null) {
            throw ValidationException::withMessages(['payment_method_id' => 'Choose an active payment method for this institute.']);
        }

        $methodCode = mb_strtolower($method->code);
        $methodName = mb_strtolower(trim($method->name));
        $isCash = $methodName === 'cash' || str_ends_with($methodCode, '_cash');
        $isMobile = $methodName === 'mobile banking' || str_ends_with($methodCode, '_mobile_banking');

        if (! $isCash && ! $isMobile) {
            throw ValidationException::withMessages(['payment_method_id' => 'Only Cash or Mobile Banking (bKash) is currently supported for student billing.']);
        }

        if ($isMobile && (empty($data['transaction_reference']) || empty($data['payer_phone']))) {
            throw ValidationException::withMessages([
                'transaction_reference' => 'Transaction ID is required for bKash.',
                'payer_phone' => 'Payer phone is required for bKash.',
            ]);
        }

        if ($isCash) {
            return [$method, null, 'confirmed', ['method' => 'manual', 'at' => now(), 'by' => $user->getAuthIdentifier()]];
        }

        $account = FinancialAccount::query()
            ->where('institute_id', $enrollment->course->institute_id)
            ->where('account_type', 'bank')
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $enrollment->branch_id))
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderBy('id')
            ->first();

        if ($account === null) {
            throw ValidationException::withMessages(['payment_method_id' => 'Configure an active receiving bank account for this branch before accepting mobile banking payments.']);
        }

        return [$method, $account, 'pending', ['method' => null, 'at' => null, 'by' => null]];
    }

    /** Stable keyed receipt numbers provide a unique idempotency record without schema changes. */
    private function receiptNumber(string $type, string $key, int $enrollmentId): string
    {
        return $type.'-'.strtoupper(substr(hash('sha256', $enrollmentId.'|'.$key), 0, 30));
    }

    private function money(mixed $amount): float
    {
        return round((float) $amount, 2);
    }

    private function formatStudent(Student $student): array
    {
        return ['id' => $student->id, 'student_code' => $student->student_code, 'full_name' => $student->full_name, 'phone' => $student->phone, 'email' => $student->email];
    }

    private function formatEnrollment(Enrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'enrollment_code' => $enrollment->enrollment_code,
            'enrollment_date' => $enrollment->getRawOriginal('enrollment_date'),
            'status' => $enrollment->status?->value ?? (string) $enrollment->status,
            'course' => $enrollment->course ? ['id' => $enrollment->course->id, 'code' => $enrollment->course->code, 'name' => $enrollment->course->name] : null,
            'batch' => $enrollment->batch ? ['id' => $enrollment->batch->id, 'code' => $enrollment->batch->code, 'name' => $enrollment->batch->name] : null,
            'branch' => $enrollment->branch ? ['id' => $enrollment->branch->id, 'name' => $enrollment->branch->name] : null,
        ];
    }

    private function formatPayment(StudentTuitionPayment|StudentExpensePayment $payment, string $source): array
    {
        return [
            'id' => $payment->id,
            'payment_key' => $source.'-'.$payment->id,
            'source' => $source,
            'payment_number' => $payment->payment_number,
            'amount' => number_format($this->money($payment->amount), 2, '.', ''),
            'paid_at' => $payment->paid_at?->toISOString(),
            'status' => $payment->status,
            'payment_method' => $payment->paymentMethod?->name,
            'transaction_reference' => $payment->transaction_reference,
            'payer_phone' => $payment->payer_phone,
            'notes' => $payment->notes,
            'recorded_by_name' => $payment->recorder?->name,
        ];
    }

    private function formatExpense(StudentExpense $expense): array
    {
        $payments = $expense->payments;
        $paid = $this->money($payments->where('status', 'confirmed')->sum('amount'));
        $payable = max(0, $this->money($expense->unit_amount) * (float) $expense->quantity - $this->money($expense->waiver_amount));

        return [
            'id' => $expense->id,
            'expense_number' => $expense->expense_number,
            'student_expense_type' => $expense->expenseType ? ['id' => $expense->expenseType->id, 'code' => $expense->expenseType->code, 'name' => $expense->expenseType->name] : null,
            'description' => $expense->description,
            'status' => $expense->status,
            'is_cancelled' => $expense->cancelled_at !== null,
            'issued_on' => $expense->getRawOriginal('issued_on'),
            'due_on' => $expense->getRawOriginal('due_on'),
            'quantity' => $expense->quantity,
            'unit_amount' => $expense->unit_amount,
            'amount' => number_format($this->money($expense->unit_amount) * (float) $expense->quantity, 2, '.', ''),
            'approved_waiver' => number_format($this->money($expense->waiver_amount), 2, '.', ''),
            'payable_amount' => number_format($payable, 2, '.', ''),
            'paid_amount' => number_format($paid, 2, '.', ''),
            'outstanding_balance' => number_format(max(0, $payable - $paid), 2, '.', ''),
            'payments' => $payments->map(fn (StudentExpensePayment $payment): array => $this->formatPayment($payment, 'expense'))->all(),
        ];
    }
}
