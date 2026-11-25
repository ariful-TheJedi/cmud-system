<?php

namespace App\Modules\Finance\Services\StudentFinance;

use App\Models\Academy\Enrollment;
use App\Models\Finance\FinancialAccount;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Security\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Enforce tuition payment ownership, branch access, and ledger rules. */
class StudentTuitionPaymentService
{
    /** Collect a payment directly under an enrollment; keep balances out of the receipt payload. */
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function recordCashPayment(Enrollment $enrollment, array $data, User $recordedBy): array
    {
        $transactionResult = DB::transaction(function () use ($enrollment, $data, $recordedBy): array {
            // Serialize receipts for one tuition so concurrent requests cannot over-collect.
            $lockedEnrollment = Enrollment::query()
                ->with('course')
                ->lockForUpdate()
                ->findOrFail($enrollment->id);

            // Keep branch policy in the service so non-HTTP callers cannot bypass it.
            $this->assertBranchAccess($lockedEnrollment, $recordedBy);

            // The unique receipt number is derived from the caller's retry key, making retries safe.
            $paymentNumber = $this->paymentNumber($lockedEnrollment, (string) $data['idempotency_key']);
            $existingPayment = StudentTuitionPayment::query()->where('payment_number', $paymentNumber)->first();
            if ($existingPayment !== null) {
                $expectedMethodId = $data['payment_method_id']
                    ?? PaymentMethod::query()->where('institute_id', $lockedEnrollment->course->institute_id)
                        ->whereRaw('LOWER(name) = ?', ['cash'])->value('id');
                $this->assertSameSubmission($existingPayment, [
                    ...$data,
                    'payment_method_id' => $expectedMethodId,
                ]);

                return ['payment_id' => (int) $existingPayment->id, 'idempotent_replay' => true];
            }

            if ($lockedEnrollment->getRawOriginal('tuition_status') !== 'issued'
                || $lockedEnrollment->getRawOriginal('tuition_cancelled_at') !== null) {
                throw ValidationException::withMessages([
                    'tuition' => 'Payment can only be recorded for issued tuition.',
                ]);
            }

            $instituteId = (int) $lockedEnrollment->course->institute_id;
            $branchId = (int) $lockedEnrollment->branch_id;
            [$method, $account] = $this->paymentConfiguration(
                $instituteId,
                $branchId,
                (int) $data['financial_account_id'],
                isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
            );
            $this->validateMethodRequirements($method, $data);

            // Approved waivers and confirmed payments alone reduce the tuition balance.
            $grossCents = $this->toCents($lockedEnrollment->tuition_original_amount);
            $waiverApproved = $lockedEnrollment->getRawOriginal('tuition_waiver_approved_by') !== null
                && $lockedEnrollment->getRawOriginal('tuition_waiver_approved_at') !== null;
            $waiverCents = $waiverApproved ? $this->toCents($lockedEnrollment->tuition_waiver_amount) : 0;
            $paidCents = $this->toCents($lockedEnrollment->tuitionPayments()
                ->where('status', 'confirmed')
                ->sum('amount'));
            $paymentCents = $this->toCents($data['amount']);
            $dueCents = max(0, $grossCents - $waiverCents - $paidCents);

            if ($paymentCents <= 0 || $paymentCents > $dueCents) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment cannot exceed the tuition balance.',
                ]);
            }

            $payment = StudentTuitionPayment::query()->create([
                'enrollment_id' => $lockedEnrollment->id,
                'payment_number' => $paymentNumber,
                'payment_method_id' => $method->id,
                'financial_account_id' => $account->id,
                'amount' => number_format($paymentCents / 100, 2, '.', ''),
                'paid_at' => $data['paid_at'] ?? now(),
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payer_phone' => $data['payer_phone'] ?? null,
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

        $payment = StudentTuitionPayment::query()
            ->with(['paymentMethod:id,name', 'financialAccount:id,name', 'recorder:id,name'])
            ->findOrFail($paymentId);

        return [
            'enrollment_id' => $enrollment->id,
            'total_paid' => $this->formatCents($this->toCents($enrollment->tuitionPayments()
                ->where('status', 'confirmed')->sum('amount'))),
            'payment' => [
                'id' => $payment->id,
                'enrollment_id' => $payment->enrollment_id,
                'source' => 'tuition',
                'payment_number' => $payment->payment_number,
                'amount' => (string) $payment->amount,
                'paid_at' => Carbon::parse((string) $payment->getRawOriginal('paid_at'))->toIso8601String(),
                'status' => $payment->getRawOriginal('status'),
                'payment_method' => $payment->paymentMethod?->name,
                'payment_method_id' => $payment->payment_method_id,
                'financial_account_id' => $payment->financial_account_id,
                'financial_account' => $payment->financialAccount?->name,
                'transaction_reference' => $payment->transaction_reference,
                'recorded_by_name' => $payment->recorder?->name,
                'notes' => $payment->notes,
            ],
            'idempotent_replay' => $transactionResult['idempotent_replay'],
        ];
    }

    /** Hide another branch's tuition from a regular employee. */
    private function assertBranchAccess(Enrollment $enrollment, User $user): void
    {
        if ($user->is_super_admin) {
            return;
        }

        if ($user->branch_id === null
            || (int) $enrollment->branch_id !== (int) $user->branch_id) {
            abort(404, 'Tuition not found.');
        }
    }

    /** Resolve an active institute payment method and branch-compatible account. */
    /** @return array{0: PaymentMethod, 1: FinancialAccount} */
    private function paymentConfiguration(int $instituteId, int $branchId, int $accountId, ?int $methodId): array
    {
        $account = FinancialAccount::query()
            ->whereKey($accountId)
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->first();

        if ($account === null) {
            throw ValidationException::withMessages([
                'financial_account_id' => 'Select an active financial account for this institute and branch.',
            ]);
        }

        $methods = PaymentMethod::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true);
        $method = $methodId !== null
            ? $methods->whereKey($methodId)->first()
            : $methods->whereRaw('LOWER(name) = ?', ['cash'])->first();

        if ($method === null) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Select an active payment method for this institute.',
            ]);
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
    private function paymentNumber(Enrollment $enrollment, string $idempotencyKey): string
    {
        // Migrated receipts retain their old retry identity; new enrollments use a separate namespace.
        $identity = $enrollment->tuition_legacy_id !== null
            ? 'tuition|'.$enrollment->tuition_legacy_id
            : 'enrollment|'.$enrollment->id;
        $digest = Str::upper(substr(hash('sha256', $identity.'|'.$idempotencyKey), 0, 26));

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
            || (int) $payment->payment_method_id !== (int) ($data['payment_method_id'] ?? 0)
            || (string) ($payment->transaction_reference ?? '') !== (string) ($data['transaction_reference'] ?? '')
            || (string) ($payment->payer_phone ?? '') !== (string) ($data['payer_phone'] ?? '')
            || (string) ($payment->notes ?? '') !== (string) ($data['notes'] ?? '')
            || $paidAtChanged) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This idempotency key has already been used for a different payment.',
            ]);
        }
    }
}
