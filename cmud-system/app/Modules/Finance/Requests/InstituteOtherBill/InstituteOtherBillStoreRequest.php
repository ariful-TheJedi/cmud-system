<?php

namespace App\Modules\Finance\Requests\InstituteOtherBill;

use Illuminate\Foundation\Http\FormRequest;

/** Validate custom bill header fields and editable item rows. */
class InstituteOtherBillStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'university_name' => ['required', 'string', 'max:255'],
            'issuer_name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', 'in:draft,pending_approval,approved,rejected'],
            'rejection_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'bill_date' => ['required', 'date'],
            'due_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:bill_date'],
            'bill_items' => ['required', 'array', 'min:1', 'max:100'],
            'bill_items.*' => ['required', 'array:description,quantity,unit_price'],
            'bill_items.*.description' => ['required', 'string', 'max:255'],
            'bill_items.*.quantity' => ['required', 'integer', 'min:1', 'max:999999999'],
            'bill_items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
