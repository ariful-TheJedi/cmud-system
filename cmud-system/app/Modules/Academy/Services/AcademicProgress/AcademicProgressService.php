<?php

namespace App\Modules\Academy\Services\AcademicProgress;

use App\Models\Academy\Enrollment;
use App\Models\Academy\ExamRecord;
use App\Models\Academy\StudentAcademicException;
use App\Models\Academy\StudentStageHistory;
use App\Models\Academy\StudentStageRecord;
use App\Modules\Academy\Services\Enrollments\StudentEnrollmentService;
use App\Modules\Finance\Services\StudentFinance\StudentFinancialOverviewService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicProgressService
{
    private const STAGE_STATUSES = [
        'pending',
        'ongoing',
        'on_hold',
        'completed',
        'failed',
        'incomplete',
    ];

    private const EXCEPTION_TYPES = [
        'progression',
        'exam_fee',
        'deadline_extension',
        'interrupted_study',
        'other',
    ];

    public function __construct(private StudentFinancialOverviewService $finances) {}

    /**
     * @return array{
     *     enrollment_id: int,
     *     enrollment_status: string,
     *     student_status: string,
     *     stages: array<int, array<string, mixed>>,
     *     exceptions: array<int, array<string, mixed>>,
     *     completion: array{eligible: bool, outstanding_requirements: array<int, string>},
     *     certificate_eligibility: array{eligible: bool, status: string, outstanding_requirements: array<int, string>}
     * }
     */
    public function overview(Enrollment $enrollment): array
    {
        $enrollment->load([
            'student:id,student_code,full_name,status',
            'course.stages' => fn ($query) => $query->where('is_active', true)->orderBy('sequence_number'),
            'stageRecords.courseStage',
            'stageRecords.examRecords' => fn ($query) => $query->orderBy('exam_name')->orderBy('attempt_number'),
            'stageRecords.history' => fn ($query) => $query->orderByDesc('created_at'),
            'academicExceptions' => fn ($query) => $query->orderByDesc('requested_at'),
        ]);

        return [
            'enrollment_id' => $enrollment->id,
            'enrollment_status' => $enrollment->getRawOriginal('status'),
            'student_status' => $enrollment->student->getRawOriginal('status'),
            'stages' => $enrollment->stageRecords
                ->sortBy(fn (StudentStageRecord $record): int => (int) $record->courseStage->sequence_number)
                ->values()
                ->map(fn (StudentStageRecord $record): array => [
                    'id' => $record->id,
                    'stage' => [
                        'id' => $record->courseStage->id,
                        'code' => $record->courseStage->code,
                        'name' => $record->courseStage->name,
                        'sequence_number' => $record->courseStage->sequence_number,
                        'is_mandatory' => $record->courseStage->is_mandatory,
                        'requires_exam' => $record->courseStage->requires_exam,
                    ],
                    'status' => $record->getRawOriginal('status'),
                    'started_on' => $record->getRawOriginal('started_on'),
                    'expected_completion_on' => $record->getRawOriginal('expected_completion_on'),
                    'completed_on' => $record->getRawOriginal('completed_on'),
                    'exams' => $record->examRecords
                        ->map(fn (ExamRecord $exam): array => [
                            'id' => $exam->id,
                            'name' => $exam->exam_name,
                            'attempt_number' => $exam->attempt_number,
                            'attempt_status' => $exam->getRawOriginal('attempt_status'),
                            'result_status' => $exam->getRawOriginal('result_status'),
                            'exam_date' => $exam->getRawOriginal('exam_date'),
                            'roll_number' => $exam->roll_number,
                            'total_marks' => $exam->total_marks,
                            'obtained_marks' => $exam->obtained_marks,
                            'grade' => $exam->grade,
                            'result_published_on' => $exam->getRawOriginal('result_published_on') === null
                                ? null
                                : Carbon::parse((string) $exam->getRawOriginal('result_published_on'))->toDateString(),
                            'result_file_path' => $exam->result_file_path,
                            'remarks' => $exam->remarks,
                        ])
                        ->all(),
                    'history' => $record->history
                        ->map(fn (StudentStageHistory $history): array => [
                            'event_type' => $history->event_type,
                            'old_values' => $history->old_values,
                            'new_values' => $history->new_values,
                            'reason' => $history->reason,
                            'created_at' => $history->getRawOriginal('created_at') === null
                                ? null
                                : Carbon::parse((string) $history->getRawOriginal('created_at'))->toIso8601String(),
                        ])
                        ->all(),
                ])
                ->all(),
            'exceptions' => $enrollment->academicExceptions
                ->map(fn (StudentAcademicException $exception): array => [
                    'id' => $exception->id,
                    'stage_record_id' => $exception->student_stage_record_id,
                    'type' => $exception->exception_type,
                    'status' => $exception->status,
                    'reason' => $exception->reason,
                    'decision_note' => $exception->decision_note,
                    'requested_at' => $exception->getRawOriginal('requested_at') === null
                        ? null
                        : Carbon::parse((string) $exception->getRawOriginal('requested_at'))->toIso8601String(),
                    'decided_at' => $exception->getRawOriginal('decided_at') === null
                        ? null
                        : Carbon::parse((string) $exception->getRawOriginal('decided_at'))->toIso8601String(),
                ])
                ->all(),
            'completion' => $this->completionStatus($enrollment),
            'certificate_eligibility' => $this->certificateEligibility($enrollment),
        ];
    }

    public function updateStageStatus(
        Enrollment $enrollment,
        StudentStageRecord $record,
        string $status,
        ?string $reason = null,
    ): StudentStageRecord {
        if (! in_array($status, self::STAGE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'The selected student stage status is invalid.',
            ]);
        }

        return DB::transaction(function () use ($enrollment, $record, $status, $reason): StudentStageRecord {
            $lockedRecord = StudentStageRecord::query()
                ->whereKey($record->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedRecord->enrollment_id !== (int) $enrollment->id) {
                throw ValidationException::withMessages([
                    'stage_record' => 'The stage record does not belong to this enrollment.',
                ]);
            }

            $oldStatus = $lockedRecord->getRawOriginal('status');
            if ($oldStatus === 'completed' && $status !== 'completed') {
                throw ValidationException::withMessages([
                    'status' => 'A completed stage cannot be reopened without an approved academic process.',
                ]);
            }

            if ($status === 'ongoing') {
                $this->assertProgressionAllowed($enrollment, $lockedRecord);
            }

            $latestExams = $status === 'completed'
                ? $lockedRecord->examRecords()
                    ->get(['exam_name', 'attempt_number', 'result_status'])
                    ->groupBy('exam_name')
                    ->map(fn ($attempts) => $attempts->sortByDesc('attempt_number')->first())
                : collect();

            if ($latestExams->contains(
                fn (?ExamRecord $exam): bool => $exam !== null && $exam->getRawOriginal('result_status') !== 'passed',
            )) {
                throw ValidationException::withMessages([
                    'status' => 'A stage with an unpassed recorded examination cannot be completed.',
                ]);
            }

            if ($status === 'completed') {
                $lockedRecord->loadMissing('courseStage');
                if ($lockedRecord->courseStage->requires_exam
                    && ! $latestExams->contains(
                        fn (?ExamRecord $exam): bool => $exam !== null && $exam->getRawOriginal('result_status') === 'passed',
                    )) {
                    throw ValidationException::withMessages([
                        'status' => 'At least one passing examination result is required to complete this stage.',
                    ]);
                }
            }

            $updates = ['status' => $status];
            if ($status === 'ongoing' && $lockedRecord->getRawOriginal('started_on') === null) {
                $updates['started_on'] = now()->toDateString();
            }
            $updates['completed_on'] = $status === 'completed' ? now()->toDateString() : null;
            $lockedRecord->update($updates);

            StudentStageHistory::query()->create([
                'enrollment_id' => $enrollment->id,
                'student_stage_record_id' => $lockedRecord->id,
                'actor_user_id' => auth()->id(),
                'event_type' => 'status_changed',
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $status],
                'reason' => $reason,
            ]);

            return $lockedRecord->refresh();
        });
    }

    public function advanceToNextStage(Enrollment $enrollment, StudentStageRecord $current): ?StudentStageRecord
    {
        return DB::transaction(function () use ($enrollment, $current): ?StudentStageRecord {
            $lockedCurrent = StudentStageRecord::query()
                ->with('courseStage')
                ->whereKey($current->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedCurrent->enrollment_id !== (int) $enrollment->id) {
                throw ValidationException::withMessages([
                    'stage_record' => 'The stage record does not belong to this enrollment.',
                ]);
            }

            $progressionException = $this->hasApprovedException($enrollment, $lockedCurrent, 'progression');
            if ($lockedCurrent->getRawOriginal('status') !== 'completed' && ! $progressionException) {
                throw ValidationException::withMessages([
                    'stage_record' => 'The current stage must be completed or have an approved progression exception.',
                ]);
            }

            $next = $enrollment->stageRecords()
                ->with('courseStage')
                ->whereHas('courseStage', fn ($query) => $query->where('sequence_number', '>', $lockedCurrent->courseStage->sequence_number))
                ->whereIn('status', ['pending', 'on_hold'])
                ->get()
                ->sortBy(fn (StudentStageRecord $stage): int => (int) $stage->courseStage->sequence_number)
                ->first();

            if (! $next) {
                return null;
            }

            $oldStatus = $next->getRawOriginal('status');
            $next->update([
                'status' => 'ongoing',
                'started_on' => $next->getRawOriginal('started_on') ?? now()->toDateString(),
            ]);
            StudentStageHistory::query()->create([
                'enrollment_id' => $enrollment->id,
                'student_stage_record_id' => $next->id,
                'actor_user_id' => auth()->id(),
                'event_type' => 'progressed',
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'ongoing'],
                'reason' => $progressionException ? 'Approved progression exception.' : null,
            ]);

            return $next->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function requestException(Enrollment $enrollment, array $data): StudentAcademicException
    {
        if (! in_array($data['exception_type'], self::EXCEPTION_TYPES, true)) {
            throw ValidationException::withMessages([
                'exception_type' => 'The selected academic exception type is invalid.',
            ]);
        }

        $stageRecordId = isset($data['student_stage_record_id']) ? (int) $data['student_stage_record_id'] : null;
        if ($stageRecordId !== null && ! $enrollment->stageRecords()->whereKey($stageRecordId)->exists()) {
            throw ValidationException::withMessages([
                'student_stage_record_id' => 'The stage record does not belong to this enrollment.',
            ]);
        }

        return DB::transaction(function () use ($enrollment, $data, $stageRecordId): StudentAcademicException {
            $exception = $enrollment->academicExceptions()->create([
                'student_stage_record_id' => $stageRecordId,
                'requested_by' => auth()->id(),
                'exception_type' => $data['exception_type'],
                'status' => 'pending',
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
                'requested_at' => now(),
            ]);

            if ($stageRecordId !== null) {
                StudentStageHistory::query()->create([
                    'enrollment_id' => $enrollment->id,
                    'student_stage_record_id' => $stageRecordId,
                    'actor_user_id' => auth()->id(),
                    'event_type' => 'exception_requested',
                    'new_values' => ['exception_id' => $exception->id, 'type' => $exception->exception_type],
                    'reason' => $exception->reason,
                ]);
            }

            return $exception;
        });
    }

    public function decideException(
        StudentAcademicException $exception,
        string $decision,
        ?string $decisionNote = null,
    ): StudentAcademicException {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => 'An academic exception can only be approved or rejected.',
            ]);
        }

        if ($exception->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending academic exceptions can be decided.',
            ]);
        }

        return DB::transaction(function () use ($exception, $decision, $decisionNote): StudentAcademicException {
            $exception->update([
                'status' => $decision,
                'decided_by' => auth()->id(),
                'decided_at' => now(),
                'decision_note' => $decisionNote,
            ]);

            if ($exception->student_stage_record_id !== null) {
                StudentStageHistory::query()->create([
                    'enrollment_id' => $exception->enrollment_id,
                    'student_stage_record_id' => $exception->student_stage_record_id,
                    'actor_user_id' => auth()->id(),
                    'event_type' => 'exception_decided',
                    'old_values' => ['status' => 'pending'],
                    'new_values' => ['status' => $decision, 'exception_id' => $exception->id],
                    'reason' => $decisionNote,
                ]);
            }

            return $exception->refresh();
        });
    }

    /** @return array{eligible: bool, reasons: array<int, string>, fee: array{payable: string, paid: string, due: string}, exception_used: bool} */
    public function examEligibility(Enrollment $enrollment, StudentStageRecord $stageRecord): array
    {
        if ((int) $stageRecord->enrollment_id !== (int) $enrollment->id) {
            throw ValidationException::withMessages([
                'stage_record' => 'The stage record does not belong to this enrollment.',
            ]);
        }

        $reasons = [];
        $studentStatus = $enrollment->student()->value('status');
        if (in_array($studentStatus, ['deactivated', 'suspended'], true)) {
            $reasons[] = 'The student institutional status restricts examination participation.';
        }

        if (! in_array($enrollment->getRawOriginal('status'), ['enrolled', 'ongoing'], true)) {
            $reasons[] = 'The enrollment is not active for examination participation.';
        }

        if ($stageRecord->getRawOriginal('status') !== 'ongoing') {
            $reasons[] = 'The semester must be ongoing before examination participation.';
        }

        if (! $this->previousStagesComplete($enrollment, $stageRecord)
            && ! $this->hasApprovedException($enrollment, $stageRecord, 'progression')) {
            $reasons[] = 'Previous mandatory stages must be completed first.';
        }

        $fee = $this->stageFeeSummary($stageRecord);
        $exceptionUsed = $fee['due_cents'] > 0
            && $this->hasApprovedException($enrollment, $stageRecord, 'exam_fee');

        if ($fee['due_cents'] > 0 && ! $exceptionUsed) {
            $reasons[] = 'The applicable semester fee must be fully cleared.';
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'fee' => [
                'payable' => number_format($fee['payable_cents'] / 100, 2, '.', ''),
                'paid' => number_format($fee['paid_cents'] / 100, 2, '.', ''),
                'due' => number_format($fee['due_cents'] / 100, 2, '.', ''),
            ],
            'exception_used' => $exceptionUsed,
        ];
    }

    /** @param array{exam_name: string, exam_date: string, total_marks?: float|int|null} $data */
    public function recordExamAttempt(
        Enrollment $enrollment,
        StudentStageRecord $stageRecord,
        array $data,
    ): ExamRecord {
        $eligibility = $this->examEligibility($enrollment, $stageRecord);
        if (! $eligibility['eligible']) {
            throw ValidationException::withMessages([
                'exam' => implode(' ', $eligibility['reasons']),
            ]);
        }

        return DB::transaction(function () use ($stageRecord, $data): ExamRecord {
            StudentStageRecord::query()->whereKey($stageRecord->id)->lockForUpdate()->firstOrFail();
            $attemptNumber = (int) $stageRecord->examRecords()
                ->where('exam_name', $data['exam_name'])
                ->max('attempt_number') + 1;

            return $stageRecord->examRecords()->create([
                'exam_name' => $data['exam_name'],
                'attempt_number' => $attemptNumber,
                'attempt_status' => 'appeared',
                'exam_date' => $data['exam_date'],
                'total_marks' => $data['total_marks'] ?? null,
                'result_status' => 'pending',
            ]);
        });
    }

    /** @param array{result_status: string, obtained_marks?: float|int|null, grade?: string|null, remarks?: string|null} $data */
    public function updateExamResult(ExamRecord $exam, array $data): ExamRecord
    {
        if (! in_array($data['result_status'], ['passed', 'failed', 'incomplete'], true)) {
            throw ValidationException::withMessages([
                'result_status' => 'The examination result must be passed, failed, or incomplete.',
            ]);
        }

        return DB::transaction(function () use ($exam, $data): ExamRecord {
            $lockedExam = ExamRecord::query()
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedExam->getRawOriginal('attempt_status') !== 'appeared') {
                throw ValidationException::withMessages([
                    'result_status' => 'Only an examination attempt marked as appeared can receive a result.',
                ]);
            }

            $oldValues = [
                'result_status' => $lockedExam->getRawOriginal('result_status'),
                'obtained_marks' => $lockedExam->getRawOriginal('obtained_marks'),
                'grade' => $lockedExam->grade,
            ];
            $updates = [
                'result_status' => $data['result_status'],
                'result_published_on' => now()->toDateString(),
            ];
            foreach (['obtained_marks', 'grade', 'remarks'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }

            $lockedExam->update($updates);
            StudentStageHistory::query()->create([
                'enrollment_id' => $lockedExam->studentStageRecord->enrollment_id,
                'student_stage_record_id' => $lockedExam->student_stage_record_id,
                'actor_user_id' => auth()->id(),
                'event_type' => 'exam_result_published',
                'old_values' => $oldValues,
                'new_values' => [
                    'result_status' => $data['result_status'],
                    'obtained_marks' => $data['obtained_marks'] ?? $lockedExam->getRawOriginal('obtained_marks'),
                    'grade' => $data['grade'] ?? $lockedExam->grade,
                ],
            ]);

            return $lockedExam->refresh();
        });
    }

    public function completeEnrollment(Enrollment $enrollment): Enrollment
    {
        $completion = $this->completionStatus($enrollment);
        if (! $completion['eligible']) {
            throw ValidationException::withMessages([
                'enrollment' => 'Course completion is blocked until every mandatory stage and recorded examination is passed.',
            ]);
        }

        return app(StudentEnrollmentService::class)->updateStatus($enrollment, 'completed');
    }

    /** @return array{eligible: bool, status: string, outstanding_requirements: array<int, string>} */
    public function certificateEligibility(Enrollment $enrollment): array
    {
        $completion = $this->completionStatus($enrollment);
        $requirements = $completion['outstanding_requirements'];
        $enrollmentCompleted = $enrollment->getRawOriginal('status') === 'completed';

        if (! $enrollmentCompleted) {
            $requirements[] = 'The enrollment must be completed before a certificate can be issued.';
        }

        $eligible = $completion['eligible'] && $enrollmentCompleted;

        return [
            'eligible' => $eligible,
            'status' => $eligible ? 'eligible' : 'not_eligible',
            'outstanding_requirements' => $requirements,
        ];
    }

    /** @return array{eligible: bool, outstanding_requirements: array<int, string>} */
    private function completionStatus(Enrollment $enrollment): array
    {
        $requiredStages = $enrollment->course->stages()
            ->where('is_active', true)
            ->where('is_mandatory', true)
            ->orderBy('sequence_number')
            ->get();
        $stageRecords = $enrollment->stageRecords()
            ->whereIn('course_stage_id', $requiredStages->modelKeys())
            ->with('examRecords:id,student_stage_record_id,exam_name,attempt_number,result_status')
            ->get()
            ->keyBy('course_stage_id');
        $outstanding = [];

        foreach ($requiredStages as $stage) {
            $record = $stageRecords->get($stage->id);
            if (! $record) {
                $outstanding[] = 'Stage '.$stage->name.' is not recorded for this enrollment.';
            } elseif ($record->getRawOriginal('status') !== 'completed') {
                $outstanding[] = 'Stage '.$stage->name.' is not completed.';
            }

            $latestExams = $record === null
                ? collect()
                : $record->examRecords
                    ->groupBy('exam_name')
                    ->map(fn ($attempts) => $attempts->sortByDesc('attempt_number')->first());

            if ($latestExams->contains(
                fn (?ExamRecord $exam): bool => $exam !== null && $exam->getRawOriginal('result_status') !== 'passed',
            )) {
                $outstanding[] = 'Stage '.$stage->name.' has an examination without a passing result.';
            }

            if ($stage->requires_exam
                && ! $latestExams->contains(
                    fn (?ExamRecord $exam): bool => $exam !== null && $exam->getRawOriginal('result_status') === 'passed',
                )) {
                $outstanding[] = 'Stage '.$stage->name.' requires a passing examination result.';
            }
        }

        return [
            'eligible' => $outstanding === [],
            'outstanding_requirements' => $outstanding,
        ];
    }

    /** @return array{payable_cents: int, paid_cents: int, due_cents: int} */
    private function stageFeeSummary(StudentStageRecord $stageRecord): array
    {
        return $this->finances->stageFeeSummary($stageRecord);
    }

    private function assertProgressionAllowed(Enrollment $enrollment, StudentStageRecord $record): void
    {
        if (! $this->previousStagesComplete($enrollment, $record)
            && ! $this->hasApprovedException($enrollment, $record, 'progression')) {
            throw ValidationException::withMessages([
                'status' => 'Complete previous mandatory stages or record an approved progression exception first.',
            ]);
        }
    }

    private function previousStagesComplete(Enrollment $enrollment, StudentStageRecord $record): bool
    {
        $record->loadMissing('courseStage');
        $previousRequiredIds = $enrollment->course->stages()
            ->where('is_active', true)
            ->where('is_mandatory', true)
            ->where('sequence_number', '<', $record->courseStage->sequence_number)
            ->pluck('id');

        if ($previousRequiredIds->isEmpty()) {
            return true;
        }

        return $enrollment->stageRecords()
            ->whereIn('course_stage_id', $previousRequiredIds)
            ->where('status', '!=', 'completed')
            ->doesntExist()
            && $enrollment->stageRecords()->whereIn('course_stage_id', $previousRequiredIds)->count()
                === $previousRequiredIds->count();
    }

    private function hasApprovedException(
        Enrollment $enrollment,
        StudentStageRecord $record,
        string $type,
    ): bool {
        return $enrollment->academicExceptions()
            ->where('student_stage_record_id', $record->id)
            ->where('exception_type', $type)
            ->where('status', 'approved')
            ->exists();
    }
}
