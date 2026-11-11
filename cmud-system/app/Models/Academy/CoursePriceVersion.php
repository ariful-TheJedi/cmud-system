<?php

namespace App\Models\Academy;

use App\Models\Institute\Branch;
use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoursePriceVersion extends Model
{
    protected $fillable = [
        'course_id',
        'branch_id',
        'version_number',
        'amount',
        'effective_from',
        'effective_to',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function studentTuitions(): HasMany
    {
        return $this->hasMany(\App\Models\Finance\StudentTuition::class, 'course_price_version_id');
    }
}
