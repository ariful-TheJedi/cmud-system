<?php

namespace App\Modules\Academy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'student_code' => $this->student_code,
            'full_name' => $this->full_name,
            'father_name' => $this->father_name,
            'mother_name' => $this->mother_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'phone' => $this->phone,
            'alternative_phone' => $this->alternative_phone,
            'phone_numbers' => $this->phone_numbers,
            'email' => $this->email,
            'present_address' => $this->present_address,
            'permanent_address' => $this->permanent_address,
            'admission_date' => $this->admission_date?->toDateString(),
            'qualification' => $this->qualification,
            'graduation_year' => $this->graduation_year,
            'bmdc_number' => $this->bmdc_number,
            'status' => $this->status,
            'institute' => $this->whenLoaded('institute', fn () => [
                'id' => $this->institute?->id,
                'name' => $this->institute?->name,
            ]),
            'admission_branch' => $this->whenLoaded('admissionBranch', fn () => [
                'id' => $this->admissionBranch?->id,
                'name' => $this->admissionBranch?->name,
            ]),
            'medical_college' => $this->whenLoaded('medicalCollege', fn () => $this->medicalCollege ? [
                'id' => $this->medicalCollege->id,
                'name' => $this->medicalCollege->name,
            ] : null),
            'enrollments' => $this->whenLoaded('enrollments', fn () => $this->enrollments->map(fn ($enrollment) => [
                'enrollment_code' => $enrollment->enrollment_code,
                'enrollment_date' => $enrollment->enrollment_date?->toDateString(),
                'starts_on' => $enrollment->starts_on?->toDateString(),
                'expected_completion_on' => $enrollment->expected_completion_on?->toDateString(),
                'completed_on' => $enrollment->completed_on?->toDateString(),
                'status' => $enrollment->status,
                'is_active' => $enrollment->is_active,
                'course' => $enrollment->course ? [
                    'id' => $enrollment->course->id,
                    'code' => $enrollment->course->code,
                    'name' => $enrollment->course->name,
                ] : null,
                'branch' => $enrollment->branch ? [
                    'id' => $enrollment->branch->id,
                    'name' => $enrollment->branch->name,
                ] : null,
                'batch' => $enrollment->batch ? [
                    'id' => $enrollment->batch->id,
                    'name' => $enrollment->batch->name,
                ] : null,
                'tuition' => $enrollment->tuition ? [
                    'tuition_number' => $enrollment->tuition->tuition_number,
                    'original_amount' => $enrollment->tuition->original_amount,
                    'waiver_amount' => $enrollment->tuition->waiver_amount,
                    'issued_on' => $enrollment->tuition->issued_on?->toDateString(),
                    'due_on' => $enrollment->tuition->due_on?->toDateString(),
                    'status' => $enrollment->tuition->status,
                    'payments' => $enrollment->tuition->payments->map(fn ($payment) => [
                        'payment_number' => $payment->payment_number,
                        'amount' => $payment->amount,
                        'paid_at' => $payment->paid_at?->toIso8601String(),
                        'status' => $payment->status,
                        'transaction_reference' => $payment->transaction_reference,
                        'payment_method' => $payment->paymentMethod?->name,
                    ])->values(),
                ] : null,
            ])),
            'other_expenses' => $this->whenLoaded('expenses', fn () => $this->expenses->map(fn ($expense) => [
                'expense_number' => $expense->expense_number,
                'description' => $expense->description,
                'quantity' => $expense->quantity,
                'unit_amount' => $expense->unit_amount,
                'waiver_amount' => $expense->waiver_amount,
                'issued_on' => $expense->issued_on?->toDateString(),
                'due_on' => $expense->due_on?->toDateString(),
                'status' => $expense->status,
                'expense_type' => $expense->expenseType?->name,
                'branch' => $expense->branch?->name,
                'payments' => $expense->payments->map(fn ($payment) => [
                    'payment_number' => $payment->payment_number,
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'status' => $payment->status,
                    'transaction_reference' => $payment->transaction_reference,
                    'payment_method' => $payment->paymentMethod?->name,
                ])->values(),
            ])->values()),
        ];
    }
}
