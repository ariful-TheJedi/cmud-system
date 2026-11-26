<?php

namespace App\Modules\Academy\Requests\ExamControl;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamControlStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                'eligible', 'paper_submitted', 'pending_result', 'passed', 'failed', 'retake', 'absent', 'incomplete',
            ])],
            'exam_name' => ['required_if:status,eligible,retake', 'string', 'max:255'],
            'exam_date' => ['required_if:status,eligible,retake', 'date'],
            'total_marks' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'obtained_marks' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'grade' => ['sometimes', 'nullable', 'string', 'max:50'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
