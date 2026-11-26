<?php

namespace App\Models\Academy;

use App\Enums\Academy\EnrollmentStatus;
use App\Models\Finance\StudentExpense;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Institute\Branch;
use App\Models\Security\User;
use Database\Factories\Academy\EnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'tuition_number',
        'course_price_version_id',
        'tuition_original_amount',
        'tuition_waiver_amount',
        'tuition_waiver_reason',
        'tuition_waiver_approved_by',
        'tuition_waiver_approved_at',
        'tuition_issued_on',
        'tuition_due_on',
        'tuition_status',
        'tuition_cancelled_at',
        'tuition_cancellation_reason',
        'tuition_notes',
    ];

    protected $appends = ['tuition'];

    protected $hidden = ['tuition_legacy_id'];

    protected function casts(): array
    {
        return [
            'tuition_original_amount' => 'decimal:2',
            'tuition_waiver_amount' => 'decimal:2',
            'tuition_waiver_approved_at' => 'datetime',
            'tuition_issued_on' => 'date',
            'tuition_due_on' => 'date',
            'tuition_cancelled_at' => 'datetime',
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

    /** @return HasMany<StudentTuitionPayment, $this> */
    public function tuitionPayments(): HasMany
    {
        return $this->hasMany(StudentTuitionPayment::class);
    }

    /** @return BelongsTo<CoursePriceVersion, $this> */
    public function coursePriceVersion(): BelongsTo
    {
        return $this->belongsTo(CoursePriceVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function tuitionWaiverApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tuition_waiver_approved_by');
    }

    public function hasTuition(): bool
    {
        return $this->tuition_original_amount !== null;
    }

    /**
     * Keep the existing student-profile JSON shape while storing tuition on enrollment.
     *
     * @return array<string, mixed>|null
     */
    public function getTuitionAttribute(): ?array
    {
        $payments = $this->relationLoaded('tuitionPayments') ? $this->tuitionPayments : collect();
        if (! $this->hasTuition() && $payments->isEmpty()) {
            return null;
        }

        return [
            'id' => $this->id,
            'tuition_number' => $this->tuition_number,
            'original_amount' => $this->tuition_original_amount,
            'waiver_amount' => $this->tuition_waiver_amount,
            'waiver_approved_by' => $this->tuition_waiver_approved_by,
            'waiver_approved_at' => $this->tuition_waiver_approved_at,
            'issued_on' => $this->getRawOriginal('tuition_issued_on'),
            'due_on' => $this->getRawOriginal('tuition_due_on'),
            'status' => $this->tuition_status,
            'cancelled_at' => $this->tuition_cancelled_at,
            'payments' => $payments->toArray(),
        ];
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
