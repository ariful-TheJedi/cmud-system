<?php

namespace App\Models\Academy;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CourseSubject extends Pivot
{
    protected $table = 'course_subject';

    public $incrementing = false;

    protected $fillable = [
        'course_id',
        'subject_id',
    ];
}
