<?php

namespace App\Modules\Academy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentEnrollmentRequest extends FormRequest
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
            'enrollment_date' => ['required', 'date'],
        ];
    }
}
