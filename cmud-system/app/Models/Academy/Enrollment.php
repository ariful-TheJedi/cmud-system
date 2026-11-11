<?php

namespace App\Models\Academy;

use App\Models\Finance\StudentExpense;
use App\Models\Finance\StudentTuition;
use App\Models\Institute\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollment extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected static function newFactory(): \Database\Factories\Academy\EnrollmentFactory
    {
        return \Database\Factories\Academy\EnrollmentFactory::new();
    }

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
        'is_active',
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
            'is_active' => 'boolean',
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
