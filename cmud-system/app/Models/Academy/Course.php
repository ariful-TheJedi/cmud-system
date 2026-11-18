<?php

namespace App\Models\Academy;

use App\Models\Finance\StudentExpensePriceVersion;
use App\Models\Institute\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'institute_id',
        'code',
        'name',
        'description',
        'duration_months',
        'completion_period_months',
        'course_mode',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'completion_period_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(CourseStage::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function certificateTemplates(): HasMany
    {
        return $this->hasMany(CertificateTemplate::class);
    }

    public function communicationGroups(): HasMany
    {
        return $this->hasMany(CommunicationGroup::class);
    }

    /** A course contains its module records; each subject belongs to at most one course. */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function priceVersions(): HasMany
    {
        return $this->hasMany(CoursePriceVersion::class);
    }

    public function studentExpensePriceVersions(): HasMany
    {
        return $this->hasMany(StudentExpensePriceVersion::class);
    }
}
