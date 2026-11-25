<?php

namespace App\Modules\Academy\Services\Communications;

use App\Models\Academy\Certificate;
use App\Models\Academy\CommunicationGroup;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Academy\StudentCommunication;
use App\Models\Academy\StudentGroupMembership;
use App\Models\Institute\Branch;
use App\Modules\Academy\Services\AcademicProgress\AcademicProgressService;
use App\Modules\Finance\Services\StudentBill\StudentFinancialOverviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentCommunicationService
{
    /** @param array<string, mixed> $data */
    public function record(Student $student, array $data): StudentCommunication
    {
        $enrollmentId = isset($data['enrollment_id']) ? (int) $data['enrollment_id'] : null;
        if ($enrollmentId !== null && ! $student->enrollments()->whereKey($enrollmentId)->exists()) {
            throw ValidationException::withMessages([
                'enrollment_id' => 'The enrollment does not belong to this student.',
            ]);
        }

        return $student->communications()->create([
            ...$data,
            'created_by' => auth()->id(),
        ]);
    }

    /** @return LengthAwarePaginator<int, StudentCommunication> */
    public function history(Student $student, int $perPage = 25): LengthAwarePaginator
    {
        return $student->communications()
            ->with(['enrollment:id,enrollment_code,course_id', 'responsibleUser:id,name'])
            ->orderByDesc('contacted_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Provide contact staff with the current Academy and Finance information without copying its calculations.
     *
     * @return array<string, mixed>
     */
    public function contactContext(
        Student $student,
        AcademicProgressService $academics,
        StudentFinancialOverviewService $finances,
    ): array {
        return [
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'full_name' => $student->full_name,
                'phone' => $student->phone,
                'alternative_phone' => $student->alternative_phone,
                'email' => $student->email,
                'status' => $student->getRawOriginal('status'),
            ],
            'academics' => $student->enrollments()
                ->with('course:id,code,name')
                ->get()
                ->map(fn (Enrollment $enrollment): array => $academics->overview($enrollment))
                ->all(),
            'finances' => $finances->forStudent($student),
            'certificates' => Certificate::query()
                ->whereHas('enrollment', fn ($query) => $query->where('student_id', $student->id))
                ->with('certificateTemplate:id,name')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Certificate $certificate): array => [
                    'id' => $certificate->id,
                    'enrollment_id' => $certificate->enrollment_id,
                    'certificate_number' => $certificate->certificate_number,
                    'name' => $certificate->certificateTemplate?->name,
                    'status' => $certificate->getRawOriginal('status'),
                ])
                ->all(),
        ];
    }

    /** Start a new membership period; prior leave records are never overwritten. */
    public function joinGroup(Student $student, CommunicationGroup $group, ?string $notes = null): StudentGroupMembership
    {
        if (! $group->is_active || (int) $group->institute_id !== (int) $student->institute_id) {
            throw ValidationException::withMessages([
                'communication_group_id' => 'The communication group is not active for the student’s institute.',
            ]);
        }

        $this->assertGroupEnrollmentEligibility($student, $group);

        if ($student->groupMemberships()
            ->where('communication_group_id', $group->id)
            ->whereIn('status', ['invited', 'joined'])
            ->whereNull('left_at')
            ->exists()) {
            throw ValidationException::withMessages([
                'communication_group_id' => 'The student already has an active membership in this group.',
            ]);
        }

        return $student->groupMemberships()->create([
            'communication_group_id' => $group->id,
            'invited_at' => now(),
            'joined_at' => now(),
            'status' => 'joined',
            'notes' => $notes,
        ]);
    }

    public function leaveGroup(Student $student, CommunicationGroup $group, ?string $notes = null): StudentGroupMembership
    {
        return DB::transaction(function () use ($student, $group, $notes): StudentGroupMembership {
            $membership = $student->groupMemberships()
                ->where('communication_group_id', $group->id)
                ->whereIn('status', ['invited', 'joined'])
                ->whereNull('left_at')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $membership) {
                throw ValidationException::withMessages([
                    'communication_group_id' => 'The student has no active membership in this group.',
                ]);
            }

            $membership->update([
                'left_at' => now(),
                'status' => 'left',
                'notes' => $notes ?? $membership->notes,
            ]);

            return $membership->refresh();
        });
    }

    /** @return LengthAwarePaginator<int, StudentGroupMembership> */
    public function groupMembershipHistory(Student $student, int $perPage = 25): LengthAwarePaginator
    {
        return $student->groupMemberships()
            ->with('communicationGroup:id,name,platform,group_url')
            ->orderByDesc('joined_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    /** @return Collection<int, CommunicationGroup> */
    public function availableGroups(Student $student): Collection
    {
        return CommunicationGroup::query()
            ->where('institute_id', $student->institute_id)
            ->where('is_active', true)
            ->with(['branch:id,name', 'course:id,code,name', 'batch:id,code,name'])
            ->orderBy('name')
            ->get();
    }

    private function assertGroupEnrollmentEligibility(Student $student, CommunicationGroup $group): void
    {
        if ($group->batch_id !== null) {
            $eligible = $student->enrollments()
                ->where('batch_id', $group->batch_id)
                ->whereIn('status', ['enrolled', 'ongoing', 'on_hold'])
                ->exists();
        } elseif ($group->course_id !== null) {
            $eligible = $student->enrollments()
                ->where('course_id', $group->course_id)
                ->whereIn('status', ['enrolled', 'ongoing', 'on_hold'])
                ->exists();
        } elseif ($group->branch_id !== null) {
            $branchInstituteId = Branch::query()->whereKey($group->branch_id)->value('institute_id');
            $eligible = (int) $branchInstituteId === (int) $student->institute_id
                && ((int) $student->admission_branch_id === (int) $group->branch_id
                    || $student->enrollments()
                        ->where('branch_id', $group->branch_id)
                        ->whereIn('status', ['enrolled', 'ongoing', 'on_hold'])
                        ->exists());
        } else {
            $eligible = true;
        }

        if (! $eligible) {
            throw ValidationException::withMessages([
                'communication_group_id' => 'The student must have an active enrollment matching the group scope.',
            ]);
        }
    }
}
