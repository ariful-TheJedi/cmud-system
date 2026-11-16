<?php

namespace Tests\Unit\Finance;

use App\Models\Finance\FinancialAccount;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\StudentExpense;
use App\Models\Finance\StudentExpensePayment;
use App\Models\Security\User;
use Tests\TestCase;

class StudentExpensePaymentMappingTest extends TestCase
{
    public function test_payment_model_uses_the_renamed_table(): void
    {
        $this->assertSame('student_other_payments', (new StudentExpensePayment())->getTable());
    }

    public function test_expense_payment_relationships_use_the_renamed_table_and_foreign_keys(): void
    {
        $this->assertPaymentRelation((new StudentExpense())->payments(), 'student_expense_id');
        $this->assertPaymentRelation((new PaymentMethod())->studentExpensePayments(), 'payment_method_id');
        $this->assertPaymentRelation((new FinancialAccount())->studentExpensePayments(), 'financial_account_id');
        $this->assertPaymentRelation((new User())->recordedStudentExpensePayments(), 'recorded_by');
        $this->assertPaymentRelation((new User())->verifiedStudentExpensePayments(), 'verified_by');
    }

    public function test_payment_belongs_to_relationships_keep_their_parent_tables_and_foreign_keys(): void
    {
        $payment = new StudentExpensePayment();

        $this->assertBelongsTo($payment->expense(), 'student_expenses', 'student_expense_id');
        $this->assertBelongsTo($payment->paymentMethod(), 'payment_methods', 'payment_method_id');
        $this->assertBelongsTo($payment->financialAccount(), 'financial_accounts', 'financial_account_id');
        $this->assertBelongsTo($payment->recorder(), 'users', 'recorded_by');
        $this->assertBelongsTo($payment->verifier(), 'users', 'verified_by');
    }

    private function assertPaymentRelation($relation, string $foreignKey): void
    {
        $this->assertSame('student_other_payments', $relation->getRelated()->getTable());
        $this->assertSame($foreignKey, $relation->getForeignKeyName());
    }

    private function assertBelongsTo($relation, string $parentTable, string $foreignKey): void
    {
        $this->assertSame($parentTable, $relation->getRelated()->getTable());
        $this->assertSame($foreignKey, $relation->getForeignKeyName());
    }
}
