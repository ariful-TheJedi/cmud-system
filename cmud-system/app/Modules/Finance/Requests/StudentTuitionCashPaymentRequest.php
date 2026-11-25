<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate the amount, payment method, and destination for a tuition payment. */
class StudentTuitionCashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The Finance service enforces branch scope and cash-ledger policy for every caller.
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'payment_method_id' => ['sometimes', 'integer', 'exists:payment_methods,id'],
            // Reusing a request key returns the original receipt instead of recording twice.
            'idempotency_key' => ['required', 'string', 'max:100'],
            'paid_at' => ['sometimes', 'date', 'before_or_equal:now'],
            'transaction_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payer_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
