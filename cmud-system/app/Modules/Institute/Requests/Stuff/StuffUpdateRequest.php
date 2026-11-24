<?php

namespace App\Modules\Institute\Requests\Stuff;

use App\Models\Institute\Stuff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates editable stuff profile fields before the service persists changes. */
class StuffUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Stuff $stuff */
        $stuff = $this->route('stuff');

        return [
            'stuff_code' => ['required', 'string', 'max:255', Rule::unique('stuff', 'stuff_code')->ignore($stuff->id)->where('institute_id', $stuff->institute_id)],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'nid_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nid_file' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'remove_nid_file' => ['sometimes', 'boolean'],
            'photo' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_photo' => ['sometimes', 'boolean'],
            'designation' => ['required', 'string', 'max:255'],
            'joining_date' => ['required', 'date'],
            'leaving_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:joining_date'],
            'employment_status' => ['required', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /** Prevent ambiguous requests that try to replace and remove the file together. */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->boolean('remove_nid_file') && $this->hasFile('nid_file')) {
                $validator->errors()->add('nid_file', 'Upload a new NID file or remove the current one, not both.');
            }

            if ($this->boolean('remove_photo') && $this->hasFile('photo')) {
                $validator->errors()->add('photo', 'Upload a new profile photo or remove the current one, not both.');
            }
        });
    }
}
