<?php

namespace App\Models\Academy;

use App\Enums\Academy\CourseStatus;
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
        'status',
        'is_available_for_enrollment',
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'completion_period_months' => 'integer',
            'is_active' => 'boolean',
            'status' => CourseStatus::class,
            'is_available_for_enrollment' => 'boolean',
        ];
    }

    /** @return BelongsTo<Institute, $this> */
    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    /** @return HasMany<CourseStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(CourseStage::class);
    }

    /** @return HasMany<Batch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<CertificateTemplate, $this> */
    public function certificateTemplates(): HasMany
    {
        return $this->hasMany(CertificateTemplate::class);
    }

    /** @return HasMany<CommunicationGroup, $this> */
    public function communicationGroups(): HasMany
    {
        return $this->hasMany(CommunicationGroup::class);
    }

    /** A course contains its module records; each subject belongs to at most one course. */
    /** @return HasMany<Subject, $this> */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    /** @return HasMany<CoursePriceVersion, $this> */
    public function priceVersions(): HasMany
    {
        return $this->hasMany(CoursePriceVersion::class);
    }

    /** @return HasMany<StudentExpensePriceVersion, $this> */
    public function studentExpensePriceVersions(): HasMany
    {
        return $this->hasMany(StudentExpensePriceVersion::class);
    }
}
