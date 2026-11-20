<?php

namespace App\Modules\Academy\Requests;

use App\Enums\Academy\StudentStageStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StageStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(StudentStageStatus::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
