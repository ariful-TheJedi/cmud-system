<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate a new enrollment-linked student expense without accepting ownership fields. */
class StudentExpenseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'student_expense_type_id' => ['required', 'integer', 'exists:student_expense_types,id'],
            'price_version_id' => ['required', 'integer', 'exists:student_expense_price_versions,id'],
            'student_stage_record_id' => ['sometimes', 'nullable', 'integer', 'exists:student_stage_records,id'],
            'quantity' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999.999'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'issued_on' => ['sometimes', 'date'],
            'due_on' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
