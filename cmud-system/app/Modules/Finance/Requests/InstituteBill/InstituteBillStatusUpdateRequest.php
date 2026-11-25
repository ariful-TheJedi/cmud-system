<?php

namespace App\Modules\Finance\Requests\InstituteBill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validate an institute bill approval-state change. */
class InstituteBillStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Status-change policy is enforced by the Finance service, independent of the HTTP entry point.
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // Draft remains accepted for bills created before the pending workflow was introduced.
            'status' => ['required', Rule::in(['draft', 'pending', 'approved', 'cancelled'])],
            'cancellation_reason' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
