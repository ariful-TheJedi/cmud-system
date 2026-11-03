<?php

namespace App\Models\UserAccess;

use App\Models\Finance\AccountTransfer;
use App\Models\Finance\InstituteExpense;
use App\Models\Finance\InstituteExpensePayment;
use App\Models\Finance\InstituteExpensePriceVersion;
use App\Models\Finance\StudentExpense;
use App\Models\Finance\StudentExpensePayment;
use App\Models\Finance\StudentExpensePriceVersion;
use App\Models\Finance\StudentTuition;
use App\Models\Finance\StudentTuitionPayment;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'phone', 'designation', 'is_active', 'is_super_admin', 'permission_group_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    public function permissionGroup(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function passwordResetTokens(): HasMany
    {
        return $this->hasMany(PasswordResetToken::class, 'email', 'email');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    public function approvedTuitionWaivers(): HasMany { return $this->hasMany(StudentTuition::class, 'waiver_approved_by'); }
    public function verifiedTuitionPayments(): HasMany { return $this->hasMany(StudentTuitionPayment::class, 'verified_by'); }
    public function recordedTuitionPayments(): HasMany { return $this->hasMany(StudentTuitionPayment::class, 'recorded_by'); }
    public function approvedStudentExpensePrices(): HasMany { return $this->hasMany(StudentExpensePriceVersion::class, 'approved_by'); }
    public function approvedStudentExpenseWaivers(): HasMany { return $this->hasMany(StudentExpense::class, 'waiver_approved_by'); }
    public function recordedStudentExpenses(): HasMany { return $this->hasMany(StudentExpense::class, 'recorded_by'); }
    public function verifiedStudentExpensePayments(): HasMany { return $this->hasMany(StudentExpensePayment::class, 'verified_by'); }
    public function recordedStudentExpensePayments(): HasMany { return $this->hasMany(StudentExpensePayment::class, 'recorded_by'); }
    public function approvedInstituteExpensePrices(): HasMany { return $this->hasMany(InstituteExpensePriceVersion::class, 'approved_by'); }
    public function approvedInstituteExpenses(): HasMany { return $this->hasMany(InstituteExpense::class, 'approved_by'); }
    public function recordedInstituteExpenses(): HasMany { return $this->hasMany(InstituteExpense::class, 'recorded_by'); }
    public function verifiedInstituteExpensePayments(): HasMany { return $this->hasMany(InstituteExpensePayment::class, 'verified_by'); }
    public function recordedInstituteExpensePayments(): HasMany { return $this->hasMany(InstituteExpensePayment::class, 'recorded_by'); }
    public function approvedAccountTransfers(): HasMany { return $this->hasMany(AccountTransfer::class, 'approved_by'); }
    public function confirmedAccountTransfers(): HasMany { return $this->hasMany(AccountTransfer::class, 'confirmed_by'); }
    public function recordedAccountTransfers(): HasMany { return $this->hasMany(AccountTransfer::class, 'recorded_by'); }
}
