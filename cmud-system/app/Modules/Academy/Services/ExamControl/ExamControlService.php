<?php

namespace App\Modules\Academy\Services\ExamControl;

use App\Models\Academy\Batch;
use App\Models\Academy\Enrollment;
use App\Models\Academy\ExamRecord;
use App\Models\Academy\StudentStageHistory;
use App\Models\Academy\StudentStageRecord;
use App\Models\Security\User;
use App\Modules\Academy\Services\AcademicProgress\AcademicProgressService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamControlService
{
    public function __construct(private AcademicProgressService $academics) {}

    /** @return array<int, array<string, mixed>> */
    public function batches(?User $user, ?string $search = null): array
    {
        $batches = Batch::query()
            ->with(['course:id,name,duration_months', 'branch:id,name'])
            ->when($user !== null && ! $user->is_super_admin, fn (Builder $query) => $query->where('branch_id', $user->branch_id ?? 0))
            ->when(filled($search), fn (Builder $query) => $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('name', 'ilike', '%'.trim((string) $search).'%')
                    ->orWhere('code', 'ilike', '%'.trim((string) $search).'%');
            }))
            ->whereHas('enrollments')
            ->orderByDesc('starts_on')
            ->get();

        return [
            'courses' => $batches->pluck('course')->unique('id')->values()->map(fn ($course): array => [
                'id' => $course->id,
                'name' => $course->name,
                'duration_months' => $course->duration_months,
            ])->all(),
            'batches' => $batches->map(fn (Batch $batch): array => [
                'id' => $batch->id,
                'course_id' => $batch->course_id,
                'code' => $batch->code,
                'name' => $batch->name,
                'status' => $batch->getRawOriginal('status'),
                'starts_on' => $batch->getRawOriginal('starts_on'),
                'expected_ends_on' => $batch->getRawOriginal('expected_ends_on'),
                'course' => [
                    'id' => $batch->course->id,
                    'name' => $batch->course->name,
                    'duration_months' => $batch->course->duration_months,
                ],
                'branch' => ['id' => $batch->branch->id, 'name' => $batch->branch->name],
            ])->all(),
        ];
    }

    /** @param array{batch_id: int, search?: string|null, page?: int, per_page?: int} $filters */
    public function students(array $filters, ?User $user): LengthAwarePaginator
    {
        $batch = Batch::query()
            ->with(['course:id,duration_months', 'branch:id,name'])
            ->when($user !== null && ! $user->is_super_admin, fn (Builder $query) => $query->where('branch_id', $user->branch_id ?? 0))
            ->findOrFail($filters['batch_id']);

        $enrollments = Enrollment::query()
            ->where('batch_id', $batch->id)
            ->when($user !== null && ! $user->is_super_admin, fn (Builder $query) => $query->where('branch_id', $user->branch_id ?? 0))
            ->whereIn('status', ['enrolled', 'ongoing', 'on_hold', 'completed'])
            ->whereHas('student', function (Builder $query) use ($filters): void {
                if (filled($filters['search'] ?? null)) {
                    $term = mb_strtolower(trim((string) $filters['search']));
                    $query->where(function (Builder $searchQuery) use ($term): void {
                        $searchQuery->whereRaw('LOWER(student_code) LIKE ?', ['%'.$term.'%'])
                            ->orWhereRaw('LOWER(full_name) LIKE ?', ['%'.$term.'%']);
                    });
                }
            })
            ->with([
                'student:id,student_code,full_name,phone,status',
                'course:id,name,duration_months',
                'batch:id,name,starts_on',
                'stageRecords.courseStage:id,course_id,code,name,sequence_number,requires_exam,is_mandatory',
                'stageRecords.examRecords' => fn ($query) => $query->orderByDesc('attempt_number'),
            ])
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15));

        $enrollments->getCollection()->transform(function (Enrollment $enrollment): array {
            $durationAnchor = $enrollment->getRawOriginal('starts_on')
                ?: $enrollment->batch?->getRawOriginal('starts_on');
            $eligibleOn = $durationAnchor === null
                ? null
                : Carbon::parse((string) $durationAnchor)->addMonthsNoOverflow((int) $enrollment->course->duration_months)->toDateString();
            $durationComplete = $eligibleOn !== null && ! Carbon::parse($eligibleOn)->isFuture();

            return [
                'enrollment_id' => $enrollment->id,
                'enrollment_code' => $enrollment->enrollment_code,
                'status' => $enrollment->getRawOriginal('status'),
                'student' => [
                    'id' => $enrollment->student->id,
                    'student_code' => $enrollment->student->student_code,
                    'full_name' => $enrollment->student->full_name,
                    'phone' => $enrollment->student->phone,
                ],
                'course' => ['id' => $enrollment->course->id, 'name' => $enrollment->course->name],
                'duration' => [
                    'months' => $enrollment->course->duration_months,
                    'starts_on' => $durationAnchor,
                    'eligible_on' => $eligibleOn,
                    'completed' => $durationComplete,
                ],
                'stages' => $enrollment->stageRecords->sortBy(fn (StudentStageRecord $stage) => $stage->courseStage->sequence_number)
                    ->values()
                    ->map(function (StudentStageRecord $stage) use ($enrollment, $durationAnchor, $eligibleOn, $durationComplete): array {
                        $eligibility = $this->academics->examEligibility($enrollment, $stage);
                        $latest = $stage->examRecords
                            ->sortByDesc('attempt_number')
                            ->first();
                        $eligibleNow = $eligibility['eligible'] && $durationComplete;

                        return [
                            'stage_record_id' => $stage->id,
                            'stage' => [
                                'id' => $stage->courseStage->id,
                                'code' => $stage->courseStage->code,
                                'name' => $stage->courseStage->name,
                                'requires_exam' => $stage->courseStage->requires_exam,
                            ],
                            'stage_status' => $stage->getRawOriginal('status'),
                            'exam_eligibility' => [
                                'eligible' => $eligibleNow,
                                'reasons' => array_values(array_unique(array_merge(
                                    $eligibility['reasons'],
                                    $durationComplete ? [] : ['The course duration is not complete.'],
                                ))),
                                'duration' => [
                                    'months' => $enrollment->course->duration_months,
                                    'starts_on' => $durationAnchor,
                                    'eligible_on' => $eligibleOn,
                                    'completed' => $durationComplete,
                                ],
                            ],
                            'exam_status' => $this->displayStatus($latest, $eligibleNow),
                            'can_schedule' => $eligibleNow && ($latest === null || in_array($latest->getRawOriginal('result_status'), ['failed', 'incomplete'], true)),
                            'latest_exam' => $latest?->toArray(),
                            'attempts' => $stage->examRecords->sortBy('attempt_number')->values(),
                        ];
                    })->all(),
            ];
        });

        return $enrollments;
    }

    /** @param array<string, mixed> $data */
    public function updateStatus(StudentStageRecord $stage, array $data): array
    {
        $status = (string) $data['status'];
        // Prevent staff assigned to one branch from changing another branch's exam records.
        $user = auth()->user();
        $stage = StudentStageRecord::query()
            ->whereKey($stage->id)
            ->when($user !== null && ! $user->is_super_admin, fn (Builder $query) => $query->whereHas('enrollment', fn (Builder $enrollmentQuery) => $enrollmentQuery->where('branch_id', $user->branch_id ?? 0)))
            ->firstOrFail();
        $enrollment = $stage->enrollment()->with(['course', 'batch'])->firstOrFail();
        if ($user !== null && ! $user->is_super_admin && (int) $user->branch_id !== (int) $enrollment->branch_id) {
            abort(404);
        }

        $eligibility = $this->academics->examEligibility($enrollment, $stage);
        $latestExam = $stage->examRecords()->orderByDesc('attempt_number')->first();

        if (in_array($status, ['eligible', 'retake'], true)) {
            $duration = $eligibility['duration'] ?? null;
            if (! $eligibility['eligible'] || ($duration !== null && ! $duration['completed'])) {
                throw ValidationException::withMessages(['status' => implode(' ', $eligibility['reasons'])]);
            }
            if ($status === 'retake' && ($latestExam === null || $latestExam->getRawOriginal('result_status') !== 'failed')) {
                throw ValidationException::withMessages(['status' => 'A retake can only be scheduled after a failed attempt.']);
            }
            if ($status === 'eligible' && $latestExam !== null
                && ! in_array($latestExam->getRawOriginal('result_status'), ['failed', 'incomplete'], true)) {
                throw ValidationException::withMessages(['status' => 'A new exam can only be scheduled when no current attempt exists or the previous attempt was not passed.']);
            }

            // Save the eligibility decision and scheduled attempt together for an auditable transition.
            $attempt = DB::transaction(function () use ($enrollment, $stage, $data, $status): ExamRecord {
                $attempt = $this->academics->recordExamAttempt($enrollment, $stage, [
                    'exam_name' => (string) $data['exam_name'],
                    'exam_date' => (string) $data['exam_date'],
                    'total_marks' => $data['total_marks'] ?? null,
                ]);
                // The shared academic workflow creates the attempt before Exam Control schedules it.
                $attempt->update(['attempt_status' => 'scheduled']);
                $this->recordStatusHistory($stage, $attempt, $status, null, $data['remarks'] ?? null);

                return $attempt;
            });

            return ['status' => $status, 'exam' => $attempt->fresh(), 'eligibility' => $eligibility];
        }

        if ($latestExam === null) {
            throw ValidationException::withMessages(['status' => 'Schedule an eligible examination before updating its status.']);
        }

        if (in_array($status, ['paper_submitted', 'pending_result'], true)) {
            if ($latestExam->getRawOriginal('attempt_status') === 'cancelled') {
                throw ValidationException::withMessages(['status' => 'A cancelled examination cannot be marked as submitted.']);
            }
            if ($latestExam->getRawOriginal('attempt_status') === 'absent') {
                throw ValidationException::withMessages(['status' => 'An absent examination cannot be marked as submitted.']);
            }
            if ($latestExam->getRawOriginal('result_status') !== 'pending') {
                throw ValidationException::withMessages(['status' => 'A published exam result cannot be changed back to pending.']);
            }
            $oldStatus = $this->displayStatus($latestExam, $eligibility['eligible']);
            DB::transaction(function () use ($latestExam, $stage, $status, $oldStatus, $data): void {
                $latestExam->update(['attempt_status' => 'appeared', 'result_status' => 'pending']);
                $this->recordStatusHistory($stage, $latestExam, $status, $oldStatus, $data['remarks'] ?? null);
            });

            return ['status' => $status, 'exam' => $latestExam->fresh(), 'eligibility' => $eligibility];
        }

        $result = match ($status) {
            'passed' => 'passed',
            'failed' => 'failed',
            'incomplete' => 'incomplete',
            'absent' => null,
            default => throw ValidationException::withMessages(['status' => 'The selected exam-control status is invalid.']),
        };

        return DB::transaction(function () use ($stage, $latestExam, $status, $result, $data, $eligibility): array {
            $oldStatus = $this->displayStatus($latestExam, $eligibility['eligible']);
            if ($latestExam->getRawOriginal('result_status') !== 'pending'
                && $latestExam->getRawOriginal('attempt_status') !== 'absent') {
                throw ValidationException::withMessages(['status' => 'The result for this attempt has already been published.']);
            }
            if ($latestExam->getRawOriginal('attempt_status') !== 'appeared' && $status !== 'absent') {
                throw ValidationException::withMessages(['status' => 'Submit the paper before publishing its result.']);
            }
            if ($status === 'absent') {
                $latestExam->update(['attempt_status' => 'absent']);
            } else {
                $this->academics->updateExamResult($latestExam, [
                    'result_status' => $result,
                    'obtained_marks' => $data['obtained_marks'] ?? null,
                    'grade' => $data['grade'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                ]);
            }
            $this->recordStatusHistory($stage, $latestExam, $status, $oldStatus, $data['remarks'] ?? null);

            return ['status' => $status, 'exam' => $latestExam->fresh(), 'eligibility' => $eligibility];
        });
    }

    private function displayStatus(?ExamRecord $exam, bool $eligible): string
    {
        if ($exam === null) {
            return $eligible ? 'eligible' : 'not_eligible';
        }
        if ($exam->getRawOriginal('attempt_status') === 'absent') {
            return 'absent';
        }
        if ($exam->getRawOriginal('result_status') !== 'pending') {
            return $exam->getRawOriginal('result_status');
        }
        if ($exam->getRawOriginal('attempt_status') === 'appeared') {
            return 'pending_result';
        }

        return $eligible ? 'eligible' : 'not_eligible';
    }

    private function recordStatusHistory(
        StudentStageRecord $stage,
        ExamRecord $exam,
        string $newStatus,
        ?string $oldStatus,
        ?string $reason,
    ): void {
        StudentStageHistory::query()->create([
            'enrollment_id' => $stage->enrollment_id,
            'student_stage_record_id' => $stage->id,
            'actor_user_id' => auth()->id(),
            'event_type' => 'exam_control_status_changed',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $newStatus, 'exam_record_id' => $exam->id],
            'reason' => $reason,
        ]);
    }
}
