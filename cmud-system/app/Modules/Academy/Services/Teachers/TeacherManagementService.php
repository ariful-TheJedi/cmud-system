<?php

namespace App\Modules\Academy\Services\Teachers;

use App\Models\Academy\Teacher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Persistence and relationship management for Academy teacher records. */
class TeacherManagementService
{
    /** Return teachers with their institute, optional stuff profile, modules, and branches. */
    /** @return LengthAwarePaginator<int, Teacher> */
    public function paginate(): LengthAwarePaginator
    {
        return Teacher::query()
            ->with(['institute:id,name', 'stuff:id,stuff_code,full_name', 'subjects:id,course_id,code,name', 'subjects.course:id,code,name', 'branches:id,name'])
            ->orderBy('full_name')
            ->paginate(25);
    }

    /** Return one teacher with the relationships used by the management API. */
    public function find(Teacher $teacher): Teacher
    {
        return $teacher->load(['institute:id,name', 'stuff:id,stuff_code,full_name', 'subjects:id,course_id,code,name', 'subjects.course:id,code,name', 'branches:id,name']);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Teacher
    {
        return DB::transaction(function () use ($data): Teacher {
            $subjectAssignments = $data['subject_assignments'] ?? [];
            $branchIds = $data['branch_ids'] ?? [];
            unset($data['subject_assignments'], $data['branch_ids']);

            $teacher = Teacher::query()->create($data);
            $this->syncSubjectAssignments($teacher, $subjectAssignments);
            $teacher->branches()->sync($branchIds);

            return $this->find($teacher);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Teacher $teacher, array $data): Teacher
    {
        return DB::transaction(function () use ($teacher, $data): Teacher {
            $subjectAssignments = $data['subject_assignments'] ?? null;
            $branchIds = $data['branch_ids'] ?? null;
            unset($data['subject_assignments'], $data['branch_ids']);

            $teacher->update($data);
            if ($subjectAssignments !== null) {
                $this->syncSubjectAssignments($teacher, $subjectAssignments);
            }
            if ($branchIds !== null) {
                $teacher->branches()->sync($branchIds);
            }

            return $this->find($teacher->refresh());
        });
    }

    /** Delete a teacher only when no teaching assignments or expenses reference it. */
    public function delete(Teacher $teacher): bool
    {
        if ($teacher->subjects()->exists() || $teacher->instituteExpenses()->exists()) {
            return false;
        }

        return DB::transaction(function () use ($teacher): bool {
            // Branch links have no history of their own, so remove them with the teacher profile.
            $teacher->branches()->detach();

            return (bool) $teacher->delete();
        });
    }

    /**
     * Replace subject assignment rows while preserving the pivot's teaching metadata.
     * The request validator ensures subject IDs are distinct before this method runs.
     *
     * @param  list<array{subject_id:int, teaching_role?:string|null, is_active?:bool, notes?:string|null}>  $assignments
     */
    private function syncSubjectAssignments(Teacher $teacher, array $assignments): void
    {
        $syncData = [];
        foreach ($assignments as $assignment) {
            $syncData[$assignment['subject_id']] = [
                'teaching_role' => $assignment['teaching_role'] ?? null,
                'is_active' => $assignment['is_active'] ?? true,
                'notes' => $assignment['notes'] ?? null,
            ];
        }

        $teacher->subjects()->sync($syncData);
    }
}
