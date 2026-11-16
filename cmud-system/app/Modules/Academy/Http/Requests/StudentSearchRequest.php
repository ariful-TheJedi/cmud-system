<?php

namespace App\Modules\Academy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StudentSearchRequest extends FormRequest
{
    /** @var list<string> */
    private const QUERY_FIELDS = [
        'search', 'student_code', 'name', 'phone', 'course_id', 'batch_id',
        'admission_date', 'admission_month', 'admission_year', 'student_status',
        'enrollment_status', 'institute_id', 'branch_id', 'stage_id', 'date_from',
        'date_to', 'sort_by', 'sort_direction', 'page', 'per_page', 'include_filters',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reject misspelled or unsupported query keys instead of silently ignoring them.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query()), self::QUERY_FIELDS) as $field) {
                $validator->errors()->add($field, 'This query parameter is not supported.');
            }

            if (($this->query('enrollment_status') === 'none')
                && collect(['course_id', 'batch_id', 'stage_id'])->contains(fn (string $field): bool => $this->query->has($field))) {
                $validator->errors()->add('enrollment_status', 'The none value cannot be combined with course, batch, or stage filters.');
            }
        });
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'student_code' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'course_id' => ['sometimes', 'integer', 'exists:courses,id'],
            'batch_id' => ['sometimes', 'integer', 'exists:batches,id'],
            'admission_date' => ['sometimes', 'date'],
            'admission_month' => ['sometimes', 'integer', 'between:1,12'],
            'admission_year' => ['sometimes', 'integer', 'between:1900,2100'],
            'student_status' => ['sometimes', Rule::in(['active', 'inactive', 'blocked'])],
            'enrollment_status' => ['sometimes', Rule::in(['active', 'enrolled', 'ongoing', 'on hold', 'completed', 'withdrawn', 'cancelled', 'none'])],
            'institute_id' => ['sometimes', 'integer', 'exists:institutes,id'],
            'branch_id' => ['sometimes', 'integer', 'exists:branches,id'],
            'stage_id' => ['sometimes', 'integer', 'exists:course_stages,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['sometimes', Rule::in(['student_code', 'full_name', 'admission_date', 'created_at'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'include_filters' => ['sometimes', 'boolean'],
        ];
    }
}
