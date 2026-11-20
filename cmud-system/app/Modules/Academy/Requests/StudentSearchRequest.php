<?php

namespace App\Modules\Academy\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'student_status' => ['sometimes', Rule::in(['registered', 'active', 'deactivated', 'suspended'])],
            'enrollment_status' => ['sometimes', Rule::in([
                'enrolled', 'ongoing', 'on_hold', 'completed', 'discontinued', 'cancelled', 'none',
            ])],
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
