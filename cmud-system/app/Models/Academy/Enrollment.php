<?php

namespace App\Models\Academy;

use App\Enums\Academy\EnrollmentStatus;
use App\Models\Finance\StudentExpense;
use App\Models\Finance\StudentTuition;
use App\Models\Institute\Branch;
use Database\Factories\Academy\EnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    protected static function newFactory(): EnrollmentFactory
    {
        return EnrollmentFactory::new();
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
            'status' => EnrollmentStatus::class,
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** @return HasMany<StudentStageRecord, $this> */
    public function stageRecords(): HasMany
    {
        return $this->hasMany(StudentStageRecord::class);
    }

    /** @return HasMany<Certificate, $this> */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /** @return HasOne<StudentTuition, $this> */
    public function tuition(): HasOne
    {
        return $this->hasOne(StudentTuition::class);
    }

    /** @return HasMany<StudentExpense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(StudentExpense::class);
    }

    /** @return HasMany<EnrollmentHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(EnrollmentHistory::class);
    }

    /** @return HasMany<StudentAcademicException, $this> */
    public function academicExceptions(): HasMany
    {
        return $this->hasMany(StudentAcademicException::class);
    }

    /** @return HasMany<StudentCommunication, $this> */
    public function communications(): HasMany
    {
        return $this->hasMany(StudentCommunication::class);
    }

    /** @return HasMany<StudentNotification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(StudentNotification::class);
    }
}
