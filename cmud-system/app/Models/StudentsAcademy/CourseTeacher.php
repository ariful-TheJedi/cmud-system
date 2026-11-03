<?php

namespace App\Models\StudentsAcademy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseTeacher extends Model
{
    protected $table = 'course_teacher';

    protected $fillable = [
        'course_id',
        'teacher_id',
        'teaching_role',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
