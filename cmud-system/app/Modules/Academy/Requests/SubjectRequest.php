<?php

namespace App\Modules\Academy\Requests;

use App\Models\Academy\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $subject = $this->route('subject');

        return [
            'code' => [
                $required,
                'string',
                'max:255',
                Rule::unique('subjects', 'code')->ignore($subject instanceof Subject ? $subject->id : null),
            ],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

}
