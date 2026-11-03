<?php

namespace App\Models\Finance;

use App\Models\UserAccess\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTransfer extends Model
{
    protected $fillable = ['transfer_number', 'from_financial_account_id', 'to_financial_account_id', 'amount', 'transferred_at', 'transaction_reference', 'proof_file_path', 'status', 'approved_by', 'approved_at', 'confirmed_by', 'confirmed_at', 'rejection_reason', 'recorded_by', 'notes'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'transferred_at' => 'datetime', 'approved_at' => 'datetime', 'confirmed_at' => 'datetime']; }
    public function fromAccount(): BelongsTo { return $this->belongsTo(FinancialAccount::class, 'from_financial_account_id'); }
    public function toAccount(): BelongsTo { return $this->belongsTo(FinancialAccount::class, 'to_financial_account_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function confirmer(): BelongsTo { return $this->belongsTo(User::class, 'confirmed_by'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
