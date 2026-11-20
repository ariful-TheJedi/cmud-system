<?php

namespace App\Models\Academy;

use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAcademicException extends Model
{
    protected $fillable = [
        'enrollment_id',
        'student_stage_record_id',
        'requested_by',
        'decided_by',
        'exception_type',
        'status',
        'reason',
        'details',
        'requested_at',
        'decided_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<StudentStageRecord, $this> */
    public function stageRecord(): BelongsTo
    {
        return $this->belongsTo(StudentStageRecord::class, 'student_stage_record_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
