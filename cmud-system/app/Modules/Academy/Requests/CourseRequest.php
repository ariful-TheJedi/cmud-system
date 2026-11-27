<?php

namespace App\Modules\Academy\Requests;

use App\Enums\Academy\CourseStatus;
use App\Models\Academy\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $routeCourse = $this->route('course');
        $course = $routeCourse instanceof Course ? $routeCourse : null;

        return [
            'institute_id' => [$required, 'integer', 'exists:institutes,id'],
            'code' => [$required, 'string', 'max:255', Rule::unique('courses', 'code')->ignore($course?->id)],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'duration_months' => [$required, 'integer', 'min:1', 'max:65535'],
            'completion_period_months' => [$required, 'integer', 'min:1', 'max:65535'],
            'course_mode' => [$required, Rule::in(['online', 'offline', 'dual'])],
            'status' => [$required, Rule::enum(CourseStatus::class)],
            'is_available_for_enrollment' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'subject_ids' => ['sometimes', 'array'],
            'subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $duration = $this->input('duration_months');
                $completionPeriod = $this->input('completion_period_months');

                if ($duration !== null && $completionPeriod !== null
                    && (int) $completionPeriod < (int) $duration) {
                    $validator->errors()->add(
                        'completion_period_months',
                        'The permitted completion period must not be shorter than the standard course duration.',
                    );
                }
            },
        ];
    }
}
