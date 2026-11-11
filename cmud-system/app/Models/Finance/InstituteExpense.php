<?php

namespace App\Models\Finance;

use App\Models\Institute\Asset;
use App\Models\Institute\Branch;
use App\Models\Institute\Department;
use App\Models\Institute\Staff;
use App\Models\Academy\Teacher;
use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstituteExpense extends Model
{
    protected $fillable = ['expense_number', 'branch_id', 'department_id', 'institute_expense_type_id', 'institute_expense_price_version_id', 'staff_id', 'teacher_id', 'asset_id', 'payee_name', 'invoice_number', 'description', 'quantity', 'unit_amount', 'expense_date', 'service_period_from', 'service_period_to', 'due_on', 'bill_file_path', 'status', 'approved_by', 'approved_at', 'rejection_reason', 'cancelled_at', 'cancellation_reason', 'recorded_by', 'notes'];
    protected function casts(): array { return ['quantity' => 'decimal:3', 'unit_amount' => 'decimal:2', 'expense_date' => 'date', 'service_period_from' => 'date', 'service_period_to' => 'date', 'due_on' => 'date', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime']; }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function expenseType(): BelongsTo { return $this->belongsTo(InstituteExpenseType::class, 'institute_expense_type_id'); }
    public function priceVersion(): BelongsTo { return $this->belongsTo(InstituteExpensePriceVersion::class, 'institute_expense_price_version_id'); }
    public function staff(): BelongsTo { return $this->belongsTo(Staff::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(Teacher::class); }
    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function payments(): HasMany { return $this->hasMany(InstituteExpensePayment::class); }
}
