<?php

namespace App\Modules\Academy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'enrollment_id' => ['nullable', 'integer', 'exists:enrollments,id'],
            'type' => ['required', 'in:schedule_change,examination,student_activity,academic_alert'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'action_url' => ['nullable', 'string', 'max:2048'],
            'related_type' => ['nullable', 'string', 'max:100'],
            'related_id' => ['nullable', 'integer'],
            'data' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
