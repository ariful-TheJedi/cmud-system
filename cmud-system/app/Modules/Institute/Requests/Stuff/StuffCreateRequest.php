<?php

namespace App\Modules\Institute\Requests\Stuff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates stuff profile fields, optional media, and institute assignments for creation. */
class StuffCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'institute_id' => ['required', 'integer', 'exists:institutes,id'],
            'stuff_code' => [
                'required', 'string', 'max:255',
                Rule::unique('stuff', 'stuff_code')->where('institute_id', $this->input('institute_id')),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'nid_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nid_file' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'photo' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'designation' => ['required', 'string', 'max:255'],
            'joining_date' => ['required', 'date'],
            'leaving_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:joining_date'],
            'employment_status' => ['required', 'string', Rule::in(['active', 'on_leave', 'inactive'])],
            'notes' => ['sometimes', 'nullable', 'string'],
            'assignments' => ['sometimes', 'array'],
            'assignments.*' => ['required', 'array:branch_id,department_id'],
            'assignments.*.branch_id' => ['required', 'integer', 'exists:branches,id'],
            'assignments.*.department_id' => ['required', 'integer', 'exists:departments,id'],
        ];
    }

    /** Reject repeated branch/department pairs before persistence. */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $seen = [];
            $assignments = $this->input('assignments', []);
            if (! is_array($assignments)) {
                return;
            }

            foreach ($assignments as $index => $assignment) {
                if (! is_array($assignment) || ! isset($assignment['branch_id'], $assignment['department_id'])) {
                    continue;
                }

                $key = $assignment['branch_id'].':'.$assignment['department_id'];
                if (isset($seen[$key])) {
                    $validator->errors()->add("assignments.{$index}", 'This branch and department assignment is duplicated.');
                }
                $seen[$key] = true;
            }
        });
    }
}
