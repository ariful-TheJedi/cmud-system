<?php

namespace App\Modules\Academy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GroupMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
