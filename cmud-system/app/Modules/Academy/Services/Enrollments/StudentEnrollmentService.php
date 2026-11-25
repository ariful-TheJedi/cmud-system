<?php

namespace App\Modules\Academy\Services\Enrollments;

use App\Enums\Academy\CertificateStatus;
use App\Enums\Academy\EnrollmentStatus;
use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\EnrollmentHistory;
use App\Models\Academy\Student;
use App\Models\Academy\StudentStageHistory;
use App\Models\Academy\StudentStageRecord;
use App\Models\Institute\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentEnrollmentService
{
    private const ACTIVE_ENROLLMENT_STATUSES = [
        'enrolled',
        'ongoing',
        'on_hold',
    ];

    private const AVAILABLE_BATCH_STATUSES = [
        'active',
        'open',
        'upcoming',
        'ongoing',
    ];

    /** @param array{course_id: int, batch_id: int, enrollment_date: string} $data */
    public function enroll(Student $student, array $data): Enrollment
    {
        return DB::transaction(function () use ($student, $data): Enrollment {
            $lockedStudent = Student::query()
                ->lockForUpdate()
                ->whereKey($student->getKey())
                ->firstOrFail();
            $course = Course::query()
                ->lockForUpdate()
                ->whereKey($data['course_id'])
                ->firstOrFail();
            $batch = Batch::query()
                ->lockForUpdate()
                ->whereKey($data['batch_id'])
                ->firstOrFail();
            $branch = Branch::query()
                ->lockForUpdate()
                ->whereKey($batch->branch_id)
                ->first();

            if (! in_array($lockedStudent->getRawOriginal('status'), ['registered', 'active'], true)) {
                throw ValidationException::withMessages([
                    'student' => 'The student is not eligible for enrollment.',
                ]);
            }

            if ($course->getRawOriginal('status') !== 'active'
                || ! $course->is_available_for_enrollment
                || ! $course->is_active) {
                throw ValidationException::withMessages([
                    'course_id' => 'The selected course is not available for enrollment.',
                ]);
            }

            if ((int) $course->institute_id !== (int) $lockedStudent->institute_id) {
                throw ValidationException::withMessages([
                    'course_id' => "The course must belong to the student's institute.",
                ]);
            }

            if ((int) $batch->course_id !== (int) $course->id) {
                throw ValidationException::withMessages([
                    'batch_id' => 'The batch must belong to the selected course.',
                ]);
            }

            if (! $branch || ! $branch->is_active
                || (int) $branch->institute_id !== (int) $course->institute_id) {
                throw ValidationException::withMessages([
                    'batch_id' => 'The batch branch must belong to the selected course institute.',
                ]);
            }

            if (! in_array(strtolower(trim((string) $batch->status)), self::AVAILABLE_BATCH_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'batch_id' => 'The selected batch is not open for enrollment.',
                ]);
            }

            $enrollmentDate = Carbon::parse($data['enrollment_date']);
            $opensOn = $batch->getRawOriginal('admission_opens_on');
            $closesOn = $batch->getRawOriginal('admission_closes_on');

            if ($opensOn !== null && $enrollmentDate->lt(Carbon::parse((string) $opensOn)->startOfDay())) {
                throw ValidationException::withMessages([
                    'enrollment_date' => 'Enrollment cannot occur before the batch admission period opens.',
                ]);
            }

            if ($closesOn !== null && $enrollmentDate->gt(Carbon::parse((string) $closesOn)->endOfDay())) {
                throw ValidationException::withMessages([
                    'enrollment_date' => 'Enrollment cannot occur after the batch admission period closes.',
                ]);
            }

            $activeCourseEnrollmentExists = $lockedStudent->enrollments()
                ->where('course_id', $course->id)
                ->whereIn('status', self::ACTIVE_ENROLLMENT_STATUSES)
                ->exists();

            if ($activeCourseEnrollmentExists) {
                throw ValidationException::withMessages([
                    'course_id' => 'The student already has an active enrollment in this course.',
                ]);
            }

            if ($batch->capacity !== null) {
                $activeEnrollmentCount = Enrollment::query()
                    ->where('batch_id', $batch->id)
                    ->whereIn('status', self::ACTIVE_ENROLLMENT_STATUSES)
                    ->count();

                if ($activeEnrollmentCount >= $batch->capacity) {
                    throw ValidationException::withMessages([
                        'batch_id' => 'The selected batch has reached its enrollment capacity.',
                    ]);
                }
            }

            $startsOn = Carbon::parse($batch->getRawOriginal('starts_on'));
            $expectedCompletion = $startsOn->copy()->addMonths($course->duration_months);
            $completionDeadline = $startsOn->copy()->addMonths($course->completion_period_months);

            $enrollment = Enrollment::query()->create([
                'enrollment_code' => 'ENR-'.Str::upper((string) Str::uuid()),
                'student_id' => $lockedStudent->id,
                'course_id' => $course->id,
                'branch_id' => $batch->branch_id,
                'batch_id' => $batch->id,
                'enrollment_date' => $enrollmentDate,
                'starts_on' => $startsOn,
                'expected_completion_on' => $expectedCompletion,
                'completion_deadline_on' => $completionDeadline,
                'status' => 'enrolled',
                'is_active' => true,
            ]);

            $this->createInitialStageRecords($enrollment, $course, $batch, $startsOn);
            $this->createInitialCertificate($enrollment, $lockedStudent, $course);
            $this->recordEnrollmentHistory($enrollment, 'created', null, [
                'course_id' => $course->id,
                'batch_id' => $batch->id,
                'status' => 'enrolled',
            ]);

            return $enrollment;
        });
    }

    public function transferBatch(Enrollment $enrollment, Batch $newBatch, ?string $reason = null): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $newBatch, $reason): Enrollment {
            $lockedEnrollment = Enrollment::query()
                ->whereKey($enrollment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedBatch = Batch::query()
                ->whereKey($newBatch->id)
                ->lockForUpdate()
                ->firstOrFail();
            $course = Course::query()->findOrFail($lockedEnrollment->course_id);
            $branch = Branch::query()->find($lockedBatch->branch_id);

            if (! in_array($lockedEnrollment->getRawOriginal('status'), self::ACTIVE_ENROLLMENT_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'enrollment' => 'Only active enrollments can be transferred.',
                ]);
            }

            if ((int) $lockedBatch->course_id !== (int) $lockedEnrollment->course_id
                || ! $branch
                || ! $branch->is_active
                || (int) $branch->institute_id !== (int) $course->institute_id) {
                throw ValidationException::withMessages([
                    'batch_id' => 'The destination batch must belong to the same course and institute.',
                ]);
            }

            if (! in_array(strtolower(trim((string) $lockedBatch->status)), self::AVAILABLE_BATCH_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'batch_id' => 'The destination batch is not eligible for transfers.',
                ]);
            }

            if ($lockedBatch->capacity !== null) {
                $capacityUsed = Enrollment::query()
                    ->where('batch_id', $lockedBatch->id)
                    ->whereIn('status', self::ACTIVE_ENROLLMENT_STATUSES)
                    ->whereKeyNot($lockedEnrollment->id)
                    ->count();

                if ($capacityUsed >= $lockedBatch->capacity) {
                    throw ValidationException::withMessages([
                        'batch_id' => 'The destination batch has reached its enrollment capacity.',
                    ]);
                }
            }

            $oldValues = [
                'batch_id' => $lockedEnrollment->batch_id,
                'branch_id' => $lockedEnrollment->branch_id,
            ];
            $newValues = [
                'batch_id' => $lockedBatch->id,
                'branch_id' => $lockedBatch->branch_id,
            ];

            if ($oldValues === $newValues) {
                return $lockedEnrollment;
            }

            $lockedEnrollment->update($newValues);
            $lockedEnrollment->stageRecords()
                ->whereIn('status', ['pending', 'ongoing', 'on_hold', 'incomplete'])
                ->get()
                ->each(function (StudentStageRecord $record) use ($lockedBatch): void {
                    $oldBatchId = $record->batch_id;
                    $record->update(['batch_id' => $lockedBatch->id]);
                    StudentStageHistory::query()->create([
                        'enrollment_id' => $record->enrollment_id,
                        'student_stage_record_id' => $record->id,
                        'event_type' => 'batch_transferred',
                        'old_values' => ['batch_id' => $oldBatchId],
                        'new_values' => ['batch_id' => $lockedBatch->id],
                    ]);
                });
            $this->recordEnrollmentHistory($lockedEnrollment, 'batch_transferred', $oldValues, $newValues, $reason);

            return $lockedEnrollment->refresh();
        });
    }

    public function updateAssignment(
        Enrollment $enrollment,
        Course $newCourse,
        Batch $newBatch,
        ?string $reason = null,
    ): Enrollment {
        return DB::transaction(function () use ($enrollment, $newCourse, $newBatch, $reason): Enrollment {
            $lockedEnrollment = Enrollment::query()
                ->whereKey($enrollment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $student = Student::query()
                ->whereKey($lockedEnrollment->student_id)
                ->lockForUpdate()
                ->firstOrFail();
            $course = Course::query()->whereKey($newCourse->id)->lockForUpdate()->firstOrFail();
            $batch = Batch::query()->whereKey($newBatch->id)->lockForUpdate()->firstOrFail();
            $branch = Branch::query()->find($batch->branch_id);

            if (! in_array($lockedEnrollment->getRawOriginal('status'), self::ACTIVE_ENROLLMENT_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'enrollment' => 'Only active enrollments can have their course or batch assignment changed.',
                ]);
            }

            if ($course->getRawOriginal('status') !== 'active'
                || ! $course->is_active
                || ! $course->is_available_for_enrollment
                || (int) $course->institute_id !== (int) $student->institute_id) {
                throw ValidationException::withMessages([
                    'course_id' => 'The selected course is not available to this student.',
                ]);
            }

            if ((int) $batch->course_id !== (int) $course->id
                || ! $branch
                || ! $branch->is_active
                || (int) $branch->institute_id !== (int) $course->institute_id
                || ! in_array(strtolower(trim((string) $batch->status)), self::AVAILABLE_BATCH_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'batch_id' => 'The selected batch is not eligible for this course assignment.',
                ]);
            }

            $duplicateExists = $student->enrollments()
                ->where('course_id', $course->id)
                ->whereIn('status', self::ACTIVE_ENROLLMENT_STATUSES)
                ->where('id', '!=', $lockedEnrollment->id)
                ->exists();
            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'course_id' => 'The student already has an active enrollment in this course.',
                ]);
            }

            if ($batch->capacity !== null) {
                $capacityUsed = Enrollment::query()
                    ->where('batch_id', $batch->id)
                    ->whereIn('status', self::ACTIVE_ENROLLMENT_STATUSES)
                    ->where('id', '!=', $lockedEnrollment->id)
                    ->count();
                if ($capacityUsed >= $batch->capacity) {
                    throw ValidationException::withMessages([
                        'batch_id' => 'The selected batch has reached its enrollment capacity.',
                    ]);
                }
            }

            $courseChanged = (int) $lockedEnrollment->course_id !== (int) $course->id;
            if ($courseChanged && (
                $lockedEnrollment->stageRecords()->exists()
                || $lockedEnrollment->certificates()->exists()
                || $lockedEnrollment->hasTuition()
                || $lockedEnrollment->expenses()->exists()
            )) {
                throw ValidationException::withMessages([
                    'course_id' => 'Course assignment cannot change after academic, certificate, or financial records exist.',
                ]);
            }

            $oldValues = [
                'course_id' => $lockedEnrollment->course_id,
                'batch_id' => $lockedEnrollment->batch_id,
                'branch_id' => $lockedEnrollment->branch_id,
                'starts_on' => $lockedEnrollment->getRawOriginal('starts_on'),
                'expected_completion_on' => $lockedEnrollment->getRawOriginal('expected_completion_on'),
                'completion_deadline_on' => $lockedEnrollment->getRawOriginal('completion_deadline_on'),
            ];
            $startsOn = Carbon::parse($batch->getRawOriginal('starts_on'));
            $newValues = [
                'course_id' => $course->id,
                'batch_id' => $batch->id,
                'branch_id' => $batch->branch_id,
                'starts_on' => $startsOn->toDateString(),
                'expected_completion_on' => $startsOn->copy()->addMonths($course->duration_months)->toDateString(),
                'completion_deadline_on' => $startsOn->copy()->addMonths($course->completion_period_months)->toDateString(),
            ];

            if ($oldValues === array_intersect_key($newValues, $oldValues)) {
                return $lockedEnrollment;
            }

            $lockedEnrollment->update($newValues);
            if ($courseChanged) {
                $this->createInitialStageRecords($lockedEnrollment, $course, $batch, $startsOn);
            } else {
                $lockedEnrollment->stageRecords()
                    ->whereIn('status', ['pending', 'ongoing', 'on_hold', 'incomplete'])
                    ->get()
                    ->each(function (StudentStageRecord $record) use ($batch, $reason): void {
                        $oldBatchId = $record->batch_id;
                        $record->update(['batch_id' => $batch->id]);
                        StudentStageHistory::query()->create([
                            'enrollment_id' => $record->enrollment_id,
                            'student_stage_record_id' => $record->id,
                            'actor_user_id' => auth()->id(),
                            'event_type' => 'batch_transferred',
                            'old_values' => ['batch_id' => $oldBatchId],
                            'new_values' => ['batch_id' => $batch->id],
                            'reason' => $reason,
                        ]);
                    });
            }

            $this->recordEnrollmentHistory($lockedEnrollment, 'assignment_changed', $oldValues, $newValues, $reason);

            return $lockedEnrollment->refresh();
        });
    }

    public function updateStatus(Enrollment $enrollment, string $status, ?string $reason = null): Enrollment
    {
        if (! in_array($status, array_column(EnrollmentStatus::cases(), 'value'), true)) {
            throw ValidationException::withMessages([
                'status' => 'The selected enrollment status is invalid.',
            ]);
        }

        return DB::transaction(function () use ($enrollment, $status, $reason): Enrollment {
            $lockedEnrollment = Enrollment::query()
                ->whereKey($enrollment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $oldStatus = $lockedEnrollment->getRawOriginal('status');

            if ($oldStatus === $status) {
                return $lockedEnrollment;
            }

            $updates = [
                'status' => $status,
                'is_active' => in_array($status, self::ACTIVE_ENROLLMENT_STATUSES, true),
            ];

            if ($status === 'completed') {
                if (! $this->hasCompletedMandatoryStages($lockedEnrollment)) {
                    throw ValidationException::withMessages([
                        'status' => 'The enrollment cannot be completed until all mandatory stages and recorded exams are passed.',
                    ]);
                }

                $updates['completed_on'] = now()->toDateString();
            } elseif ($oldStatus === 'completed') {
                $updates['completed_on'] = null;
            }

            $lockedEnrollment->update($updates);
            $this->recordEnrollmentHistory(
                $lockedEnrollment,
                'status_changed',
                ['status' => $oldStatus],
                ['status' => $status],
                $reason,
            );

            return $lockedEnrollment->refresh();
        });
    }

    /** @return Collection<int, EnrollmentHistory> */
    public function history(Enrollment $enrollment): Collection
    {
        return $enrollment->history()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    private function createInitialStageRecords(Enrollment $enrollment, Course $course, Batch $batch, Carbon $startsOn): void
    {
        $stages = $course->stages()
            ->where('is_active', true)
            ->orderBy('sequence_number')
            ->get();
        $stageStart = $startsOn->copy();

        foreach ($stages as $index => $stage) {
            $stageEnd = $stageStart->copy()->addMonths((int) $stage->duration_months);
            $record = StudentStageRecord::query()->create([
                'enrollment_id' => $enrollment->id,
                'course_stage_id' => $stage->id,
                'batch_id' => $batch->id,
                'started_on' => $index === 0 ? $stageStart->toDateString() : null,
                'expected_completion_on' => $stageEnd->toDateString(),
                'status' => $index === 0 ? 'ongoing' : 'pending',
            ]);

            StudentStageHistory::query()->create([
                'enrollment_id' => $enrollment->id,
                'student_stage_record_id' => $record->id,
                'event_type' => 'created',
                'new_values' => [
                    'course_stage_id' => $stage->id,
                    'status' => $record->getRawOriginal('status'),
                ],
            ]);

            $stageStart = $stageEnd;
        }
    }

    private function createInitialCertificate(Enrollment $enrollment, Student $student, Course $course): void
    {
        $template = $course->certificateTemplates()
            ->where('institute_id', $course->institute_id)
            ->where('is_active', true)
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->first();

        // A course without its own active template remains enrollable without a certificate record.
        if ($template === null) {
            return;
        }

        $enrollment->certificates()->create([
            'certificate_template_id' => $template->id,
            'recipient_name' => $student->full_name,
            'course_title' => $course->name,
            'status' => CertificateStatus::NotEligible->value,
        ]);
    }

    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordEnrollmentHistory(
        Enrollment $enrollment,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        ?string $reason = null,
    ): void {
        EnrollmentHistory::query()->create([
            'enrollment_id' => $enrollment->id,
            'actor_user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
        ]);
    }

    private function hasCompletedMandatoryStages(Enrollment $enrollment): bool
    {
        $mandatoryStages = Course::query()
            ->whereKey($enrollment->course_id)
            ->firstOrFail()
            ->stages()
            ->where('is_active', true)
            ->where('is_mandatory', true)
            ->get(['id', 'name', 'requires_exam']);

        if ($mandatoryStages->isEmpty()) {
            return true;
        }

        $stageRecords = $enrollment->stageRecords()
            ->whereIn('course_stage_id', $mandatoryStages->modelKeys())
            ->with('examRecords:id,student_stage_record_id,exam_name,attempt_number,result_status')
            ->get();

        if ($stageRecords->count() !== $mandatoryStages->count()) {
            return false;
        }

        foreach ($mandatoryStages as $stage) {
            $stageRecord = $stageRecords->firstWhere('course_stage_id', $stage->id);
            if (! $stageRecord) {
                return false;
            }

            if ($stageRecord->getRawOriginal('status') !== 'completed') {
                return false;
            }

            $latestExams = $stageRecord->examRecords
                ->groupBy('exam_name')
                ->map(fn ($attempts) => $attempts->sortByDesc('attempt_number')->first());
            if ($latestExams->contains(
                fn ($exam): bool => $exam->getRawOriginal('result_status') !== 'passed',
            )) {
                return false;
            }

            if ($stage->requires_exam && ! $latestExams->contains(
                fn ($exam): bool => $exam->getRawOriginal('result_status') === 'passed',
            )) {
                return false;
            }
        }

        return true;
    }
}
