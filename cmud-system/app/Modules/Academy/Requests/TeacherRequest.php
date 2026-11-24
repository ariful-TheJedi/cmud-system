<?php

namespace App\Modules\Academy\Requests;

use App\Models\Academy\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $routeTeacher = $this->route('teacher');
        $teacher = $routeTeacher instanceof Teacher ? $routeTeacher : null;
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'institute_id' => [$required, 'integer', 'exists:institutes,id'],
            'stuff_id' => ['sometimes', 'nullable', 'integer', 'exists:stuff,id'],
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
