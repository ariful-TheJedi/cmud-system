<?php

namespace App\Modules\Security\Requests\UserControl;

use Illuminate\Foundation\Http\FormRequest;

/** Validate the employee and initial password used to provision a login account. */
class CreateUserFromStuffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'stuff_id' => ['required', 'integer', 'exists:stuff,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
