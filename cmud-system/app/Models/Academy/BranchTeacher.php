<?php

namespace App\Models\Academy;

use App\Models\Institute\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchTeacher extends Model
{
    protected $table = 'branch_teacher';

    protected $fillable = [
        'branch_id',
        'teacher_id',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
