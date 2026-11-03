<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstituteExpenseType extends Model
{
    protected $fillable = ['institute_expense_category_id', 'code', 'name', 'description', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function category(): BelongsTo { return $this->belongsTo(InstituteExpenseCategory::class, 'institute_expense_category_id'); }
    public function priceVersions(): HasMany { return $this->hasMany(InstituteExpensePriceVersion::class); }
    public function expenses(): HasMany { return $this->hasMany(InstituteExpense::class); }
}
