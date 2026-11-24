<?php

namespace App\Models\Institute;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StuffAssignment extends Model
{
    protected $fillable = [
        'stuff_id',
        'branch_id',
        'department_id',
    ];

    public function stuff(): BelongsTo
    {
        return $this->belongsTo(Stuff::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
