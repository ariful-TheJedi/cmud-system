<?php

namespace App\Models\InstituteBranch;

use App\Models\Finance\FinancialAccount;
use App\Models\Finance\InstituteExpense;
use App\Models\Finance\InstituteExpensePriceVersion;
use App\Models\Finance\StudentExpense;
use App\Models\Finance\StudentExpensePriceVersion;
use App\Models\StudentsAcademy\Batch;
use App\Models\StudentsAcademy\CommunicationGroup;
use App\Models\StudentsAcademy\Enrollment;
use App\Models\StudentsAcademy\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'institute_id',
        'code',
        'name',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(StaffAssignment::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function admittedStudents(): HasMany
    {
        return $this->hasMany(Student::class, 'admission_branch_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function communicationGroups(): HasMany
    {
        return $this->hasMany(CommunicationGroup::class);
    }

    public function studentExpensePriceVersions(): HasMany
    {
        return $this->hasMany(StudentExpensePriceVersion::class);
    }

    public function studentExpenses(): HasMany
    {
        return $this->hasMany(StudentExpense::class);
    }

    public function instituteExpensePriceVersions(): HasMany
    {
        return $this->hasMany(InstituteExpensePriceVersion::class);
    }

    public function instituteExpenses(): HasMany
    {
        return $this->hasMany(InstituteExpense::class);
    }

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
