<?php

namespace App\Modules\Academy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnrollmentAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
