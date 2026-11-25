<?php

namespace Tests\Feature\Academy\EnrollmentController;

use Tests\Support\Academy\EnrollmentController\StudentOverviewTestCase;

class EnrollmentFinancialSummaryTest extends StudentOverviewTestCase
{
    public function test_financial_summary_uses_only_approved_waivers_and_confirmed_payments(): void
    {
        $this->actingAs($this->user);
        $course = $this->createCourse('FINANCIAL-A');
        $batch = $this->createBatch($course, 'FINANCIAL-A');
        $enrollment = $this->createEnrollment($course, $batch, 'FINANCIAL-A');
        $tuition = $this->createTuition($enrollment, 'FINANCIAL-A', '10000.00', '1000.00', true);
        $this->createTuitionPayment($tuition, 'FINANCIAL-A-1', '2000.00');
        $this->createTuitionPayment($tuition, 'FINANCIAL-A-2', '3000.00');
        $this->createTuitionPayment($tuition, 'FINANCIAL-A-PENDING', '4000.00', 'pending');

        $this->getJson('/api/v1/enrollments/'.$enrollment->id.'/financial-summary')
            ->assertOk()
            ->assertJsonPath('data.total_course_fee', '10000.00')
            ->assertJsonPath('data.approved_waiver', '1000.00')
            ->assertJsonPath('data.payable_fee', '9000.00')
            ->assertJsonPath('data.total_paid', '5000.00')
            ->assertJsonPath('data.outstanding_due', '4000.00')
            ->assertJsonPath('data.financial_status', 'partial')
            ->assertJsonCount(3, 'data.bill.payments');
    }

    public function test_student_financial_summary_is_separate_per_enrollment_and_marks_overdue_bills(): void
    {
        $this->actingAs($this->user);
        $firstCourse = $this->createCourse('FINANCIAL-STUDENT-A');
        $firstBatch = $this->createBatch($firstCourse, 'FINANCIAL-STUDENT-A');
        $firstEnrollment = $this->createEnrollment($firstCourse, $firstBatch, 'FINANCIAL-STUDENT-A');
        $this->createTuition($firstEnrollment, 'FINANCIAL-STUDENT-A', '10000.00', '2000.00', false, '2020-01-01');

        $secondCourse = $this->createCourse('FINANCIAL-STUDENT-B');
        $secondBatch = $this->createBatch($secondCourse, 'FINANCIAL-STUDENT-B');
        $secondEnrollment = $this->createEnrollment($secondCourse, $secondBatch, 'FINANCIAL-STUDENT-B', 'completed');
        $secondTuition = $this->createTuition($secondEnrollment, 'FINANCIAL-STUDENT-B', '5000.00');
        $this->createTuitionPayment($secondTuition, 'FINANCIAL-STUDENT-B-1', '5000.00');

        $this->getJson('/api/v1/students/'.$this->student->student_code.'/financial-summary')
            ->assertOk()
            ->assertJsonCount(2, 'data.enrollments')
            ->assertJsonPath('data.enrollments.0.enrollment_code', $secondEnrollment->enrollment_code)
            ->assertJsonPath('data.enrollments.0.financial_status', 'paid')
            ->assertJsonPath('data.enrollments.1.enrollment_code', $firstEnrollment->enrollment_code)
            ->assertJsonPath('data.enrollments.1.approved_waiver', '0.00')
            ->assertJsonPath('data.enrollments.1.financial_status', 'overdue');
    }

    public function test_enrollment_summary_includes_stage_expenses_in_payable_balance_and_due_status(): void
    {
        $this->actingAs($this->user);
        $course = $this->createCourse('FINANCIAL-EXPENSE');
        $batch = $this->createBatch($course, 'FINANCIAL-EXPENSE');
        $enrollment = $this->createEnrollment($course, $batch, 'FINANCIAL-EXPENSE');
        $tuition = $this->createTuition($enrollment, 'FINANCIAL-EXPENSE', '1000.00', '100.00', true);
        $this->createTuitionPayment($tuition, 'FINANCIAL-EXPENSE-TUITION', '900.00');
        $expense = $this->createStudentExpense('FINANCIAL-EXPENSE', $enrollment);
        $expense->update([
            'waiver_amount' => '50.00',
            'waiver_approved_by' => $this->user->id,
            'waiver_approved_at' => '2025-01-03 00:00:00',
            'due_on' => '2020-01-01',
        ]);
        $this->createExpensePayment(
            $expense,
            'FINANCIAL-EXPENSE-PAYMENT',
            '200.00',
            'confirmed',
            '2025-03-02 10:00:00',
            null,
            'EXPENSE-TRANSACTION-01',
        );

        $this->getJson('/api/v1/enrollments/'.$enrollment->id.'/financial-summary')
            ->assertOk()
            ->assertJsonPath('data.total_course_fee', '1000.00')
            ->assertJsonPath('data.additional_fees', '500.00')
            ->assertJsonPath('data.approved_waiver', '150.00')
            ->assertJsonPath('data.payable_fee', '1350.00')
            ->assertJsonPath('data.total_paid', '1100.00')
            ->assertJsonPath('data.outstanding_due', '250.00')
            ->assertJsonPath('data.financial_status', 'overdue')
            ->assertJsonPath('data.other_bills.0.payable_amount', '450.00')
            ->assertJsonPath('data.other_bills.0.outstanding_due', '250.00')
            ->assertJsonPath('data.other_bills.0.payments.0.transaction_reference', 'EXPENSE-TRANSACTION-01');
    }
}
