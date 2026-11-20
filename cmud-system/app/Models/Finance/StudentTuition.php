<?php

namespace App\Models\Finance;

use App\Models\Academy\CoursePriceVersion;
use App\Models\Academy\Enrollment;
use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentTuition extends Model
{
    protected $fillable = ['enrollment_id', 'course_price_version_id', 'tuition_number', 'original_amount', 'waiver_amount', 'waiver_reason', 'waiver_approved_by', 'waiver_approved_at', 'issued_on', 'due_on', 'status', 'cancelled_at', 'cancellation_reason', 'notes'];

    protected function casts(): array
    {
        return ['original_amount' => 'decimal:2', 'waiver_amount' => 'decimal:2', 'waiver_approved_at' => 'datetime', 'issued_on' => 'date', 'due_on' => 'date', 'cancelled_at' => 'datetime'];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<CoursePriceVersion, $this> */
    public function coursePriceVersion(): BelongsTo
    {
        return $this->belongsTo(CoursePriceVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function waiverApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiver_approved_by');
    }

    /** @return HasMany<StudentTuitionPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(StudentTuitionPayment::class);
    }
}
