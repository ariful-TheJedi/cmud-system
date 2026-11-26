<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate common fields submitted by student payment forms. */
abstract class StudentBillPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    protected function commonRules(): array
    {
        return [
            'student_id' => ['required', 'string', 'max:50'],
            // Each receipt must be explicitly tied to the enrollment chosen in the billing UI.
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'payer_phone' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
