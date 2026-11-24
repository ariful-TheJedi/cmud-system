<?php

namespace App\Modules\Finance\Requests\InstituteBill;

use Illuminate\Foundation\Http\FormRequest;

/** Validate the optional live-search term used by the bill item picker. */
class InstituteBillItemSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
