<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate the date used when resolving currently effective student expense prices. */
class StudentExpenseOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'issued_on' => ['sometimes', 'date'],
            'student_stage_record_id' => ['sometimes', 'integer', 'exists:student_stage_records,id'],
        ];
    }
}
