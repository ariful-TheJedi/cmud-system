<?php

namespace App\Models\Finance;

use App\Models\Institute\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstituteExpenseType extends Model
{
    protected $fillable = ['institute_id', 'code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(InstituteExpenseCategory::class, 'institute_expense_type_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(InstituteExpense::class);
    }
}
