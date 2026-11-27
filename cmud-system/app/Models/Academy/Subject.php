<?php

namespace App\Models\Academy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    protected $fillable = [
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

    /** Courses reuse standalone modules through the course_subject pivot. */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_subject')->withTimestamps();
    }

    /** Courses may place this reusable module in one or more of their stages. */
    public function courseStages(): BelongsToMany
    {
        return $this->belongsToMany(CourseStage::class, 'course_stage_subject')->withTimestamps();
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'subject_teacher')
            ->withPivot(['teaching_role', 'is_active', 'notes'])
            ->withTimestamps();
    }
}
