<?php

namespace App\Modules\Finance\Requests;

/** Validate a tuition receipt submitted from the student billing dialog. */
class StudentTuitionPaymentStoreRequest extends StudentBillPaymentRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->commonRules();
    }
}
