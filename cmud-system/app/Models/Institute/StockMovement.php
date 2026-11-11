<?php

namespace App\Models\Institute;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'stock_id',
        'movement_type',
        'quantity',
        'stock_price_version_id',
        'unit_cost',
        'reason',
        'movement_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'movement_date' => 'datetime',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function stockPriceVersion(): BelongsTo
    {
        return $this->belongsTo(StockPriceVersion::class);
    }
}
