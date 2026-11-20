<?php

namespace App\Modules\Academy\Requests;

use App\Models\Academy\Course;
use App\Models\Academy\CourseStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $course = $this->route('course');
        $stage = $this->route('stage');
        $courseId = $course instanceof Course ? $course->id : null;
        $stageId = $stage instanceof CourseStage ? $stage->id : null;

        return [
            'code' => [
                $required,
                'string',
                'max:255',
                Rule::unique('course_stages', 'code')
                    ->where('course_id', $courseId)
                    ->ignore($stageId),
            ],
            'name' => [$required, 'string', 'max:255'],
            'sequence_number' => [$required, 'integer', 'min:1', 'max:65535'],
            'duration_months' => [$required, 'integer', 'min:1', 'max:65535'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'requires_exam' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
