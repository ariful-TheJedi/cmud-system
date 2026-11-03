<?php

namespace App\Models\StudentsAcademy;

use App\Models\Finance\StudentExpensePriceVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'duration_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function studentStageRecords(): HasMany
    {
        return $this->hasMany(StudentStageRecord::class);
    }

    public function studentExpensePriceVersions(): HasMany
    {
        return $this->hasMany(StudentExpensePriceVersion::class);
    }
}
