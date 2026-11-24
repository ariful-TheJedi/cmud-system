<?php

namespace App\Modules\Finance\Requests\InstituteBill;

use Illuminate\Foundation\Http\FormRequest;

/** Validate the fields needed to record an institute supplier bill. */
class InstituteBillStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'billing_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_item_id' => ['required', 'integer', 'exists:institute_expense_categories,id'],
            'price_version_id' => ['sometimes', 'nullable', 'integer', 'exists:institute_expense_price_versions,id'],
            'payee_name' => ['required', 'string', 'max:255'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'description' => ['sometimes', 'nullable', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999999'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'billing_date' => ['required', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:billing_date'],
            'status' => ['sometimes', 'in:draft,approved,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
