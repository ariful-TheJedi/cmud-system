<?php

namespace App\Models\Finance;

use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstituteIncomeBill extends Model
{
    protected $table = 'institute_income_bill';

    protected $fillable = [
        'university_name',
        'bill_number',
        'bill_items',
        'total_amount',
        'bill_date',
        'due_on',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'bill_items' => 'array',
            'total_amount' => 'decimal:2',
            'bill_date' => 'date',
            'due_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InstituteOtherIncomeBillPayment::class, 'institute_income_bill_id');
    }
}
