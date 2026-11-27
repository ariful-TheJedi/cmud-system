<?php

namespace App\Modules\Academy\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Academy\Batch;

class BatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $routeBatch = $this->route('batch');
        $batch = $routeBatch instanceof Batch ? $routeBatch : null;
        $courseId = $this->input('course_id', $batch?->course_id);

        return [
            'course_id' => [$required, 'integer', 'exists:courses,id'],
            'branch_id' => [$required, 'integer', 'exists:branches,id'],
            'code' => [
                $required,
                'string',
                'max:255',
                Rule::unique('batches', 'code')
                    ->where('course_id', $courseId)
                    ->ignore($batch?->id),
            ],
            'name' => [$required, 'string', 'max:255'],
            'admission_opens_on' => ['sometimes', 'nullable', 'date'],
            'admission_closes_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:admission_opens_on'],
            'starts_on' => [$required, 'date'],
            'expected_ends_on' => [$required, 'date', 'after_or_equal:starts_on'],
            'actual_ends_on' => ['sometimes', 'nullable', 'date'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => [
                $required,
                Rule::in(['active', 'open', 'upcoming', 'ongoing', 'inactive', 'completed', 'cancelled']),
            ],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
