<?php

namespace App\Models\InstituteBranch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'stock_id',
        'movement_type',
        'quantity',
        'reason',
        'movement_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'movement_date' => 'datetime',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
