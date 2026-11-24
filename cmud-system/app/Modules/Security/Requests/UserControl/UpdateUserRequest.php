<?php

namespace App\Modules\Security\Requests\UserControl;

use App\Models\Security\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validate editable user profile, branch scope, status, and optional password. */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:100'],
            'branch_id' => [$user->is_super_admin ? 'nullable' : 'required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
