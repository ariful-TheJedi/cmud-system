<?php

namespace App\Models\InstituteBranch;

use App\Models\Finance\InstituteExpense;
use App\Models\StudentsAcademy\Teacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = [
        'institute_id',
        'staff_code',
        'full_name',
        'phone',
        'email',
        'address',
        'date_of_birth',
        'photo_path',
        'designation',
        'joining_date',
        'leaving_date',
        'employment_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'leaving_date' => 'date',
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

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function instituteExpenses(): HasMany
    {
        return $this->hasMany(InstituteExpense::class);
    }
}
