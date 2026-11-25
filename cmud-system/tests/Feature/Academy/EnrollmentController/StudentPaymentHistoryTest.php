<?php

namespace Tests\Feature\Academy\EnrollmentController;

use Tests\Support\Academy\EnrollmentController\StudentOverviewTestCase;

class StudentPaymentHistoryTest extends StudentOverviewTestCase
{
    public function test_student_payment_history_combines_sources_without_duplicates_and_supports_filters(): void
    {
        $this->actingAs($this->user);
        $firstCourse = $this->createCourse('PAYMENT-A');
        $firstBatch = $this->createBatch($firstCourse, 'PAYMENT-A');
        $firstEnrollment = $this->createEnrollment($firstCourse, $firstBatch, 'PAYMENT-A');
        $firstTuition = $this->createTuition($firstEnrollment, 'PAYMENT-A');
        $tuitionPayment = $this->createTuitionPayment(
            $firstTuition,
            'PAYMENT-A-1',
            '1000.00',
            'confirmed',
            '2025-03-01 10:00:00',
            'receipts/tuition-payment.pdf',
        );
        $this->createTuitionPayment(
            $firstTuition,
            'PAYMENT-A-PENDING',
            '500.00',
            'pending',
            '2025-02-28 10:00:00',
        );

        $expense = $this->createStudentExpense('PAYMENT-A', $firstEnrollment);
        $expensePayment = $this->createExpensePayment(
            $expense,
            'PAYMENT-A-1',
            '250.00',
            'confirmed',
            '2025-03-02 10:00:00',
            'receipts/expense-payment.pdf',
            'EXPENSE-TRANSACTION-01',
        );

        $secondCourse = $this->createCourse('PAYMENT-B');
        $secondBatch = $this->createBatch($secondCourse, 'PAYMENT-B');
        $secondEnrollment = $this->createEnrollment($secondCourse, $secondBatch, 'PAYMENT-B');
        $secondTuition = $this->createTuition($secondEnrollment, 'PAYMENT-B');
        $this->createTuitionPayment(
            $secondTuition,
            'PAYMENT-B-1',
            '2000.00',
            'confirmed',
            '2025-02-27 10:00:00',
        );

        $this->getJson('/api/v1/students/'.$this->student->student_code.'/payments')
            ->assertOk()
            ->assertJsonPath('total', 4)
            ->assertJsonPath('data.0.payment_key', 'expense-'.$expensePayment->id)
            ->assertJsonPath('data.0.source', 'expense')
            ->assertJsonPath('data.0.transaction_reference', 'EXPENSE-TRANSACTION-01')
            ->assertJsonPath('data.1.payment_key', 'tuition-'.$tuitionPayment->id)
            ->assertJsonPath('data.1.receipt.path', 'receipts/tuition-payment.pdf')
            ->assertJsonPath('data.2.is_valid', false);

        $this->getJson('/api/v1/students/'.$this->student->student_code.'/payments?course_id='.$firstCourse->id.'&payment_method_id='.$this->paymentMethod->id.'&date_from=2025-03-01&date_to=2025-03-31')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->getJson('/api/v1/students/'.$this->student->student_code.'/payments/summary')
            ->assertOk()
            ->assertJsonPath('data.valid_payment_count', 3)
            ->assertJsonPath('data.valid_amount', '3250.00');

        $this->getJson('/api/v1/student-payments/tuition-'.$tuitionPayment->id)
            ->assertOk()
            ->assertJsonPath('data.payment_number', $tuitionPayment->payment_number);

        $this->getJson('/api/v1/student-payments/expense-'.$expensePayment->id)
            ->assertOk()
            ->assertJsonPath('data.payment_number', $expensePayment->payment_number);
    }

    public function test_course_filter_matches_expenses_attached_through_stage_records(): void
    {
        $this->actingAs($this->user);
        $course = $this->createCourse('PAYMENT-STAGE-FILTER');
        $batch = $this->createBatch($course, 'PAYMENT-STAGE-FILTER');
        $enrollment = $this->createEnrollment($course, $batch, 'PAYMENT-STAGE-FILTER');
        $stage = $this->createCourseStage($course, 'PAYMENT-STAGE-FILTER', 1);
        $stageRecord = $this->createStageRecord($enrollment, $stage, $batch, 'Stage expense', 'ongoing');
        $expense = $this->createStudentExpense('PAYMENT-STAGE-FILTER', $enrollment);
        $expense->update([
            'enrollment_id' => null,
            'student_stage_record_id' => $stageRecord->id,
        ]);
        $payment = $this->createExpensePayment($expense, 'PAYMENT-STAGE-FILTER', '300.00');

        $this->getJson('/api/v1/students/'.$this->student->student_code.'/payments?course_id='.$course->id)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.payment_key', 'expense-'.$payment->id)
            ->assertJsonPath('data.0.enrollment_code', $enrollment->enrollment_code)
            ->assertJsonPath('data.0.course_id', $course->id);
    }
}
