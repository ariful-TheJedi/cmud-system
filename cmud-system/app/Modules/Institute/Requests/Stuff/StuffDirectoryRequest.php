<?php

namespace App\Modules\Institute\Requests\Stuff;

use Illuminate\Foundation\Http\FormRequest;

/** Validates search and pagination options for the stuff directory endpoint. */
class StuffDirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'stuff_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'branch' => ['sometimes', 'nullable', 'string', 'max:255'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'status' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employment_status' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
