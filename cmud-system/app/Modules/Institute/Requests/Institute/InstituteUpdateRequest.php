<?php

namespace App\Modules\Institute\Requests\Institute;

use App\Models\Institute\Institute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstituteUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Institute $institute */
        $institute = $this->route('institute');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'tin_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bin_number' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('institutes', 'bin_number')->ignore($institute->id)],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'address' => ['sometimes', 'required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'remove_logo' => ['sometimes', 'boolean'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'branches' => ['sometimes', 'array'],
            'branches.*.id' => ['sometimes', 'integer', 'distinct', Rule::exists('branches', 'id')->where('institute_id', $institute->id)],
            'branches.*.code' => ['required', 'string', 'max:255'],
            'branches.*.name' => ['required', 'string', 'max:255'],
            'branches.*.phone' => ['required', 'string', 'max:30'],
            'branches.*.email' => ['nullable', 'email', 'max:255'],
            'branches.*.address' => ['required', 'string'],
            'branches.*.is_active' => ['sometimes', 'boolean'],
            'deleted_branch_ids' => ['sometimes', 'array'],
            'deleted_branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where('institute_id', $institute->id)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (($this->boolean('remove_logo')) && $this->hasFile('logo')) {
                $validator->errors()->add('logo', 'Upload a new logo or remove the current one, not both.');
            }
        });
    }
}
