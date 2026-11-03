<?php

namespace App\Models\InstituteBranch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends Model
{
    protected $fillable = [
        'institute_id',
        'code',
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

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
