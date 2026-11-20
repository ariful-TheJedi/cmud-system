<?php

namespace App\Models\Academy;

use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentStageHistory extends Model
{
    protected $fillable = [
        'enrollment_id',
        'student_stage_record_id',
        'actor_user_id',
        'event_type',
        'old_values',
        'new_values',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
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
        return $this->belongsTo(StudentStageRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
