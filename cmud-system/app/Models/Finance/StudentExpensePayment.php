<?php

namespace App\Models\Finance;

use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property-read StudentExpense|null $expense */
class StudentExpensePayment extends Model
{
    protected $table = 'student_other_payments';

    protected $fillable = ['student_expense_id', 'payment_number', 'payment_method_id', 'financial_account_id', 'amount', 'paid_at', 'transaction_reference', 'payer_phone', 'proof_file_path', 'status', 'verification_method', 'verified_at', 'verified_by', 'verification_reference', 'verification_response', 'rejection_reason', 'recorded_by', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'verified_at' => 'datetime', 'verification_response' => 'array'];
    }

    /** @return BelongsTo<StudentExpense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(StudentExpense::class, 'student_expense_id');
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
