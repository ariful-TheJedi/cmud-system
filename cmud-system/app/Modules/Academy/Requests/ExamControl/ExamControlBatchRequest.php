<?php

namespace App\Modules\Academy\Requests\ExamControl;

use Illuminate\Foundation\Http\FormRequest;

class ExamControlBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['search' => ['sometimes', 'nullable', 'string', 'max:255']];
    }
}
