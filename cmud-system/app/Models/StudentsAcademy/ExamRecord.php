<?php

namespace App\Models\StudentsAcademy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamRecord extends Model
{
    protected $fillable = [
        'student_stage_record_id',
        'exam_name',
        'attempt_number',
        'exam_date',
        'roll_number',
        'total_marks',
        'obtained_marks',
        'grade',
        'result_status',
        'result_published_on',
        'result_file_path',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'exam_date' => 'date',
            'total_marks' => 'decimal:2',
            'obtained_marks' => 'decimal:2',
            'result_published_on' => 'date',
        ];
    }

    public function studentStageRecord(): BelongsTo
    {
        return $this->belongsTo(StudentStageRecord::class);
    }
}
