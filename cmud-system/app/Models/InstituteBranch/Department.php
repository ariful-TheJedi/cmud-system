<?php

namespace App\Models\InstituteBranch;

use App\Models\Finance\InstituteExpense;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'institute_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(StaffAssignment::class);
    }

    public function instituteExpenses(): HasMany
    {
        return $this->hasMany(InstituteExpense::class);
    }
}
