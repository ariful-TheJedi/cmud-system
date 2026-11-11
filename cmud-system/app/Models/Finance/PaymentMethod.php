<?php

namespace App\Models\Finance;

use App\Models\Institute\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = ['institute_id', 'code', 'name', 'requires_transaction_reference', 'requires_sender_phone', 'is_active', 'notes'];
    protected function casts(): array { return ['requires_transaction_reference' => 'boolean', 'requires_sender_phone' => 'boolean', 'is_active' => 'boolean']; }
    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
    public function studentTuitionPayments(): HasMany { return $this->hasMany(StudentTuitionPayment::class); }
    public function studentExpensePayments(): HasMany { return $this->hasMany(StudentExpensePayment::class); }
    public function instituteExpensePayments(): HasMany { return $this->hasMany(InstituteExpensePayment::class); }
    public function otherIncomeBillPayments(): HasMany { return $this->hasMany(InstituteOtherIncomeBillPayment::class, 'payment_method_id'); }
}
