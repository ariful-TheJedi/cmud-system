<?php

namespace App\Models\Finance;

use App\Models\InstituteBranch\Branch;
use App\Models\StudentsAcademy\Course;
use App\Models\StudentsAcademy\CourseStage;
use App\Models\UserAccess\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentExpensePriceVersion extends Model
{
    protected $fillable = ['student_expense_type_id', 'branch_id', 'course_id', 'course_stage_id', 'version_number', 'amount', 'effective_from', 'effective_to', 'status', 'approved_by', 'approved_at', 'notes'];
    protected function casts(): array { return ['version_number' => 'integer', 'amount' => 'decimal:2', 'effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'datetime']; }
    public function expenseType(): BelongsTo { return $this->belongsTo(StudentExpenseType::class, 'student_expense_type_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function courseStage(): BelongsTo { return $this->belongsTo(CourseStage::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function expenses(): HasMany { return $this->hasMany(StudentExpense::class); }
}
