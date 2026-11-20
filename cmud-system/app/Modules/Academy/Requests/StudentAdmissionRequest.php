<?php

namespace App\Modules\Academy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'student_code' => ['required', 'string', 'max:255', 'unique:students,student_code'],
            'admission_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'course_id' => ['sometimes', 'nullable', 'integer', 'exists:courses,id'],
            'admission_date' => ['nullable', 'date'],
            'full_name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:50'],
            'phone_numbers' => ['required_without:phone', 'array', 'min:1'],
            'phone_numbers.*' => ['required', 'string', 'max:30', 'distinct'],
            'phone' => ['required_without:phone_numbers', 'string', 'max:30'],
            'alternative_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'present_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],
            'medical_college_id' => ['nullable', 'integer', 'exists:medical_colleges,id'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'graduation_year' => ['nullable', 'integer', 'between:1900,2100'],
            'bmdc_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
