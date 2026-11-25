<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate exact Student ID lookup input for the enrollment billing workspace. */
class StudentBillingSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['student_id' => ['required', 'string', 'max:50']];
    }
}
