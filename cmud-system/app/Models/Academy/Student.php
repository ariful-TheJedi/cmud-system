<?php

namespace App\Models\Academy;

use App\Enums\Academy\StudentStatus;
use App\Models\Finance\StudentExpense;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use Database\Factories\Academy\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected static function newFactory(): StudentFactory
    {
        return StudentFactory::new();
    }

    protected $fillable = [
        'institute_id',
        'admission_branch_id',
        'student_code',
        'admission_date',
        'full_name',
        'father_name',
        'mother_name',
        'date_of_birth',
        'gender',
        'phone',
        'alternative_phone',
        'phone_numbers',
        'email',
        'present_address',
        'permanent_address',
        'medical_college_id',
        'qualification',
        'graduation_year',
        'bmdc_number',
        'photo_path',
        'documents',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'admission_date' => 'date',
            'date_of_birth' => 'date',
            'graduation_year' => 'integer',
            'documents' => 'array',
            'phone_numbers' => 'array',
            'status' => StudentStatus::class,
        ];
    }

    /** @return BelongsTo<Institute, $this> */
    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function admissionBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'admission_branch_id');
    }

    /** @return BelongsTo<MedicalCollege, $this> */
    public function medicalCollege(): BelongsTo
    {
        return $this->belongsTo(MedicalCollege::class);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasOne<Enrollment, $this> */
    public function latestEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->latestOfMany();
    }

    /** @return HasMany<StudentGroupMembership, $this> */
    public function groupMemberships(): HasMany
    {
        return $this->hasMany(StudentGroupMembership::class);
    }

    /** @return HasMany<StudentExpense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(StudentExpense::class);
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
