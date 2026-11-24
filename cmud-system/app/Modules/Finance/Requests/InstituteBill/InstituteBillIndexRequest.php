<?php

namespace App\Modules\Finance\Requests\InstituteBill;

use Illuminate\Foundation\Http\FormRequest;

/** Validate filters for the institute bill register. */
class InstituteBillIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'nullable', 'date'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'billing_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'payment_status' => ['sometimes', 'nullable', 'in:unpaid,partial,paid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /** Reject inverted date ranges before the service builds a register query. */
    public function after(): array
    {
        return [function ($validator): void {
            $from = $this->input('date_from');
            $to = $this->input('date_to');

            if ($from && $to && strtotime((string) $to) < strtotime((string) $from)) {
                $validator->errors()->add('date_to', 'The end date must be on or after the start date.');
            }
        }];
    }
}
