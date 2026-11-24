<?php

namespace Database\Seeders\Development;

use Illuminate\Database\Seeder;

class CoreFinanceSeeder extends Seeder
{
    use UsesCoreSeedData;

    public function run(): void
    {
        $instituteId = $this->findId('institutes', ['code' => 'CMUD-DEV-CORE']);
        $branchId = $this->findId('branches', ['institute_id' => $instituteId, 'code' => 'DEV-BR-DHAKA']);
        $departmentId = $this->findId('departments', ['institute_id' => $instituteId, 'code' => 'DEV-DEPT-OPERATIONS']);
        $operatorId = $this->findId('users', ['email' => 'core.operator@example.invalid']);
        $studentId = $this->findId('students', ['student_code' => 'DEV-CORE-STU-002']);
        $enrollmentId = $this->findId('enrollments', ['enrollment_code' => 'DEV-CORE-ENR-002']);

        $paymentMethods = [];
        foreach ([
            ['slug' => 'cash', 'name' => 'Cash', 'reference' => false, 'phone' => false],
            ['slug' => 'bank_transfer', 'name' => 'Bank Transfer', 'reference' => true, 'phone' => false],
            ['slug' => 'mobile_banking', 'name' => 'Mobile Banking', 'reference' => true, 'phone' => true],
        ] as $method) {
            $code = $instituteId.'_'.$method['slug'];
            $saved = $this->saveRow('payment_methods', ['code' => $code], [
                'institute_id' => $instituteId,
                'name' => $method['name'],
                'requires_transaction_reference' => $method['reference'],
                'requires_sender_phone' => $method['phone'],
                'is_active' => true,
                'notes' => 'Development reference method; no payment provider is connected.',
            ]);
            $paymentMethods[$method['slug']] = (int) $saved->id;
        }

        $accounts = [];
        foreach ([
            ['code' => 'DEVCORE-CASH-001', 'name' => 'Core Demo Cash Account', 'type' => 'cash', 'provider' => null, 'number' => null, 'opening' => '100000.00'],
            ['code' => 'DEVCORE-BANK-001', 'name' => 'Core Demo Bank Account', 'type' => 'bank', 'provider' => 'Fictional Demo Bank', 'number' => 'DEV-ACCOUNT-0001', 'opening' => '50000.00'],
        ] as $account) {
            $saved = $this->saveRow('financial_accounts', ['code' => $account['code']], [
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'name' => $account['name'],
                'account_type' => $account['type'],
                'provider_name' => $account['provider'],
                'account_holder_name' => 'CMUD Core Development Institute',
                'account_number' => $account['number'],
                'bank_branch_name' => $account['type'] === 'bank' ? 'Demo Branch' : null,
                'routing_number' => null,
                'currency_code' => 'BDT',
                'opening_balance' => $account['opening'],
                'opening_balance_as_of' => '2025-01-01 00:00:00',
                'is_active' => true,
                'notes' => 'Fictional development account; no real account details.',
            ]);
            $accounts[$account['code']] = (int) $saved->id;
        }

        $tuitionScenarios = [
            ['key' => 'FIN-001', 'enrollment' => 'DEV-CORE-ENR-002', 'course' => 'DEV-COURSE-EMT', 'version' => 1, 'number' => 'DEVCORE-TUI-001', 'amount' => '40000.00', 'waiver' => '0.00', 'due' => '2025-12-15', 'payments' => [['DEVCORE-TUIP-001-A', '15000.00'], ['DEVCORE-TUIP-001-B', '25000.00']]],
            ['key' => 'FIN-002', 'enrollment' => 'DEV-CORE-ENR-003-A', 'course' => 'DEV-COURSE-BLS', 'version' => 1, 'number' => 'DEVCORE-TUI-002', 'amount' => '50000.00', 'waiver' => '5000.00', 'due' => '2025-12-15', 'payments' => [['DEVCORE-TUIP-002-A', '12000.00'], ['DEVCORE-TUIP-002-B', '8000.00']]],
            ['key' => 'FIN-003', 'enrollment' => 'DEV-CORE-ENR-004', 'course' => 'DEV-COURSE-DMU', 'version' => 1, 'number' => 'DEVCORE-TUI-003', 'amount' => '60000.00', 'waiver' => '10000.00', 'due' => '2026-07-15', 'payments' => [['DEVCORE-TUIP-003-A', '10000.00'], ['DEVCORE-TUIP-003-B', '15000.00'], ['DEVCORE-TUIP-003-C', '5000.00']]],
            ['key' => 'FIN-004', 'enrollment' => 'DEV-CORE-ENR-006', 'course' => 'DEV-COURSE-REFRESH', 'version' => 1, 'number' => 'DEVCORE-TUI-004', 'amount' => '35000.00', 'waiver' => '5000.00', 'due' => '2025-11-15', 'payments' => []],
        ];

        foreach ($tuitionScenarios as $scenario) {
            $courseId = $this->findId('courses', ['institute_id' => $instituteId, 'code' => $scenario['course']]);
            $priceVersionId = $this->findId('course_price_versions', [
                'course_id' => $courseId,
                'branch_id' => null,
                'version_number' => $scenario['version'],
            ]);
            $tuition = $this->saveRow('student_tuitions', [
                'enrollment_id' => $this->findId('enrollments', ['enrollment_code' => $scenario['enrollment']]),
            ], [
                'course_price_version_id' => $priceVersionId,
                'tuition_number' => $scenario['number'],
                'original_amount' => $scenario['amount'],
                'waiver_amount' => $scenario['waiver'],
                'waiver_reason' => $scenario['waiver'] !== '0.00' ? 'Fictional core scenario waiver.' : null,
                'waiver_approved_by' => $scenario['waiver'] !== '0.00' ? $operatorId : null,
                'waiver_approved_at' => $scenario['waiver'] !== '0.00' ? self::SEEDED_AT : null,
                'issued_on' => '2025-01-15',
                'due_on' => $scenario['due'],
                'status' => 'issued',
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'notes' => $this->scenarioNote('SD-'.$scenario['key'], 'Amounts are source records; balance behavior is pending.'),
            ]);

            foreach ($scenario['payments'] as $index => [$paymentNumber, $amount]) {
                $this->saveRow('student_tuition_payments', ['payment_number' => $paymentNumber], [
                    'student_tuition_id' => $tuition->id,
                    'payment_method_id' => $paymentMethods['cash'],
                    'financial_account_id' => $accounts['DEVCORE-CASH-001'],
                    'amount' => $amount,
                    'paid_at' => '2025-02-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).' 10:00:00',
                    'transaction_reference' => null,
                    'payer_phone' => '+880000000101',
                    'proof_file_path' => null,
                    'status' => 'confirmed',
                    'verification_method' => 'manual',
                    'verified_at' => self::SEEDED_AT,
                    'verified_by' => $operatorId,
                    'verification_reference' => null,
                    'verification_response' => null,
                    'rejection_reason' => null,
                    'recorded_by' => $operatorId,
                    'notes' => $this->scenarioNote('SD-'.$scenario['key'], 'Fictional tuition payment source row.'),
                ]);
            }
        }

        $studentExpenseType = $this->saveRow('student_expense_types', [
            'institute_id' => $instituteId,
            'code' => 'DEV-STUDENT-OTHER-001',
        ], [
            'name' => 'Demo Student Materials Charge',
            'description' => 'Fictional non-tuition student charge.',
            'is_active' => true,
        ]);
        $studentExpensePrice = $this->saveRow('student_expense_price_versions', [
            'student_expense_type_id' => $studentExpenseType->id,
            'branch_id' => $branchId,
            'course_id' => null,
            'course_stage_id' => null,
            'version_number' => 1,
        ], [
            'amount' => '2000.00',
            'effective_from' => '2025-01-01',
            'effective_to' => null,
            'status' => 'approved',
            'approved_by' => $operatorId,
            'approved_at' => self::SEEDED_AT,
            'notes' => $this->scenarioNote('SD-FIN-005', 'Fictional charge price; calculation workflow is pending.'),
        ]);
        $studentExpense = $this->saveRow('student_expenses', ['expense_number' => 'DEVCORE-SEXP-001'], [
            'student_id' => $studentId,
            'enrollment_id' => $enrollmentId,
            'student_stage_record_id' => null,
            'branch_id' => $branchId,
            'student_expense_type_id' => $studentExpenseType->id,
            'student_expense_price_version_id' => $studentExpensePrice->id,
            'description' => 'Fictional course materials charge',
            'quantity' => '1.000',
            'unit_amount' => '2000.00',
            'waiver_amount' => '0.00',
            'waiver_reason' => null,
            'waiver_approved_by' => null,
            'waiver_approved_at' => null,
            'issued_on' => '2025-02-01',
            'due_on' => '2025-02-15',
            'status' => 'issued',
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'recorded_by' => $operatorId,
            'notes' => $this->scenarioNote('SD-FIN-005', 'Source charge amount; outstanding calculation is pending.'),
        ]);
        $this->saveRow('student_other_payments', ['payment_number' => 'DEVCORE-SEXP-PAY-001'], [
            'student_expense_id' => $studentExpense->id,
            'payment_method_id' => $paymentMethods['cash'],
            'financial_account_id' => $accounts['DEVCORE-CASH-001'],
            'amount' => '500.00',
            'paid_at' => '2025-02-05 11:00:00',
            'transaction_reference' => null,
            'payer_phone' => '+880000000101',
            'proof_file_path' => null,
            'status' => 'confirmed',
            'verification_method' => 'manual',
            'verified_at' => self::SEEDED_AT,
            'verified_by' => $operatorId,
            'verification_reference' => null,
            'verification_response' => null,
            'rejection_reason' => null,
            'recorded_by' => $operatorId,
            'notes' => $this->scenarioNote('SD-FIN-005', 'Fictional student charge payment row.'),
        ]);

        $expenseCategory = $this->saveRow('institute_expense_types', [
            'institute_id' => $instituteId,
            'code' => 'DEV-INST-EXP-CAT-001',
        ], [
            'name' => 'Demo Facility Costs',
            'description' => 'Fictional institute expense category.',
            'is_active' => true,
        ]);
        $expenseType = $this->saveRow('institute_expense_categories', [
            'institute_expense_type_id' => $expenseCategory->id,
            'code' => 'DEV-INST-EXP-TYPE-001',
        ], [
            'name' => 'Demo Classroom Maintenance',
            'description' => 'Fictional institute expense type.',
            'is_active' => true,
        ]);
        $expensePrice = $this->saveRow('institute_expense_price_versions', [
            'institute_expense_category_id' => $expenseType->id,
            'branch_id' => $branchId,
            'version_number' => 1,
        ], [
            'amount' => '12000.00',
            'unit_name' => 'service',
            'effective_from' => '2025-01-01',
            'effective_to' => null,
            'status' => 'approved',
            'approved_by' => $operatorId,
            'approved_at' => self::SEEDED_AT,
            'notes' => $this->scenarioNote('SD-FIN-006', 'Fictional expense price source row.'),
        ]);
        $instituteExpense = $this->saveRow('institute_expenses', ['expense_number' => 'DEVCORE-IEXP-001'], [
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'institute_expense_type_id' => $expenseCategory->id,
            'institute_expense_category_id' => $expenseType->id,
            'institute_expense_price_version_id' => $expensePrice->id,
            'stuff_id' => $this->findId('stuff', ['stuff_code' => 'DEV-STUFF-002']),
            'teacher_id' => $this->findId('teachers', ['teacher_code' => 'DEV-TEACHER-001']),
            'asset_id' => null,
            'payee_name' => 'Demo Facilities Supplier',
            'invoice_number' => 'DEVCORE-INVOICE-001',
            'description' => 'Fictional classroom maintenance service',
            'quantity' => '1.000',
            'unit_amount' => '12000.00',
            'expense_date' => '2025-03-01',
            'service_period_from' => null,
            'service_period_to' => null,
            'due_on' => '2025-03-15',
            'bill_file_path' => null,
            'status' => 'approved',
            'approved_by' => $operatorId,
            'approved_at' => self::SEEDED_AT,
            'rejection_reason' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'recorded_by' => $operatorId,
            'notes' => $this->scenarioNote('SD-FIN-006', 'Fictional institute expense; approval workflow is pending.'),
        ]);
        $this->saveRow('institute_expense_payments', ['payment_number' => 'DEVCORE-IEXP-PAY-001'], [
            'institute_expense_id' => $instituteExpense->id,
            'payment_method_id' => $paymentMethods['cash'],
            'financial_account_id' => $accounts['DEVCORE-CASH-001'],
            'amount' => '12000.00',
            'paid_at' => '2025-03-02 11:00:00',
            'transaction_reference' => null,
            'recipient_account_reference' => null,
            'proof_file_path' => null,
            'status' => 'confirmed',
            'verification_method' => 'manual',
            'verified_by' => $operatorId,
            'verified_at' => self::SEEDED_AT,
            'verification_reference' => null,
            'verification_response' => null,
            'rejection_reason' => null,
            'recorded_by' => $operatorId,
            'notes' => $this->scenarioNote('SD-FIN-006', 'Fictional expense payment source row.'),
        ]);

        $this->saveRow('account_transfers', ['transfer_number' => 'DEVCORE-TRANSFER-001'], [
            'from_financial_account_id' => $accounts['DEVCORE-CASH-001'],
            'to_financial_account_id' => $accounts['DEVCORE-BANK-001'],
            'amount' => '5000.00',
            'transferred_at' => '2025-04-01 10:00:00',
            'transaction_reference' => null,
            'proof_file_path' => null,
            'status' => 'confirmed',
            'approved_by' => $operatorId,
            'approved_at' => self::SEEDED_AT,
            'confirmed_by' => $operatorId,
            'confirmed_at' => self::SEEDED_AT,
            'rejection_reason' => null,
            'recorded_by' => $operatorId,
            'notes' => $this->scenarioNote('SD-FIN-007', 'Fictional transfer row; ledger posting is pending.'),
        ]);

        $incomeBill = $this->saveRow('institute_income_bill', ['bill_number' => 'DEVCORE-INCOME-001'], [
            'university_name' => 'Central Demo Medical College',
            'bill_items' => json_encode([[
                'description' => 'Fictional examination service',
                'exam_date' => '2025-04-15',
                'quantity' => 1,
                'unit_price' => '3000.00',
                'line_total' => '3000.00',
            ]], JSON_THROW_ON_ERROR),
            'total_amount' => '3000.00',
            'bill_date' => '2025-04-01',
            'due_on' => '2025-04-30',
            'status' => 'approved',
            'approved_by' => $operatorId,
            'approved_at' => self::SEEDED_AT,
            'rejection_reason' => null,
            'recorded_by' => $operatorId,
            'notes' => $this->scenarioNote('SD-FIN-008', 'Fictional bill; bill-item validation is pending.'),
        ]);
        $this->saveRow('institute_other_income_bill_payment', ['payment_number' => 'DEVCORE-INCOME-PAY-001'], [
            'institute_income_bill_id' => $incomeBill->id,
            'payment_method_id' => $paymentMethods['cash'],
            'financial_account_id' => $accounts['DEVCORE-BANK-001'],
            'amount' => '3000.00',
            'status' => 'paid',
            'paid_at' => '2025-04-10 10:00:00',
            'transaction_reference' => null,
            'proof_file_path' => null,
            'received_by' => $operatorId,
            'recorded_by' => $operatorId,
            'verified_by' => $operatorId,
            'verified_at' => self::SEEDED_AT,
            'notes' => $this->scenarioNote('SD-FIN-008', 'Fictional received-payment source row.'),
        ]);
    }
}
