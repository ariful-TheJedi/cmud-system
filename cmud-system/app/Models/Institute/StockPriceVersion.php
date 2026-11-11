<?php

namespace App\Models\Institute;

use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockPriceVersion extends Model
{
    protected $fillable = [
        'stock_id',
        'stock_category_id',
        'version_number',
        'unit_cost',
        'effective_from',
        'effective_to',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'unit_cost' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function stockCategory(): BelongsTo
    {
        return $this->belongsTo(StockCategory::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
