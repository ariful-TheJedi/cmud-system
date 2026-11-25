<?php

namespace App\Modules\Finance\Requests\InstituteBill;

use Illuminate\Foundation\Http\FormRequest;

/** Validate a manually received cash payment against an approved institute bill. */
class InstituteBillCashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The service checks bill ownership and approval state for every caller.
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
        ];
    }
}
