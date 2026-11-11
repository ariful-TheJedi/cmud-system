<?php

namespace App\Models\Academy;

use App\Models\Finance\InstituteExpense;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use App\Models\Institute\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    protected $fillable = [
        'institute_id',
        'staff_id',
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

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function courseAssignments(): HasMany
    {
        return $this->hasMany(CourseTeacher::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_teacher')
            ->withPivot(['teaching_role', 'is_active', 'notes'])
            ->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_teacher')->withTimestamps();
    }

    public function instituteExpenses(): HasMany
    {
        return $this->hasMany(InstituteExpense::class);
    }
}
