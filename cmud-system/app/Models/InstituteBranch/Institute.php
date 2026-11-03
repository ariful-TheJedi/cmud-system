<?php

namespace App\Models\InstituteBranch;

use App\Models\Finance\FinancialAccount;
use App\Models\Finance\InstituteExpense;
use App\Models\Finance\InstituteExpenseCategory;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\StudentExpenseType;
use App\Models\StudentsAcademy\CertificateTemplate;
use App\Models\StudentsAcademy\CommunicationGroup;
use App\Models\StudentsAcademy\Course;
use App\Models\StudentsAcademy\Student;
use App\Models\StudentsAcademy\Teacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institute extends Model
{
    protected $fillable = [
        'code',
        'name',
        'phone',
        'email',
        'website',
        'address',
        'logo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function assetCategories(): HasMany
    {
        return $this->hasMany(AssetCategory::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function certificateTemplates(): HasMany
    {
        return $this->hasMany(CertificateTemplate::class);
    }

    public function communicationGroups(): HasMany
    {
        return $this->hasMany(CommunicationGroup::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function studentExpenseTypes(): HasMany
    {
        return $this->hasMany(StudentExpenseType::class);
    }

    public function instituteExpenseCategories(): HasMany
    {
        return $this->hasMany(InstituteExpenseCategory::class);
    }

    public function instituteExpenses(): HasMany
    {
        return $this->hasMany(InstituteExpense::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }
}
