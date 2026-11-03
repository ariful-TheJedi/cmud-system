<?php

namespace App\Models\StudentsAcademy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGroupMembership extends Model
{
    protected $fillable = [
        'student_id',
        'communication_group_id',
        'invited_at',
        'joined_at',
        'left_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function communicationGroup(): BelongsTo
    {
        return $this->belongsTo(CommunicationGroup::class);
    }
}
