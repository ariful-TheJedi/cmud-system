<?php

namespace App\Modules\Finance\Requests;

/** Validate an atomic other-expense bill and payment submission. */
class StudentOtherPaymentStoreRequest extends StudentBillPaymentRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'student_expense_type_id' => ['required', 'integer', 'exists:student_expense_types,id'],
        ]);
    }
}
