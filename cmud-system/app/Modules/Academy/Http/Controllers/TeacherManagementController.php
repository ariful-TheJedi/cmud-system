<?php

namespace App\Modules\Academy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Teacher;
use App\Modules\Academy\Services\TeacherManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Validates teacher-management requests and delegates persistence to its service. */
class TeacherManagementController extends Controller
{
    /** List teachers with their institute, staff, subject, and branch summaries. */
    public function index(TeacherManagementService $teachers): JsonResponse
    {
        return response()->json($teachers->paginate());
    }

    /** Return one teacher and its current subject and branch assignments. */
    public function show(Teacher $teacher, TeacherManagementService $teachers): JsonResponse
    {
        return response()->json(['data' => $teachers->find($teacher)]);
    }

    /** Create a teacher profile and any supplied subject/branch assignments. */
    public function store(Request $request, TeacherManagementService $teachers): JsonResponse
    {
        $data = $request->validate($this->rules());

        return response()->json(['data' => $teachers->create($data)], 201);
    }

    /** Update supplied teacher fields and optionally replace subject/branch assignments. */
    public function update(Request $request, Teacher $teacher, TeacherManagementService $teachers): JsonResponse
    {
        $data = $request->validate($this->rules(true, $teacher));

        return response()->json(['data' => $teachers->update($teacher, $data)]);
    }

    /** Prevent deleting a teacher referenced by course assignments or institute expenses. */
    public function destroy(Teacher $teacher, TeacherManagementService $teachers): JsonResponse
    {
        if (! $teachers->delete($teacher)) {
            return response()->json(['message' => 'This teacher is in use and cannot be deleted.'], 409);
        }

        return response()->json(['message' => 'Teacher deleted.']);
    }

    /**
     * Build create/update rules from the persisted teacher schema.
     * Assignment arrays replace their corresponding links only when included.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $partial = false, ?Teacher $teacher = null): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'institute_id' => [$required, 'integer', 'exists:institutes,id'],
            'staff_id' => ['sometimes', 'nullable', 'integer', 'exists:staff,id'],
            'teacher_code' => [$required, 'string', 'max:255', Rule::unique('teachers', 'teacher_code')->ignore($teacher?->id)],
            'full_name' => [$required, 'string', 'max:255'],
            'phone' => [$required, 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'qualification' => ['sometimes', 'nullable', 'string'],
            'specialization' => ['sometimes', 'nullable', 'string', 'max:255'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'organization' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bmdc_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'photo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'subject_assignments' => ['sometimes', 'array'],
            'subject_assignments.*.subject_id' => ['required', 'integer', 'distinct', 'exists:subjects,id'],
            'subject_assignments.*.teaching_role' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject_assignments.*.is_active' => ['sometimes', 'boolean'],
            'subject_assignments.*.notes' => ['sometimes', 'nullable', 'string'],
            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => ['integer', 'distinct', 'exists:branches,id'],
        ];
    }
}
