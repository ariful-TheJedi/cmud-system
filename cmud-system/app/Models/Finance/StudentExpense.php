<?php

namespace App\Models\Finance;

use App\Models\InstituteBranch\Branch;
use App\Models\StudentsAcademy\Enrollment;
use App\Models\StudentsAcademy\Student;
use App\Models\StudentsAcademy\StudentStageRecord;
use App\Models\UserAccess\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentExpense extends Model
{
    protected $fillable = ['expense_number', 'student_id', 'enrollment_id', 'student_stage_record_id', 'branch_id', 'student_expense_type_id', 'student_expense_price_version_id', 'description', 'quantity', 'unit_amount', 'waiver_amount', 'waiver_reason', 'waiver_approved_by', 'waiver_approved_at', 'issued_on', 'due_on', 'status', 'cancelled_at', 'cancellation_reason', 'recorded_by', 'notes'];
    protected function casts(): array { return ['quantity' => 'decimal:2', 'unit_amount' => 'decimal:2', 'waiver_amount' => 'decimal:2', 'waiver_approved_at' => 'datetime', 'issued_on' => 'date', 'due_on' => 'date', 'cancelled_at' => 'datetime']; }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function enrollment(): BelongsTo { return $this->belongsTo(Enrollment::class); }
    public function stageRecord(): BelongsTo { return $this->belongsTo(StudentStageRecord::class, 'student_stage_record_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function expenseType(): BelongsTo { return $this->belongsTo(StudentExpenseType::class, 'student_expense_type_id'); }
    public function priceVersion(): BelongsTo { return $this->belongsTo(StudentExpensePriceVersion::class, 'student_expense_price_version_id'); }
    public function waiverApprover(): BelongsTo { return $this->belongsTo(User::class, 'waiver_approved_by'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function payments(): HasMany { return $this->hasMany(StudentExpensePayment::class); }
}
