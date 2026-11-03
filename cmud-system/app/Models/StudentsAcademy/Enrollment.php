<?php

namespace App\Models\StudentsAcademy;

use App\Models\Finance\StudentExpense;
use App\Models\Finance\StudentTuition;
use App\Models\InstituteBranch\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollment extends Model
{
    protected $fillable = [
        'enrollment_code',
        'student_id',
        'course_id',
        'branch_id',
        'batch_id',
        'enrollment_date',
        'starts_on',
        'expected_completion_on',
        'completion_deadline_on',
        'completed_on',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
            'starts_on' => 'date',
            'expected_completion_on' => 'date',
            'completion_deadline_on' => 'date',
            'completed_on' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function stageRecords(): HasMany
    {
        return $this->hasMany(StudentStageRecord::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function tuition(): HasOne
    {
        return $this->hasOne(StudentTuition::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(StudentExpense::class);
    }
}
