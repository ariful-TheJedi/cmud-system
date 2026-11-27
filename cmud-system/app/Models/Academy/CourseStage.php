<?php

namespace App\Models\Academy;

use App\Models\Finance\StudentExpensePriceVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseStage extends Model
{
    protected $fillable = [
        'course_id',
        'code',
        'name',
        'sequence_number',
        'duration_months',
        'description',
        'is_active',
        'is_mandatory',
        'requires_exam',
        'certificate_enabled',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'duration_months' => 'integer',
            'is_active' => 'boolean',
            'is_mandatory' => 'boolean',
            'requires_exam' => 'boolean',
            'certificate_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<StudentStageRecord, $this> */
    public function studentStageRecords(): HasMany
    {
        return $this->hasMany(StudentStageRecord::class);
    }

    /** @return BelongsToMany<Subject, $this> */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'course_stage_subject')->withTimestamps();
    }

    /** @return HasMany<StudentExpensePriceVersion, $this> */
    public function studentExpensePriceVersions(): HasMany
    {
        return $this->hasMany(StudentExpensePriceVersion::class);
    }
}
