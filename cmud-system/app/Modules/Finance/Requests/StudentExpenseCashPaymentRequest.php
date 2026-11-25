<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate a payment against one enrollment-owned expense. */
class StudentExpenseCashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'payment_method_id' => ['sometimes', 'integer', 'exists:payment_methods,id'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'paid_at' => ['sometimes', 'date', 'before_or_equal:now'],
            'transaction_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payer_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
