<?php

namespace App\Models\StudentsAcademy;

use App\Models\InstituteBranch\Institute;
use App\Models\InstituteBranch\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationGroup extends Model
{
    protected $fillable = [
        'institute_id',
        'branch_id',
        'course_id',
        'batch_id',
        'name',
        'platform',
        'group_url',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(StudentGroupMembership::class);
    }
}
