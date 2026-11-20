<?php

namespace App\Modules\Academy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExamAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'exam_name' => ['required', 'string', 'max:255'],
            'exam_date' => ['required', 'date'],
            'total_marks' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
