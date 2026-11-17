<?php

namespace App\Modules\Academy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StudentAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level authentication and permission middleware are intentionally
        // deferred until the application access rules are implemented.
        return true;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'failure',
            'message' => 'You are not authorized to submit an admission.',
        ], 403));
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'failure',
            'message' => 'The admission details are invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }

    /** @return array<string, array<int, string>> */
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
