<?php

namespace App\Models\Academy;

use App\Models\Finance\StudentExpense;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected static function newFactory(): \Database\Factories\Academy\StudentFactory
    {
        return \Database\Factories\Academy\StudentFactory::new();
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
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function admissionBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'admission_branch_id');
    }

    public function medicalCollege(): BelongsTo
    {
        return $this->belongsTo(MedicalCollege::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(StudentGroupMembership::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(StudentExpense::class);
    }
}
