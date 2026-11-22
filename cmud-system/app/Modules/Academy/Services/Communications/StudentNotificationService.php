<?php

namespace App\Modules\Academy\Services\Communications;

use App\Models\Academy\Certificate;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Academy\StudentNotification;
use App\Models\Academy\StudentStageRecord;
use App\Modules\Finance\Services\StudentFinancialOverviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class StudentNotificationService
{
    private const SYSTEM_TYPES = [
        'payment_due',
        'course_completion',
        'certificate_status',
        'pending_academic_action',
        'examination',
    ];

    /** @param array<string, mixed> $data */
    public function create(Student $student, array $data): StudentNotification
    {
        $enrollmentId = isset($data['enrollment_id']) ? (int) $data['enrollment_id'] : null;
        if ($enrollmentId !== null && ! $student->enrollments()->whereKey($enrollmentId)->exists()) {
            throw ValidationException::withMessages([
                'enrollment_id' => 'The enrollment does not belong to this student.',
            ]);
        }

        return $student->notifications()->create([
            ...$data,
            'created_by' => auth()->id(),
        ]);
    }

    /** @return LengthAwarePaginator<int, StudentNotification> */
    public function list(Student $student, int $perPage = 25): LengthAwarePaginator
    {
        return $student->notifications()
            ->with('enrollment:id,enrollment_code,course_id')
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function markRead(Student $student, StudentNotification $notification): StudentNotification
    {
        if ((int) $notification->student_id !== (int) $student->id) {
            throw ValidationException::withMessages([
                'notification' => 'The notification does not belong to this student.',
            ]);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $notification->refresh();
    }

    /**
     * Reconcile in-app alerts against current source-of-truth data, preserving resolved notifications.
     *
     * @return array{created: int, resolved: int}
     */
    public function synchronize(Student $student, StudentFinancialOverviewService $finances): array
    {
        $activeKeys = [];
        $changed = 0;

        foreach ($student->enrollments()->with('course:id,name')->get() as $enrollment) {
            if ($enrollment->getRawOriginal('status') === 'completed') {
                $changed += $this->upsertSystemNotification(
                    $student,
                    $enrollment,
                    'course_completion',
                    'Course completed',
                    'Enrollment '.$enrollment->enrollment_code.' has been marked completed.',
                    'course_completion:'.$enrollment->id,
                    'enrollments',
                    $enrollment->id,
                );
                $activeKeys[] = 'course_completion:'.$enrollment->id;
            }
        }

        foreach ($finances->forStudent($student)['enrollments'] as $summary) {
            $due = $summary['outstanding_due'] ?? null;
            if ($due === null || (float) $due <= 0) {
                continue;
            }

            $enrollment = Enrollment::query()->whereKey((int) $summary['enrollment_id'])->first();
            if (! $enrollment) {
                continue;
            }

            $key = 'payment_due:enrollment:'.$enrollment->id;
            $changed += $this->upsertSystemNotification(
                $student,
                $enrollment,
                'payment_due',
                'Payment due',
                'Enrollment '.$enrollment->enrollment_code.' has '.$due.' outstanding.',
                $key,
                'enrollments',
                $enrollment->id,
                ['amount_due' => $due],
            );
            $activeKeys[] = $key;
        }

        foreach (StudentStageRecord::query()
            ->whereHas('enrollment', fn ($query) => $query->where('student_id', $student->id))
            ->with('enrollment:id,enrollment_code,student_id', 'courseStage:id,name')
            ->whereIn('status', ['incomplete', 'failed', 'on_hold'])
            ->get() as $stageRecord) {
            $key = 'pending_academic:'.$stageRecord->id;
            $changed += $this->upsertSystemNotification(
                $student,
                $stageRecord->enrollment,
                'pending_academic_action',
                'Academic action required',
                'Stage '.$stageRecord->courseStage->name.' requires follow-up.',
                $key,
                'student_stage_records',
                $stageRecord->id,
                ['stage_status' => $stageRecord->getRawOriginal('status')],
            );
            $activeKeys[] = $key;
        }

        $certificateIds = Certificate::query()
            ->whereHas('enrollment', fn ($query) => $query->where('student_id', $student->id))
            ->whereIn('status', ['processing', 'ready', 'issued'])
            ->with('enrollment:id,student_id,enrollment_code')
            ->get();

        foreach ($certificateIds as $certificate) {
            $status = (string) $certificate->getRawOriginal('status');
            $key = 'certificate:'.$certificate->id.':'.$status;
            $changed += $this->upsertSystemNotification(
                $student,
                $certificate->enrollment,
                'certificate_status',
                'Certificate status updated',
                'Certificate '.$certificate->certificate_number.' is '.$status.'.',
                $key,
                'certificates',
                $certificate->id,
                ['certificate_status' => $status],
            );
            $activeKeys[] = $key;
        }

        $scheduledExams = StudentStageRecord::query()
            ->whereHas('enrollment', fn ($query) => $query->where('student_id', $student->id))
            ->with('enrollment:id,student_id,enrollment_code')
            ->whereHas('examRecords', fn ($query) => $query
                ->where('attempt_status', 'scheduled')
                ->whereDate('exam_date', '>=', Carbon::today()))
            ->with(['examRecords' => fn ($query) => $query
                ->where('attempt_status', 'scheduled')
                ->whereDate('exam_date', '>=', Carbon::today())])
            ->get();

        foreach ($scheduledExams as $stageRecord) {
            foreach ($stageRecord->examRecords as $exam) {
                $key = 'examination:'.$exam->id;
                $changed += $this->upsertSystemNotification(
                    $student,
                    $stageRecord->enrollment,
                    'examination',
                    'Upcoming examination',
                    $exam->exam_name.' is scheduled for '.$exam->getRawOriginal('exam_date').'.',
                    $key,
                    'exam_records',
                    $exam->id,
                    ['exam_date' => $exam->getRawOriginal('exam_date')],
                );
                $activeKeys[] = $key;
            }
        }

        $stale = $student->notifications()
            ->whereIn('type', self::SYSTEM_TYPES)
            ->whereNull('resolved_at');

        if ($activeKeys !== []) {
            $stale->whereNotIn('dedupe_key', $activeKeys);
        }

        $resolved = $stale->update(['resolved_at' => now()]);

        return [
            'created' => $changed,
            'resolved' => $resolved,
        ];
    }

    /** @param array<string, mixed> $data */
    private function upsertSystemNotification(
        Student $student,
        ?Enrollment $enrollment,
        string $type,
        string $title,
        string $message,
        string $dedupeKey,
        string $relatedType,
        int $relatedId,
        array $data = [],
    ): int {
        $notification = StudentNotification::query()->updateOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'student_id' => $student->id,
                'enrollment_id' => $enrollment?->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_url' => '/academy/students/'.$student->student_code
                    .($enrollment === null ? '' : '?enrollment_id='.$enrollment->id),
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'data' => $data,
                'resolved_at' => null,
            ],
        );

        return $notification->wasRecentlyCreated ? 1 : 0;
    }
}
