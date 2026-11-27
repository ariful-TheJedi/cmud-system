<?php

namespace App\Models\Academy;

use App\Models\Institute\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = [
        'course_id',
        'branch_id',
        'code',
        'name',
        'admission_opens_on',
        'admission_closes_on',
        'starts_on',
        'expected_ends_on',
        'actual_ends_on',
        'capacity',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'admission_opens_on' => 'date',
            'admission_closes_on' => 'date',
            'starts_on' => 'date',
            'expected_ends_on' => 'date',
            'actual_ends_on' => 'date',
            'capacity' => 'integer',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Return the students who currently occupy a batch place for capacity reporting. */
    public function currentlyEnrolledStudents(): HasMany
    {
        return $this->enrollments()->whereIn('status', ['enrolled', 'ongoing', 'on_hold']);
    }

    /** @return HasMany<StudentStageRecord, $this> */
    public function studentStageRecords(): HasMany
    {
        return $this->hasMany(StudentStageRecord::class);
    }

    /** @return HasMany<CommunicationGroup, $this> */
    public function communicationGroups(): HasMany
    {
        return $this->hasMany(CommunicationGroup::class);
    }
}
