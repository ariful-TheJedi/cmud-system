<?php

namespace App\Modules\Academy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $studentCode = (string) $this->route('studentCode');

        return [
            'student_code' => ['required', 'string', 'max:255', Rule::unique('students', 'student_code')->ignore($studentCode, 'student_code')],
            'full_name' => ['required', 'string', 'max:255'],
            'admission_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'phone_numbers' => ['required', 'array', 'min:1'],
            'phone_numbers.*' => ['required', 'string', 'max:30', 'distinct'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'present_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],
            'admission_date' => ['nullable', 'date'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'graduation_year' => ['nullable', 'integer', 'between:1900,2100'],
            'bmdc_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
