<?php

namespace App\Models\InstituteBranch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    protected $fillable = [
        'branch_id',
        'item_code',
        'item_name',
        'unit',
        'quantity_on_hand',
        'reorder_level',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:2',
            'reorder_level' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
