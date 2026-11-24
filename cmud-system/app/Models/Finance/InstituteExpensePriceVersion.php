<?php

namespace App\Models\Finance;

use App\Models\Institute\Branch;
use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstituteExpensePriceVersion extends Model
{
    protected $fillable = ['institute_expense_category_id', 'branch_id', 'version_number', 'amount', 'unit_name', 'effective_from', 'effective_to', 'status', 'approved_by', 'approved_at', 'notes'];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'amount' => 'decimal:2', 'effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'datetime'];
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(InstituteExpenseCategory::class, 'institute_expense_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(InstituteExpense::class);
    }
}
