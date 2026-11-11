<?php

namespace App\Models\Institute;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function priceVersions(): HasMany
    {
        return $this->hasMany(StockPriceVersion::class);
    }
}
