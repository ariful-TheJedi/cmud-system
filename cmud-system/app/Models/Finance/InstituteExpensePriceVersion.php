<?php

namespace App\Models\Finance;

use App\Models\InstituteBranch\Branch;
use App\Models\UserAccess\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstituteExpensePriceVersion extends Model
{
    protected $fillable = ['institute_expense_type_id', 'branch_id', 'version_number', 'amount', 'unit_name', 'effective_from', 'effective_to', 'status', 'approved_by', 'approved_at', 'notes'];
    protected function casts(): array { return ['version_number' => 'integer', 'amount' => 'decimal:2', 'effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'datetime']; }
    public function expenseType(): BelongsTo { return $this->belongsTo(InstituteExpenseType::class, 'institute_expense_type_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function expenses(): HasMany { return $this->hasMany(InstituteExpense::class); }
}
