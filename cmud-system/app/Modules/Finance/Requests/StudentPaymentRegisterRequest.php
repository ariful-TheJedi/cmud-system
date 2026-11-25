<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate filters for the branch-scoped student charge register. */
class StudentPaymentRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'course_id' => ['sometimes', 'nullable', 'integer', 'exists:courses,id'],
            'batch_id' => ['sometimes', 'nullable', 'integer'],
            'invoice_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
