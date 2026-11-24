<?php

namespace App\Modules\Security\Requests\UserControl;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validate standalone branch-scoped user account details. */
class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:100'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
