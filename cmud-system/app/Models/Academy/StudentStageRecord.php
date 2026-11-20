<?php

namespace App\Models\Academy;

use App\Enums\Academy\StudentStageStatus;
use App\Models\Finance\StudentExpense;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentStageRecord extends Model
{
    protected $fillable = [
        'enrollment_id',
        'course_stage_id',
        'batch_id',
        'started_on',
        'expected_completion_on',
        'completed_on',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'expected_completion_on' => 'date',
            'completed_on' => 'date',
            'status' => StudentStageStatus::class,
        ];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<CourseStage, $this> */
    public function courseStage(): BelongsTo
    {
        return $this->belongsTo(CourseStage::class);
    }

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** @return HasMany<ExamRecord, $this> */
    public function examRecords(): HasMany
    {
        return $this->hasMany(ExamRecord::class);
    }

    /** @return HasMany<StudentExpense, $this> */
    public function studentExpenses(): HasMany
    {
        return $this->hasMany(StudentExpense::class);
    }

    /** @return HasMany<StudentStageHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(StudentStageHistory::class);
    }

    /** @return HasMany<StudentAcademicException, $this> */
    public function academicExceptions(): HasMany
    {
        return $this->hasMany(StudentAcademicException::class);
    }
}
