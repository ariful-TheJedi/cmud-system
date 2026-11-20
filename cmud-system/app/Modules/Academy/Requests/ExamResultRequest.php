<?php

namespace App\Modules\Academy\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'result_status' => ['required', Rule::in(['passed', 'failed', 'incomplete'])],
            'obtained_marks' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'grade' => ['sometimes', 'nullable', 'string', 'max:50'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
