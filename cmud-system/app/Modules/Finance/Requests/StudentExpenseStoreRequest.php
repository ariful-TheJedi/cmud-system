<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate a new enrollment-linked student expense without accepting ownership fields. */
class StudentExpenseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'student_expense_type_id' => ['required', 'integer', 'exists:student_expense_types,id'],
            'price_version_id' => ['required', 'integer', 'exists:student_expense_price_versions,id'],
            'student_stage_record_id' => ['sometimes', 'nullable', 'integer', 'exists:student_stage_records,id'],
            'quantity' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999.999'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'issued_on' => ['sometimes', 'date'],
            'due_on' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // Omit payment to issue a bill for later; include it to collect payment atomically.
            'payment' => ['sometimes', 'required', 'array:amount,financial_account_id,payment_method_id,idempotency_key,paid_at,transaction_reference,payer_phone,notes'],
            'payment.amount' => ['required_with:payment', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'payment.financial_account_id' => ['required_with:payment', 'integer', 'exists:financial_accounts,id'],
            'payment.payment_method_id' => ['sometimes', 'integer', 'exists:payment_methods,id'],
            'payment.idempotency_key' => ['required_with:payment', 'string', 'max:100'],
            'payment.paid_at' => ['sometimes', 'date', 'before_or_equal:now'],
            'payment.transaction_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment.payer_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'payment.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
