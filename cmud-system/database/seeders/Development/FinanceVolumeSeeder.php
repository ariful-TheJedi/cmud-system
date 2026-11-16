<?php

namespace Database\Seeders\Development;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class FinanceVolumeSeeder extends Seeder
{
    use UsesCoreSeedData;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Finance volume data may only be seeded in local or testing environments.');
        }

        DB::transaction(function (): void {
            $instituteId = $this->findId('institutes', ['code' => 'CMUD-DEV-CORE']);
            $branchId = $this->findId('branches', ['institute_id' => $instituteId, 'code' => 'DEV-BR-SYLHET']);
            $this->seedAdditionalAccount($instituteId, $branchId);
            $operatorId = $this->findId('users', ['email' => 'core.operator@example.invalid']);
            $cashMethodId = $this->findId('payment_methods', ['code' => $instituteId.'_cash']);
            $cashAccountId = $this->findId('financial_accounts', ['code' => 'DEVCORE-CASH-001']);
            $bankAccountId = $this->findId('financial_accounts', ['code' => 'DEVCORE-BANK-001']);
            $reserveAccountId = $this->findId('financial_accounts', ['code' => 'DEVCORE-RESERVE-001']);

            $this->seedTuitionVolume($instituteId, $operatorId, $cashMethodId, $cashAccountId);
            $this->seedStudentExpenses($instituteId, $operatorId, $cashMethodId, $cashAccountId);
            $this->seedInstituteExpenses($instituteId, $operatorId, $cashMethodId, $cashAccountId);
            $this->seedTransfers($operatorId, $cashAccountId, $bankAccountId);
            $this->seedIncomeBills($operatorId, $cashMethodId, $bankAccountId, $reserveAccountId);
        });
    }

    private function seedAdditionalAccount(int $instituteId, int $branchId): void
    {
        $this->saveRow('financial_accounts', ['code' => 'DEVCORE-RESERVE-001'], [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'name' => 'Core Demo Reserve Account',
            'account_type' => 'bank',
            'provider_name' => 'Fictional Development Cooperative',
            'account_holder_name' => 'CMUD Core Development Institute',
            'account_number' => 'DEV-ACCOUNT-0003',
            'bank_branch_name' => 'Fictional Sylhet Branch',
            'routing_number' => null,
            'currency_code' => 'BDT',
            'opening_balance' => '25000.00',
            'opening_balance_as_of' => '2025-01-01 00:00:00',
            'is_active' => true,
            'notes' => 'Fictional development account with no real bank credentials.',
        ]);
    }

    private function seedTuitionVolume(int $instituteId, int $operatorId, int $methodId, int $accountId): void
    {
        $enrollments = [];
        for ($student = 7; $student <= 200; $student++) {
            $enrollments[] = 'DEV-ACA-ENR-'.str_pad((string) $student, 3, '0', STR_PAD_LEFT).'-1';
        }
        $enrollments[] = 'DEV-ACA-ENR-007-2';
        $enrollments[] = 'DEV-ACA-ENR-008-2';

        $paymentIndex = 1;
        foreach ($enrollments as $index => $enrollmentCode) {
            $enrollment = DB::table('enrollments')
                ->join('courses', 'courses.id', '=', 'enrollments.course_id')
                ->where('courses.institute_id', $instituteId)
                ->where('enrollments.enrollment_code', $enrollmentCode)
                ->select('enrollments.*')
                ->first();
            if ($enrollment === null) {
                throw new RuntimeException("Missing Academy volume enrollment [{$enrollmentCode}].");
            }

            $price = $this->coursePriceForEnrollment($enrollment->course_id, $enrollment->branch_id, $enrollment->enrollment_date);
            $tuitionNumber = 'DEVCORE-TUI-VOL-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $tuition = $this->saveRow('student_tuitions', ['enrollment_id' => $enrollment->id], [
                'course_price_version_id' => $price->id,
                'tuition_number' => $tuitionNumber,
                'original_amount' => $price->amount,
                'waiver_amount' => '0.00',
                'waiver_reason' => null,
                'waiver_approved_by' => null,
                'waiver_approved_at' => null,
                'issued_on' => '2025-01-15',
                'due_on' => '2025-12-15',
                'status' => 'issued',
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'notes' => $this->scenarioNote('FIN-VOL-TUITION-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'Tuition source amount matches its course price version; payment math is derived from integer cents.'),
            ]);

            $amountCents = $this->toCents((string) $price->amount);
            if ($index < 97) {
                $firstCents = intdiv($amountCents, 2);
                foreach ([$firstCents, $amountCents - $firstCents] as $installment => $paidCents) {
                    $this->writeTuitionPayment($tuition, $paymentIndex++, $paidCents, $methodId, $accountId, $operatorId, $installment + 1);
                }
            } else {
                $this->writeTuitionPayment($tuition, $paymentIndex++, intdiv($amountCents * 60, 100), $methodId, $accountId, $operatorId, 1);
            }
        }
    }

    private function coursePriceForEnrollment(int $courseId, int $branchId, string $enrollmentDate): object
    {
        $effective = fn ($query) => $query
            ->whereDate('effective_from', '<=', $enrollmentDate)
            ->where(function ($query) use ($enrollmentDate): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $enrollmentDate);
            })
            ->orderByDesc('version_number');

        $price = $effective(DB::table('course_price_versions')->where('course_id', $courseId)->where('branch_id', $branchId))->first();
        $price ??= $effective(DB::table('course_price_versions')->where('course_id', $courseId)->whereNull('branch_id'))->first();

        if ($price === null) {
            throw new RuntimeException("No effective course price for course [{$courseId}] and enrollment date [{$enrollmentDate}].");
        }

        return $price;
    }

    private function writeTuitionPayment(object $tuition, int $paymentIndex, int $paidCents, int $methodId, int $accountId, int $operatorId, int $installment): void
    {
        $number = 'DEVCORE-TUIP-VOL-'.str_pad((string) $paymentIndex, 3, '0', STR_PAD_LEFT);
        $this->saveRow('student_tuition_payments', ['payment_number' => $number], [
            'student_tuition_id' => $tuition->id,
            'payment_method_id' => $methodId,
            'financial_account_id' => $accountId,
            'amount' => $this->fromCents($paidCents),
            'paid_at' => sprintf('2025-02-%02d 10:00:00', 1 + (($paymentIndex - 1) % 27)),
            'transaction_reference' => null,
            'payer_phone' => null,
            'proof_file_path' => null,
            'status' => 'confirmed',
            'verification_method' => 'manual',
            'verified_at' => self::SEEDED_AT,
            'verified_by' => $operatorId,
            'verification_reference' => null,
            'verification_response' => null,
            'rejection_reason' => null,
            'recorded_by' => $operatorId,
            'notes' => $this->scenarioNote('FIN-VOL-PAYMENT-'.$paymentIndex, "Fictional cash installment {$installment}; confirmed sum does not exceed tuition."),
        ]);
    }

    private function seedStudentExpenses(int $instituteId, int $operatorId, int $methodId, int $accountId): void
    {
        $branchIds = [];
        foreach (['DEV-BR-DHAKA', 'DEV-BR-CHITTAGONG', 'DEV-BR-SYLHET'] as $code) {
            $branchIds[$code] = $this->findId('branches', ['institute_id' => $instituteId, 'code' => $code]);
        }
        $typeSpecs = [
            ['code' => 'DEV-ACADEMIC-EXP-01', 'name' => 'Development Lab Materials', 'amount' => '1800.00'],
            ['code' => 'DEV-ACADEMIC-EXP-02', 'name' => 'Development Assessment Materials', 'amount' => '1200.00'],
            ['code' => 'DEV-ACADEMIC-EXP-03', 'name' => 'Development Student File Pack', 'amount' => '650.00'],
        ];
        $types = [];
        foreach ($typeSpecs as $spec) {
            $saved = $this->saveRow('student_expense_types', ['institute_id' => $instituteId, 'code' => $spec['code']], [
                'name' => $spec['name'],
                'description' => 'Fictional non-tuition student charge for development data.',
                'is_active' => true,
            ]);
            $types[] = ['id' => (int) $saved->id, 'amount' => $spec['amount'], 'code' => $spec['code'], 'name' => $spec['name']];
        }

        $priceIds = [];
        foreach ($types as $type) {
            foreach ($branchIds as $branchCode => $branchId) {
                $price = $this->saveRow('student_expense_price_versions', [
                    'student_expense_type_id' => $type['id'],
                    'branch_id' => $branchId,
                    'course_id' => null,
                    'course_stage_id' => null,
                    'version_number' => 1,
                ], [
                    'amount' => $type['amount'],
                    'effective_from' => '2025-01-01',
                    'effective_to' => null,
                    'status' => 'approved',
                    'approved_by' => $operatorId,
                    'approved_at' => self::SEEDED_AT,
                    'notes' => 'Fictional charge price; assessment and payment rules remain pending.',
                ]);
                $priceIds[$type['code'].':'.$branchCode] = (int) $price->id;
            }
        }

        for ($index = 1; $index <= 49; $index++) {
            $studentNumber = $index + 6;
            $enrollmentCode = 'DEV-ACA-ENR-'.str_pad((string) $studentNumber, 3, '0', STR_PAD_LEFT).'-1';
            $enrollment = DB::table('enrollments')->where('enrollment_code', $enrollmentCode)->first();
            if ($enrollment === null) {
                throw new RuntimeException("Missing Academy enrollment [{$enrollmentCode}] for student charge volume.");
            }
            $branchCode = array_search((int) $enrollment->branch_id, $branchIds, true);
            if ($branchCode === false) {
                throw new RuntimeException("Enrollment [{$enrollmentCode}] uses a branch outside the development institute.");
            }
            $type = $types[($index - 1) % count($types)];
            $expenseNumber = 'DEVCORE-SEXP-VOL-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $expense = $this->saveRow('student_expenses', ['expense_number' => $expenseNumber], [
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'student_stage_record_id' => null,
                'branch_id' => $enrollment->branch_id,
                'student_expense_type_id' => $type['id'],
                'student_expense_price_version_id' => $priceIds[$type['code'].':'.$branchCode],
                'description' => $type['name'],
                'quantity' => '1.000',
                'unit_amount' => $type['amount'],
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
                'notes' => $this->scenarioNote('FIN-VOL-STUDENT-EXPENSE-'.$index, 'Charge equals its scoped approved price version.'),
            ]);

            if ($index <= 39) {
                $this->saveRow('student_other_payments', ['payment_number' => 'DEVCORE-SEXP-VOL-PAY-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT)], [
                    'student_expense_id' => $expense->id,
                    'payment_method_id' => $methodId,
                    'financial_account_id' => $accountId,
                    'amount' => $type['amount'],
                    'paid_at' => sprintf('2025-02-%02d 11:00:00', 1 + (($index - 1) % 27)),
                    'transaction_reference' => null,
                    'payer_phone' => null,
                    'proof_file_path' => null,
                    'status' => 'confirmed',
                    'verification_method' => 'manual',
                    'verified_at' => self::SEEDED_AT,
                    'verified_by' => $operatorId,
                    'verification_reference' => null,
                    'verification_response' => null,
                    'rejection_reason' => null,
                    'recorded_by' => $operatorId,
                    'notes' => $this->scenarioNote('FIN-VOL-STUDENT-EXPENSE-PAYMENT-'.$index, 'Full payment of the corresponding volume charge.'),
                ]);
            }
        }
    }

    private function seedInstituteExpenses(int $instituteId, int $operatorId, int $methodId, int $accountId): void
    {
        $categoryIds = [$this->findId('institute_expense_categories', ['institute_id' => $instituteId, 'code' => 'DEV-INST-EXP-CAT-001'])];
        foreach ([
            ['code' => 'DEV-INST-EXP-CAT-002', 'name' => 'Development Academic Materials'],
            ['code' => 'DEV-INST-EXP-CAT-003', 'name' => 'Development Facilities'],
            ['code' => 'DEV-INST-EXP-CAT-004', 'name' => 'Development Administration'],
        ] as $category) {
            $saved = $this->saveRow('institute_expense_categories', ['institute_id' => $instituteId, 'code' => $category['code']], [
                'name' => $category['name'],
                'description' => 'Fictional institute expense classification.',
                'is_active' => true,
            ]);
            $categoryIds[] = (int) $saved->id;
        }

        $typeSpecs = [
            ['code' => 'DEV-INST-EXP-TYPE-002', 'name' => 'Development Classroom Materials', 'amount' => '4200.00'],
            ['code' => 'DEV-INST-EXP-TYPE-003', 'name' => 'Development Facility Cleaning', 'amount' => '2500.00'],
            ['code' => 'DEV-INST-EXP-TYPE-004', 'name' => 'Development Office Printing', 'amount' => '1600.00'],
            ['code' => 'DEV-INST-EXP-TYPE-005', 'name' => 'Development Equipment Service', 'amount' => '5600.00'],
            ['code' => 'DEV-INST-EXP-TYPE-006', 'name' => 'Development Internet Service', 'amount' => '3200.00'],
            ['code' => 'DEV-INST-EXP-TYPE-007', 'name' => 'Development Training Supplies', 'amount' => '2800.00'],
            ['code' => 'DEV-INST-EXP-TYPE-008', 'name' => 'Development Utilities', 'amount' => '4700.00'],
        ];
        $types = [[
            'id' => $this->findId('institute_expense_types', ['institute_expense_category_id' => $categoryIds[0], 'code' => 'DEV-INST-EXP-TYPE-001']),
            'code' => 'DEV-INST-EXP-TYPE-001',
            'amount' => '12000.00',
        ]];
        foreach ($typeSpecs as $index => $spec) {
            $saved = $this->saveRow('institute_expense_types', [
                'institute_expense_category_id' => $categoryIds[1 + ($index % 3)],
                'code' => $spec['code'],
            ], [
                'name' => $spec['name'],
                'description' => 'Fictional finance reference type for development records.',
                'is_active' => true,
            ]);
            $types[] = ['id' => (int) $saved->id, 'code' => $spec['code'], 'amount' => $spec['amount']];
        }

        $priceIds = [];
        foreach ($types as $index => $type) {
            $branchId = $index === 0 ? $this->findId('branches', ['institute_id' => $instituteId, 'code' => 'DEV-BR-DHAKA']) : null;
            if ($index === 0) {
                $priceIds[$type['code']] = $this->findId('institute_expense_price_versions', [
                    'institute_expense_type_id' => $type['id'],
                    'branch_id' => $branchId,
                    'version_number' => 1,
                ]);
                continue;
            }
            $price = $this->saveRow('institute_expense_price_versions', [
                'institute_expense_type_id' => $type['id'],
                'branch_id' => null,
                'version_number' => 1,
            ], [
                'amount' => $type['amount'],
                'unit_name' => 'service',
                'effective_from' => '2025-01-01',
                'effective_to' => null,
                'status' => 'approved',
                'approved_by' => $operatorId,
                'approved_at' => self::SEEDED_AT,
                'notes' => 'Fictional finance price; expense workflow is pending.',
            ]);
            $priceIds[$type['code']] = (int) $price->id;
        }

        $branchCodes = ['DEV-BR-DHAKA', 'DEV-BR-CHITTAGONG', 'DEV-BR-SYLHET'];
        $branchIds = [];
        foreach ($branchCodes as $code) {
            $branchIds[] = $this->findId('branches', ['institute_id' => $instituteId, 'code' => $code]);
        }
        foreach (range(1, 19) as $index) {
            $type = $types[$index % count($types)];
            $branchId = $index % 8 === 0
                ? $this->findId('branches', ['institute_id' => $instituteId, 'code' => 'DEV-BR-DHAKA'])
                : $branchIds[$index % count($branchIds)];
            $expenseNumber = 'DEVCORE-IEXP-VOL-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $expense = $this->saveRow('institute_expenses', ['expense_number' => $expenseNumber], [
                'branch_id' => $branchId,
                'department_id' => null,
                'institute_expense_type_id' => $type['id'],
                'institute_expense_price_version_id' => $priceIds[$type['code']],
                'staff_id' => null,
                'teacher_id' => null,
                'asset_id' => null,
                'payee_name' => 'Fictional Development Service Provider',
                'invoice_number' => 'DEVCORE-IEXP-INV-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'description' => $type['code'].' fictional service expense',
                'quantity' => '1.000',
                'unit_amount' => $type['amount'],
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
                'notes' => $this->scenarioNote('FIN-VOL-INSTITUTE-EXPENSE-'.$index, 'Expense amount equals its approved reference amount.'),
            ]);

            $this->saveRow('institute_expense_payments', ['payment_number' => 'DEVCORE-IEXP-VOL-PAY-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT)], [
                'institute_expense_id' => $expense->id,
                'payment_method_id' => $methodId,
                'financial_account_id' => $accountId,
                'amount' => $type['amount'],
                'paid_at' => sprintf('2025-03-%02d 11:00:00', 1 + ($index % 27)),
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
                'notes' => $this->scenarioNote('FIN-VOL-INSTITUTE-EXPENSE-PAYMENT-'.$index, 'Full payment of the corresponding expense.'),
            ]);
        }
    }

    private function seedTransfers(int $operatorId, int $cashAccountId, int $bankAccountId): void
    {
        foreach ([1000, 1500, 2000, 2500] as $index => $amount) {
            $number = $index + 1;
            $this->saveRow('account_transfers', ['transfer_number' => 'DEVCORE-TRANSFER-VOL-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)], [
                'from_financial_account_id' => $cashAccountId,
                'to_financial_account_id' => $bankAccountId,
                'amount' => number_format($amount, 2, '.', ''),
                'transferred_at' => sprintf('2025-04-%02d 10:00:00', 1 + $index),
                'transaction_reference' => null,
                'proof_file_path' => null,
                'status' => 'confirmed',
                'approved_by' => $operatorId,
                'approved_at' => self::SEEDED_AT,
                'confirmed_by' => $operatorId,
                'confirmed_at' => self::SEEDED_AT,
                'rejection_reason' => null,
                'recorded_by' => $operatorId,
                'notes' => $this->scenarioNote('FIN-VOL-TRANSFER-'.$number, 'Equal source and destination transfer; ledger posting is pending.'),
            ]);
        }
    }

    private function seedIncomeBills(int $operatorId, int $methodId, int $accountId, int $reserveAccountId): void
    {
        foreach (range(1, 9) as $index) {
            $amount = 2500 + ($index * 250);
            $billNumber = 'DEVCORE-INCOME-VOL-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $bill = $this->saveRow('institute_income_bill', ['bill_number' => $billNumber], [
                'university_name' => 'Fictional Regional Medical College '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'bill_items' => json_encode([[
                    'description' => 'Fictional assessment service',
                    'exam_date' => '2025-04-15',
                    'quantity' => 1,
                    'unit_price' => number_format($amount, 2, '.', ''),
                    'line_total' => number_format($amount, 2, '.', ''),
                ]], JSON_THROW_ON_ERROR),
                'total_amount' => number_format($amount, 2, '.', ''),
                'bill_date' => '2025-04-01',
                'due_on' => '2025-04-30',
                'status' => 'approved',
                'approved_by' => $operatorId,
                'approved_at' => self::SEEDED_AT,
                'rejection_reason' => null,
                'recorded_by' => $operatorId,
                'notes' => $this->scenarioNote('FIN-VOL-INCOME-BILL-'.$index, 'JSON bill line and bill total agree; application reconciliation remains pending.'),
            ]);

            $this->saveRow('institute_other_income_bill_payment', ['payment_number' => 'DEVCORE-INCOME-VOL-PAY-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT)], [
                'institute_income_bill_id' => $bill->id,
                'payment_method_id' => $methodId,
                'financial_account_id' => $index % 2 === 0 ? $reserveAccountId : $accountId,
                'amount' => number_format($amount, 2, '.', ''),
                'status' => 'paid',
                'paid_at' => sprintf('2025-04-%02d 10:00:00', 5 + $index),
                'transaction_reference' => null,
                'proof_file_path' => null,
                'received_by' => $operatorId,
                'recorded_by' => $operatorId,
                'verified_by' => $operatorId,
                'verified_at' => self::SEEDED_AT,
                'notes' => $this->scenarioNote('FIN-VOL-INCOME-PAYMENT-'.$index, 'Payment equals its associated bill total.'),
            ]);
        }
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '00');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function fromCents(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
