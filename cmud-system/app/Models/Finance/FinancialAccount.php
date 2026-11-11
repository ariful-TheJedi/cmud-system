<?php

namespace App\Models\Finance;

use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    protected $fillable = ['institute_id', 'branch_id', 'code', 'name', 'account_type', 'provider_name', 'account_holder_name', 'account_number', 'bank_branch_name', 'routing_number', 'currency_code', 'opening_balance', 'opening_balance_as_of', 'is_active', 'notes'];
    protected function casts(): array { return ['opening_balance' => 'decimal:2', 'opening_balance_as_of' => 'datetime', 'is_active' => 'boolean']; }
    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function studentTuitionPayments(): HasMany { return $this->hasMany(StudentTuitionPayment::class); }
    public function studentExpensePayments(): HasMany { return $this->hasMany(StudentExpensePayment::class); }
    public function instituteExpensePayments(): HasMany { return $this->hasMany(InstituteExpensePayment::class); }
    public function otherIncomeBillPayments(): HasMany { return $this->hasMany(InstituteOtherIncomeBillPayment::class, 'financial_account_id'); }
    public function outgoingTransfers(): HasMany { return $this->hasMany(AccountTransfer::class, 'from_financial_account_id'); }
    public function incomingTransfers(): HasMany { return $this->hasMany(AccountTransfer::class, 'to_financial_account_id'); }
}
