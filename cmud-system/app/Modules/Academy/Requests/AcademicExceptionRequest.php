<?php

namespace App\Modules\Academy\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'student_stage_record_id' => ['nullable', 'integer', 'exists:student_stage_records,id'],
            'exception_type' => [
                'required',
                Rule::in(['progression', 'exam_fee', 'deadline_extension', 'interrupted_study', 'other']),
            ],
            'reason' => ['required', 'string', 'max:4000'],
            'details' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
