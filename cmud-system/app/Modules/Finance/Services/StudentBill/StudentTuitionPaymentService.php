<?php

namespace App\Modules\Finance\Services\StudentBill;

use App\Models\Academy\Enrollment;
use App\Models\Finance\FinancialAccount;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\StudentTuition;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Security\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Enforce tuition payment ownership, branch access, and cash-ledger rules. */
class StudentTuitionPaymentService
{
    /** Return only active cash accounts usable for the tuition's institute and branch. */
    /** @return array<string, mixed> */
    public function paymentOptions(StudentTuition $tuition, User $viewer): array
    {
        $tuition->loadMissing('enrollment.course');
        $this->assertBranchAccess($tuition, $viewer);

        $instituteId = (int) $tuition->enrollment->course->institute_id;
        $branchId = (int) $tuition->enrollment->branch_id;

        return [
            'tuition_id' => $tuition->id,
            'tuition_number' => $tuition->tuition_number,
            'status' => $tuition->getRawOriginal('status'),
            'cash_accounts' => FinancialAccount::query()
                ->where('institute_id', $instituteId)
                ->where('account_type', 'cash')
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId))
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'branch_id'])
                ->toArray(),
        ];
    }

    /** Verify the requested enrollment owns tuition before handling a nested payment route. */
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function recordEnrollmentCashPayment(
        Enrollment $enrollment,
        StudentTuition $tuition,
        array $data,
        User $recordedBy,
    ): array {
        $tuition->loadMissing('enrollment.course');
        if ((int) $tuition->enrollment_id !== (int) $enrollment->id) {
            abort(404, 'Tuition not found.');
        }

        return $this->recordCashPayment($tuition, $data, $recordedBy);
    }

    /** Record an immediately confirmed cash receipt without allowing overpayment. */
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function recordCashPayment(StudentTuition $tuition, array $data, User $recordedBy): array
    {
        $transactionResult = DB::transaction(function () use ($tuition, $data, $recordedBy): array {
            // Serialize receipts for one tuition so concurrent requests cannot over-collect.
            $lockedTuition = StudentTuition::query()
                ->with('enrollment.course')
                ->lockForUpdate()
                ->findOrFail($tuition->id);

            // Keep branch policy in the service so non-HTTP callers cannot bypass it.
            $this->assertBranchAccess($lockedTuition, $recordedBy);

            // The unique receipt number is derived from the caller's retry key, making retries safe.
            $paymentNumber = $this->paymentNumber($lockedTuition->id, (string) $data['idempotency_key']);
            $existingPayment = StudentTuitionPayment::query()->where('payment_number', $paymentNumber)->first();
            if ($existingPayment !== null) {
                $this->assertSameSubmission($existingPayment, $data);

                return ['payment_id' => (int) $existingPayment->id, 'idempotent_replay' => true];
            }

            if ($lockedTuition->getRawOriginal('status') !== 'issued'
                || $lockedTuition->getRawOriginal('cancelled_at') !== null) {
                throw ValidationException::withMessages([
                    'tuition' => 'Payment can only be recorded for issued tuition.',
                ]);
            }

            $instituteId = (int) $lockedTuition->enrollment->course->institute_id;
            $branchId = (int) $lockedTuition->enrollment->branch_id;
            [$cashMethod, $cashAccount] = $this->cashPaymentConfiguration(
                $instituteId,
                $branchId,
                (int) $data['financial_account_id'],
            );

            // Approved waivers and confirmed payments alone reduce the tuition balance.
            $grossCents = $this->toCents($lockedTuition->original_amount);
            $waiverApproved = $lockedTuition->getRawOriginal('waiver_approved_by') !== null
                && $lockedTuition->getRawOriginal('waiver_approved_at') !== null;
            $waiverCents = $waiverApproved ? $this->toCents($lockedTuition->waiver_amount) : 0;
            $paidCents = $this->toCents($lockedTuition->payments()
                ->where('status', 'confirmed')
                ->sum('amount'));
            $paymentCents = $this->toCents($data['amount']);
            $dueCents = max(0, $grossCents - $waiverCents - $paidCents);

            if ($paymentCents > $dueCents) {
                throw ValidationException::withMessages([
                    'amount' => 'The cash payment cannot exceed the tuition balance.',
                ]);
            }

            $payment = StudentTuitionPayment::query()->create([
                'student_tuition_id' => $lockedTuition->id,
                'payment_number' => $paymentNumber,
                'payment_method_id' => $cashMethod->id,
                'financial_account_id' => $cashAccount->id,
                'amount' => number_format($paymentCents / 100, 2, '.', ''),
                'paid_at' => $data['paid_at'] ?? now(),
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'status' => 'confirmed',
                'verification_method' => 'manual',
                'verified_by' => $recordedBy->getAuthIdentifier(),
                'verified_at' => now(),
                'recorded_by' => $recordedBy->getAuthIdentifier(),
                'notes' => $data['notes'] ?? null,
            ]);

            return ['payment_id' => (int) $payment->id, 'idempotent_replay' => false];
        });
        $paymentId = $transactionResult['payment_id'];

        $tuition->load(['payments.paymentMethod:id,name', 'payments.financialAccount:id,name']);
        $paidCents = $this->toCents($tuition->payments->where('status', 'confirmed')->sum('amount'));
        $grossCents = $this->toCents($tuition->original_amount);
        $waiverApproved = $tuition->getRawOriginal('waiver_approved_by') !== null
            && $tuition->getRawOriginal('waiver_approved_at') !== null;
        $waiverCents = $waiverApproved ? $this->toCents($tuition->waiver_amount) : 0;

        return [
            'tuition' => [
                'id' => $tuition->id,
                'tuition_number' => $tuition->tuition_number,
                'status' => $tuition->getRawOriginal('status'),
                'original_amount' => (string) $tuition->original_amount,
                'approved_waiver' => $this->formatCents($waiverCents),
                'payable_amount' => $this->formatCents(max(0, $grossCents - $waiverCents)),
                'paid_amount' => $this->formatCents($paidCents),
                'due_amount' => $this->formatCents(max(0, $grossCents - $waiverCents - $paidCents)),
            ],
            'payment' => $tuition->payments->firstWhere('id', $paymentId)?->only([
                'id', 'payment_number', 'amount', 'paid_at', 'status', 'payment_method_id', 'financial_account_id',
            ]),
            'idempotent_replay' => $transactionResult['idempotent_replay'],
        ];
    }

    /** Hide another branch's tuition from a regular employee. */
    private function assertBranchAccess(StudentTuition $tuition, User $user): void
    {
        if ($user->is_super_admin) {
            return;
        }

        if ($user->branch_id === null
            || (int) $tuition->enrollment->branch_id !== (int) $user->branch_id) {
            abort(404, 'Tuition not found.');
        }
    }

    /** Resolve an active cash method and branch-compatible active cash account. */
    /** @return array{0: PaymentMethod, 1: FinancialAccount} */
    private function cashPaymentConfiguration(int $instituteId, int $branchId, int $accountId): array
    {
        $cashAccount = FinancialAccount::query()
            ->whereKey($accountId)
            ->where('institute_id', $instituteId)
            ->where('account_type', 'cash')
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->first();

        if ($cashAccount === null) {
            throw ValidationException::withMessages([
                'financial_account_id' => 'Select an active cash account for this institute and branch.',
            ]);
        }

        $cashMethod = PaymentMethod::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', ['cash'])
            ->first();

        if ($cashMethod === null) {
            throw ValidationException::withMessages([
                'cash_payment' => 'Cash payment is not configured for this institute.',
            ]);
        }

        return [$cashMethod, $cashAccount];
    }

    /** Convert decimal currency to integer cents before comparing balances. */
    private function toCents(mixed $amount): int
    {
        $decimal = trim((string) $amount);
        $negative = str_starts_with($decimal, '-');
        $decimal = ltrim($decimal, '+-');
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    /** Format an integer cent value as a stable decimal currency string. */
    private function formatCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    /** Build a stable, unique receipt reference for safe client retries. */
    private function paymentNumber(int $tuitionId, string $idempotencyKey): string
    {
        $digest = Str::upper(substr(hash('sha256', 'tuition|'.$tuitionId.'|'.$idempotencyKey), 0, 26));

        return 'STP-'.$digest;
    }

    /** Reject accidental reuse of a retry key for a different payment payload. */
    /** @param array<string, mixed> $data */
    private function assertSameSubmission(StudentTuitionPayment $payment, array $data): void
    {
        $paidAtChanged = array_key_exists('paid_at', $data)
            && Carbon::parse((string) $data['paid_at'])->getTimestamp()
                !== Carbon::parse((string) $payment->getRawOriginal('paid_at'))->getTimestamp();

        if ($this->toCents($payment->amount) !== $this->toCents($data['amount'])
            || (int) $payment->financial_account_id !== (int) $data['financial_account_id']
            || (string) ($payment->transaction_reference ?? '') !== (string) ($data['transaction_reference'] ?? '')
            || (string) ($payment->notes ?? '') !== (string) ($data['notes'] ?? '')
            || $paidAtChanged) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This idempotency key has already been used for a different payment.',
            ]);
        }
    }
}
