<?php

namespace App\Models\Academy;

use App\Models\Finance\InstituteExpense;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use App\Models\Institute\Stuff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    protected $fillable = [
        'institute_id',
        'stuff_id',
        'teacher_code',
        'full_name',
        'phone',
        'email',
        'qualification',
        'specialization',
        'designation',
        'organization',
        'bmdc_number',
        'address',
        'photo_path',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Institute, $this> */
    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    /** @return BelongsTo<Stuff, $this> */
    public function stuff(): BelongsTo
    {
        return $this->belongsTo(Stuff::class);
    }

    /** Subjects are the direct teaching assignment targets for a teacher. */
    /** @return BelongsToMany<Subject, $this> */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher')
            ->withPivot(['teaching_role', 'is_active', 'notes'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_teacher')->withTimestamps();
    }

    /** @return HasMany<InstituteExpense, $this> */
    public function instituteExpenses(): HasMany
    {
        return $this->hasMany(InstituteExpense::class);
    }
}
