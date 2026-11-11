<?php

namespace App\Models\Finance;

use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstituteOtherIncomeBillPayment extends Model
{
    protected $table = 'institute_other_income_bill_payment';

    protected $fillable = [
        'institute_income_bill_id',
        'payment_number',
        'payment_method_id',
        'financial_account_id',
        'amount',
        'status',
        'paid_at',
        'transaction_reference',
        'proof_file_path',
        'received_by',
        'recorded_by',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function incomeBill(): BelongsTo
    {
        return $this->belongsTo(InstituteIncomeBill::class, 'institute_income_bill_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
