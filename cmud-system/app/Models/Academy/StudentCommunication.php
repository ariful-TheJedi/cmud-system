<?php

namespace App\Models\Academy;

use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCommunication extends Model
{
    protected $fillable = [
        'student_id',
        'enrollment_id',
        'responsible_user_id',
        'created_by',
        'activity_type',
        'channel',
        'direction',
        'subject',
        'remarks',
        'outcome',
        'contacted_at',
        'follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'contacted_at' => 'datetime',
            'follow_up_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
