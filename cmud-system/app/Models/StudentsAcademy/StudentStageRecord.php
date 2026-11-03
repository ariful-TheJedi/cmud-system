<?php

namespace App\Models\StudentsAcademy;

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
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function courseStage(): BelongsTo
    {
        return $this->belongsTo(CourseStage::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function examRecords(): HasMany
    {
        return $this->hasMany(ExamRecord::class);
    }

    public function studentExpenses(): HasMany
    {
        return $this->hasMany(StudentExpense::class);
    }
}
