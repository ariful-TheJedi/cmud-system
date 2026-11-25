<?php

use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\CourseStage;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Academy\StudentStageRecord;
use App\Models\Finance\FinancialAccount;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\StudentExpense;
use App\Models\Finance\StudentExpensePayment;
use App\Models\Finance\StudentExpensePriceVersion;
use App\Models\Finance\StudentExpenseType;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use App\Models\Security\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EnrollmentTuition;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Build an isolated enrollment ledger and branch-scoped cash collection setup. */
function enrollmentBillingFixture(): array
{
    $institute = Institute::query()->create(['code' => 'EBI-'.fake()->unique()->numerify('####'), 'name' => 'Enrollment Billing Institute', 'phone' => '01700000031', 'email' => fake()->unique()->safeEmail(), 'address' => 'Test address', 'is_active' => true]);
    $branch = Branch::query()->create(['institute_id' => $institute->id, 'code' => 'EBB-'.fake()->unique()->numerify('####'), 'name' => 'Enrollment Billing Branch', 'phone' => '01700000032', 'address' => 'Branch address', 'is_active' => true]);
    $student = Student::factory()->create(['institute_id' => $institute->id, 'admission_branch_id' => $branch->id, 'student_code' => 'EB-STU-'.fake()->unique()->numerify('####')]);
    $course = Course::query()->create(['institute_id' => $institute->id, 'code' => 'EB-C-'.fake()->unique()->numerify('####'), 'name' => 'Enrollment Billing Course', 'duration_months' => 12, 'completion_period_months' => 15, 'is_active' => true, 'status' => 'active', 'is_available_for_enrollment' => true]);
    $enrollment = Enrollment::query()->create(['enrollment_code' => 'EB-ENR-'.fake()->unique()->numerify('####'), 'student_id' => $student->id, 'course_id' => $course->id, 'branch_id' => $branch->id, 'enrollment_date' => now()->toDateString(), 'expected_completion_on' => now()->addYear()->toDateString(), 'completion_deadline_on' => now()->addMonths(15)->toDateString(), 'status' => 'ongoing', 'is_active' => true]);
    $tuition = EnrollmentTuition::set(['enrollment_id' => $enrollment->id, 'tuition_number' => 'EB-TUI-'.fake()->unique()->numerify('####'), 'original_amount' => '1000.00', 'waiver_amount' => '0.00', 'issued_on' => now()->toDateString(), 'status' => 'issued']);
    $user = User::query()->create(['name' => 'Enrollment Cashier', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'is_active' => true, 'branch_id' => $branch->id]);
    $method = PaymentMethod::query()->create(['institute_id' => $institute->id, 'code' => 'EB-CASH-'.fake()->unique()->numerify('####'), 'name' => 'Cash', 'is_active' => true]);
    $account = FinancialAccount::query()->create(['institute_id' => $institute->id, 'branch_id' => $branch->id, 'code' => 'EB-ACC-'.fake()->unique()->numerify('####'), 'name' => 'Branch Cash Drawer', 'account_type' => 'cash', 'currency_code' => 'BDT', 'opening_balance' => '0.00', 'opening_balance_as_of' => now(), 'is_active' => true]);
    $expenseType = StudentExpenseType::query()->create(['institute_id' => $institute->id, 'code' => 'EB-EX-'.fake()->unique()->numerify('####'), 'name' => 'Examination Fee', 'is_active' => true]);
    $price = StudentExpensePriceVersion::query()->create(['student_expense_type_id' => $expenseType->id, 'branch_id' => $branch->id, 'course_id' => $course->id, 'course_stage_id' => null, 'version_number' => 1, 'amount' => '200.00', 'effective_from' => '2020-01-01', 'status' => 'approved']);

    return compact('institute', 'branch', 'student', 'course', 'enrollment', 'tuition', 'user', 'method', 'account', 'expenseType', 'price');
}

it('searches by Student ID and returns only the student enrollments', function (): void {
    $fixture = enrollmentBillingFixture();
    $this->actingAs($fixture['user'])->getJson('/api/v1/students/search?student_id='.$fixture['student']->student_code)
        ->assertOk()->assertJsonPath('data.student.student_code', $fixture['student']->student_code)
        ->assertJsonPath('data.enrollments.0.id', $fixture['enrollment']->id);
});

it('lists charge obligations separately in a paginated branch-scoped register', function (): void {
    $fixture = enrollmentBillingFixture();
    $this->getJson('/api/v1/student-payments')->assertUnauthorized();
    $this->getJson('/api/v1/student-payment-charges')->assertUnauthorized();
    $this->actingAs($fixture['user'])->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/expenses", [
        'student_expense_type_id' => $fixture['expenseType']->id,
        'price_version_id' => $fixture['price']->id,
        'quantity' => '2.000',
    ])->assertCreated();

    $this->actingAs($fixture['user'])->getJson('/api/v1/student-payment-charges?per_page=1')
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('data.0.student.student_code', $fixture['student']->student_code);
    $allCharges = $this->actingAs($fixture['user'])->getJson('/api/v1/student-payment-charges?per_page=100')
        ->assertOk()->json('data');
    expect(collect($allCharges)->firstWhere('kind', 'tuition'))
        ->toMatchArray(['amount' => '1000.00', 'payable_amount' => '1000.00', 'outstanding_balance' => '1000.00'])
        ->and(collect($allCharges)->firstWhere('kind', 'expense'))
        ->toMatchArray(['amount' => '400.00', 'payable_amount' => '400.00', 'outstanding_balance' => '400.00']);

    $otherBranch = Branch::query()->create([
        'institute_id' => $fixture['institute']->id,
        'code' => 'EBR-'.fake()->unique()->numerify('####'),
        'name' => 'Separate Register Branch',
        'phone' => '01700000035',
        'address' => 'Separate branch address',
        'is_active' => true,
    ]);
    $otherUser = User::query()->create([
        'name' => 'Separate Branch Cashier',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'branch_id' => $otherBranch->id,
    ]);

    $this->actingAs($otherUser)->getJson('/api/v1/student-payment-charges')
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);

    $superuser = User::query()->create([
        'name' => 'Finance Superuser',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'is_super_admin' => true,
    ]);
    $this->actingAs($superuser)->getJson('/api/v1/student-payment-charges?search='.$fixture['student']->student_code)
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
});

it('provides branch-scoped course and course-dependent batch filters for the charge register', function (): void {
    $fixture = enrollmentBillingFixture();
    $batch = Batch::query()->create([
        'course_id' => $fixture['course']->id,
        'branch_id' => $fixture['branch']->id,
        'code' => 'EB-FILTER-'.fake()->unique()->numerify('####'),
        'name' => 'Morning Batch',
        'starts_on' => now()->toDateString(),
        'expected_ends_on' => now()->addYear()->toDateString(),
        'status' => 'active',
    ]);
    $fixture['enrollment']->update(['batch_id' => $batch->id]);

    $this->actingAs($fixture['user'])
        ->getJson('/api/v1/student-payments/options')
        ->assertOk()
        ->assertJsonFragment(['id' => $fixture['course']->id, 'name' => $fixture['course']->name]);

    $this->actingAs($fixture['user'])
        ->getJson('/api/v1/student-payments/options?course_id='.$fixture['course']->id)
        ->assertOk()
        ->assertJsonFragment(['id' => $batch->id, 'name' => $batch->name]);

    $this->actingAs($fixture['user'])
        ->getJson('/api/v1/student-payment-charges?search='.urlencode($fixture['student']->student_code).'&course_id='.$fixture['course']->id.'&batch_id='.$batch->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student.student_code', $fixture['student']->student_code)
        ->assertJsonPath('data.0.enrollment.batch_name', $batch->name);
});

it('filters payment records by the selected course and its batch', function (): void {
    $fixture = enrollmentBillingFixture();
    $firstBatch = Batch::query()->create([
        'course_id' => $fixture['course']->id,
        'branch_id' => $fixture['branch']->id,
        'code' => 'EB-FIRST-'.fake()->unique()->numerify('####'),
        'name' => 'First Course Batch',
        'starts_on' => now()->toDateString(),
        'expected_ends_on' => now()->addYear()->toDateString(),
        'status' => 'active',
    ]);
    $fixture['enrollment']->update(['batch_id' => $firstBatch->id]);

    $secondCourse = Course::query()->create([
        'institute_id' => $fixture['institute']->id,
        'code' => 'EB-SECOND-'.fake()->unique()->numerify('####'),
        'name' => 'Second Billing Course',
        'duration_months' => 12,
        'completion_period_months' => 15,
        'is_active' => true,
        'status' => 'active',
        'is_available_for_enrollment' => true,
    ]);
    $secondBatch = Batch::query()->create([
        'course_id' => $secondCourse->id,
        'branch_id' => $fixture['branch']->id,
        'code' => 'EB-SECOND-BATCH-'.fake()->unique()->numerify('####'),
        'name' => 'Second Course Batch',
        'starts_on' => now()->toDateString(),
        'expected_ends_on' => now()->addYear()->toDateString(),
        'status' => 'active',
    ]);
    $secondEnrollment = Enrollment::query()->create([
        'enrollment_code' => 'EB-SECOND-ENR-'.fake()->unique()->numerify('####'),
        'student_id' => $fixture['student']->id,
        'course_id' => $secondCourse->id,
        'branch_id' => $fixture['branch']->id,
        'batch_id' => $secondBatch->id,
        'enrollment_date' => now()->toDateString(),
        'expected_completion_on' => now()->addYear()->toDateString(),
        'completion_deadline_on' => now()->addMonths(15)->toDateString(),
        'status' => 'ongoing',
        'is_active' => true,
    ]);
    EnrollmentTuition::set([
        'enrollment_id' => $secondEnrollment->id,
        'tuition_number' => 'EB-SECOND-TUI-'.fake()->unique()->numerify('####'),
        'original_amount' => '1000.00',
        'waiver_amount' => '0.00',
        'issued_on' => now()->toDateString(),
        'status' => 'issued',
    ]);

    foreach ([$fixture['enrollment'], $secondEnrollment] as $index => $enrollment) {
        $this->actingAs($fixture['user'])->postJson(
            "/api/v1/enrollments/{$enrollment->id}/tuition/payments",
            [
                'amount' => '100.00',
                'financial_account_id' => $fixture['account']->id,
                'idempotency_key' => 'course-batch-register-'.$index,
            ],
        )->assertCreated();
    }

    $this->actingAs($fixture['user'])
        ->getJson('/api/v1/student-payments/options?course_id='.$fixture['course']->id)
        ->assertOk()
        ->assertJsonCount(1, 'data.batches')
        ->assertJsonPath('data.batches.0.id', $firstBatch->id);

    $this->actingAs($fixture['user'])
        ->getJson('/api/v1/student-payments?course_id='.$fixture['course']->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.enrollment.enrollment_code', $fixture['enrollment']->enrollment_code);

    $this->actingAs($fixture['user'])
        ->getJson('/api/v1/student-payments?search='.urlencode($fixture['student']->student_code)
            .'&course_id='.$secondCourse->id.'&batch_id='.$secondBatch->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.enrollment.enrollment_code', $secondEnrollment->enrollment_code);

    $this->actingAs($fixture['user'])
        ->getJson('/api/v1/student-payments?search='.urlencode($fixture['student']->full_name)
            .'&course_id='.$secondCourse->id.'&batch_id='.$secondBatch->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student.full_name', $fixture['student']->full_name);

    $this->actingAs($fixture['user'])
        ->getJson('/api/v1/student-payments?course_id='.$fixture['course']->id.'&batch_id='.$secondBatch->id)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('lists each payment record separately when one enrollment has multiple payments', function (): void {
    $fixture = enrollmentBillingFixture();
    $first = $this->actingAs($fixture['user'])->postJson(
        "/api/v1/enrollments/{$fixture['enrollment']->id}/tuition/payments",
        [
            'amount' => '125.00',
            'financial_account_id' => $fixture['account']->id,
            'idempotency_key' => 'register-receipt-details-001',
        ],
    )->assertCreated()->json('data.payment.payment_number');
    $second = $this->actingAs($fixture['user'])->postJson(
        "/api/v1/enrollments/{$fixture['enrollment']->id}/tuition/payments",
        [
            'amount' => '175.00',
            'financial_account_id' => $fixture['account']->id,
            'idempotency_key' => 'register-receipt-details-002',
        ],
    )->assertCreated()->json('data.payment.payment_number');

    $this->actingAs($fixture['user'])
        ->getJson('/api/v1/student-payments?search='.$fixture['student']->student_code)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.amount', '175.00')
        ->assertJsonMissingPath('data.0.charge')
        ->assertJsonMissingPath('data.0.due')
        ->assertJsonMissingPath('data.0.waiver')
        ->assertJsonFragment(['payment_number' => $first, 'amount' => '125.00', 'recorded_by_name' => $fixture['user']->name])
        ->assertJsonFragment(['payment_number' => $second, 'amount' => '175.00', 'recorded_by_name' => $fixture['user']->name]);

    $otherBranch = Branch::query()->create([
        'institute_id' => $fixture['institute']->id,
        'code' => 'EBP-'.fake()->unique()->numerify('####'),
        'name' => 'Payment Records Other Branch',
        'phone' => '01700000036',
        'address' => 'Other branch address',
        'is_active' => true,
    ]);
    $otherUser = User::query()->create([
        'name' => 'Payment Records Other Branch User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'branch_id' => $otherBranch->id,
    ]);
    $this->actingAs($otherUser)->getJson('/api/v1/student-payments?search='.$fixture['student']->student_code)
        ->assertOk()->assertJsonCount(0, 'data');

    $superuser = User::query()->create([
        'name' => 'All Branch Payment Records User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'is_super_admin' => true,
    ]);
    $this->actingAs($superuser)->getJson('/api/v1/student-payments?search='.$fixture['student']->student_code)
        ->assertOk()->assertJsonCount(2, 'data');
});

it('includes expense payments as separate student payment records', function (): void {
    $fixture = enrollmentBillingFixture();
    $expense = $this->actingAs($fixture['user'])->postJson(
        "/api/v1/enrollments/{$fixture['enrollment']->id}/expenses",
        [
            'student_expense_type_id' => $fixture['expenseType']->id,
            'price_version_id' => $fixture['price']->id,
        ],
    )->assertCreated()->json('data');

    $this->actingAs($fixture['user'])->postJson(
        "/api/v1/enrollments/{$fixture['enrollment']->id}/expenses/{$expense['id']}/payments",
        [
            'amount' => '75.00',
            'financial_account_id' => $fixture['account']->id,
            'idempotency_key' => 'expense-record-register-001',
        ],
    )->assertCreated();

    $this->actingAs($fixture['user'])
        ->getJson('/api/v1/student-payments?search='.$fixture['student']->student_code)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.source', 'expense')
        ->assertJsonPath('data.0.amount', '75.00')
        ->assertJsonPath('data.0.student.student_code', $fixture['student']->student_code);
});

it('can issue an other bill and record its payment atomically or leave it unpaid', function (): void {
    $fixture = enrollmentBillingFixture();
    $bankMethod = PaymentMethod::query()->create([
        'institute_id' => $fixture['institute']->id,
        'code' => 'EB-BANK-'.fake()->unique()->numerify('####'),
        'name' => 'Bank Transfer',
        'requires_transaction_reference' => true,
        'is_active' => true,
    ]);
    $bankAccount = FinancialAccount::query()->create([
        'institute_id' => $fixture['institute']->id,
        'branch_id' => $fixture['branch']->id,
        'code' => 'EB-BANK-ACC-'.fake()->unique()->numerify('####'),
        'name' => 'Branch Bank Account',
        'account_type' => 'bank',
        'currency_code' => 'BDT',
        'opening_balance' => '0.00',
        'opening_balance_as_of' => now(),
        'is_active' => true,
    ]);
    $payload = [
        'student_expense_type_id' => $fixture['expenseType']->id,
        'price_version_id' => $fixture['price']->id,
        'payment' => [
            'amount' => '125.00',
            'financial_account_id' => $bankAccount->id,
            'payment_method_id' => $bankMethod->id,
            'idempotency_key' => 'other-bill-pay-now-001',
            'transaction_reference' => 'OTHER-BILL-BANK-001',
        ],
    ];

    $response = $this->actingAs($fixture['user'])
        ->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/expenses", $payload)
        ->assertCreated()
        ->assertJsonPath('data.payment.amount', '125.00')
        ->assertJsonPath('data.payment.payment_method', 'Bank Transfer')
        ->assertJsonPath('data.payment.status', 'confirmed');

    $this->actingAs($fixture['user'])
        ->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/expenses", $payload)
        ->assertOk()
        ->assertJsonPath('data.idempotent_replay', true);

    $changedMethodRetry = $payload;
    unset($changedMethodRetry['payment']['payment_method_id']);
    $this->actingAs($fixture['user'])
        ->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/expenses", $changedMethodRetry)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['idempotency_key']);

    expect(StudentExpense::query()->count())->toBe(1)
        ->and(StudentExpensePayment::query()->count())->toBe(1)
        ->and($response->json('data.outstanding_balance'))->toBe('75.00');
});

it('returns enrollment-specific billing totals and records retry-safe expense payments', function (): void {
    $fixture = enrollmentBillingFixture();
    $this->actingAs($fixture['user'])->getJson("/api/v1/enrollments/{$fixture['enrollment']->id}/billing")
        ->assertOk()->assertJsonPath('data.totals.tuition_amount', '1000.00')
        ->assertJsonPath('data.totals.outstanding_balance', '1000.00');

    $created = $this->actingAs($fixture['user'])->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/expenses", [
        'student_expense_type_id' => $fixture['expenseType']->id,
        'price_version_id' => $fixture['price']->id,
        'quantity' => '2.000',
    ])->assertCreated()->assertJsonPath('data.amount', '400.00');
    $expenseId = $created->json('data.id');

    $payload = ['amount' => '100.00', 'financial_account_id' => $fixture['account']->id, 'idempotency_key' => 'expense-retry-001', 'transaction_reference' => 'CASH-001'];
    $this->actingAs($fixture['user'])->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/expenses/{$expenseId}/payments", $payload)
        ->assertCreated()->assertJsonPath('data.expense.outstanding_balance', '300.00')
        ->assertJsonPath('data.payment.recorded_by_name', $fixture['user']->name);
    $this->actingAs($fixture['user'])->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/expenses/{$expenseId}/payments", $payload)
        ->assertOk()->assertJsonPath('data.idempotent_replay', true);

    expect(StudentExpense::query()->count())->toBe(1)
        ->and(StudentExpensePayment::query()->count())->toBe(1);
});

it('does not allow another branch to read a student or pay a different enrollment', function (): void {
    $fixture = enrollmentBillingFixture();
    $otherBranch = Branch::query()->create(['institute_id' => $fixture['institute']->id, 'code' => 'EBX-'.fake()->unique()->numerify('####'), 'name' => 'Other Branch', 'phone' => '01700000033', 'address' => 'Other address', 'is_active' => true]);
    $otherUser = User::query()->create(['name' => 'Other Branch User', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'is_active' => true, 'branch_id' => $otherBranch->id]);

    $this->actingAs($otherUser)->getJson('/api/v1/students/search?student_id='.$fixture['student']->student_code)->assertNotFound();
    $this->actingAs($fixture['user'])->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/tuition/payments", [
        'amount' => '10.00', 'financial_account_id' => $fixture['account']->id, 'idempotency_key' => 'enrollment-check-001',
    ])->assertCreated();
    expect(StudentTuitionPayment::query()->count())->toBe(1);

    $otherEnrollment = Enrollment::query()->create(['enrollment_code' => 'EB-ENR-'.fake()->unique()->numerify('####'), 'student_id' => $fixture['student']->id, 'course_id' => $fixture['course']->id, 'branch_id' => $fixture['branch']->id, 'enrollment_date' => now()->toDateString(), 'expected_completion_on' => now()->addYear()->toDateString(), 'completion_deadline_on' => now()->addMonths(15)->toDateString(), 'status' => 'ongoing', 'is_active' => true]);
    $otherTuition = EnrollmentTuition::set(['enrollment_id' => $otherEnrollment->id, 'tuition_number' => 'EB-TUI-'.fake()->unique()->numerify('####'), 'original_amount' => '300.00', 'waiver_amount' => '0.00', 'issued_on' => now()->toDateString(), 'status' => 'issued']);
    $otherExpense = StudentExpense::query()->create([
        'expense_number' => 'EB-EXP-'.fake()->unique()->numerify('####'),
        'student_id' => $fixture['student']->id,
        'enrollment_id' => $otherEnrollment->id,
        'branch_id' => $fixture['branch']->id,
        'student_expense_type_id' => $fixture['expenseType']->id,
        'student_expense_price_version_id' => $fixture['price']->id,
        'description' => 'Other enrollment expense',
        'quantity' => '1.000',
        'unit_amount' => '200.00',
        'issued_on' => now()->toDateString(),
        'status' => 'issued',
        'recorded_by' => $fixture['user']->id,
    ]);
    $this->actingAs($fixture['user'])->getJson('/api/v1/students/'.$fixture['student']->student_code.'/enrollments')
        ->assertOk()->assertJsonCount(2, 'data.enrollments');
    $this->actingAs($fixture['user'])->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/tuition/{$otherTuition->id}/payments", [
        'amount' => '10.00', 'financial_account_id' => $fixture['account']->id, 'idempotency_key' => 'wrong-enrollment-001',
    ])->assertNotFound();
    $this->actingAs($fixture['user'])->getJson("/api/v1/enrollments/{$fixture['enrollment']->id}/expenses/{$otherExpense->id}")
        ->assertNotFound();
    $this->actingAs($fixture['user'])->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/expenses/{$otherExpense->id}/payments", [
        'amount' => '10.00', 'financial_account_id' => $fixture['account']->id, 'idempotency_key' => 'wrong-enrollment-expense-001',
    ])->assertNotFound();
});

it('rejects overpayment and rejects an expense price from another enrollment context', function (): void {
    $fixture = enrollmentBillingFixture();
    $this->actingAs($fixture['user'])->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/expenses", [
        'student_expense_type_id' => $fixture['expenseType']->id,
        'price_version_id' => $fixture['price']->id,
        'quantity' => '1.000',
    ])->assertCreated();
    $expense = StudentExpense::query()->firstOrFail();

    $this->actingAs($fixture['user'])->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/expenses/{$expense->id}/payments", [
        'amount' => '200.01', 'financial_account_id' => $fixture['account']->id, 'idempotency_key' => 'expense-overpay-001',
    ])->assertUnprocessable()->assertJsonValidationErrors(['amount']);
});

it('supports multiple partial tuition payments and returns the cleared enrollment balance', function (): void {
    $fixture = enrollmentBillingFixture();
    $path = "/api/v1/enrollments/{$fixture['enrollment']->id}/tuition/payments";
    $this->actingAs($fixture['user'])->postJson($path, [
        'amount' => '400.00', 'financial_account_id' => $fixture['account']->id, 'idempotency_key' => 'tuition-part-1',
    ])->assertCreated()->assertJsonPath('data.total_paid', '400.00');
    $this->actingAs($fixture['user'])->postJson($path, [
        'amount' => '600.00', 'financial_account_id' => $fixture['account']->id, 'idempotency_key' => 'tuition-part-2',
    ])->assertCreated()->assertJsonPath('data.total_paid', '1000.00');

    $this->actingAs($fixture['user'])->getJson("/api/v1/enrollments/{$fixture['enrollment']->id}/payments")
        ->assertOk()->assertJsonCount(2, 'data');
    expect(StudentTuitionPayment::query()->count())->toBe(2);
});

it('rejects a reused payment key when its payload changes', function (): void {
    $fixture = enrollmentBillingFixture();
    $path = "/api/v1/enrollments/{$fixture['enrollment']->id}/tuition/payments";
    $payload = [
        'amount' => '100.00',
        'financial_account_id' => $fixture['account']->id,
        'idempotency_key' => 'tuition-payload-key',
        'paid_at' => '2026-11-25T10:00:00+06:00',
    ];
    $this->actingAs($fixture['user'])->postJson($path, $payload)->assertCreated();
    $this->actingAs($fixture['user'])->postJson($path, [...$payload, 'amount' => '101.00'])
        ->assertUnprocessable()->assertJsonValidationErrors(['idempotency_key']);
    $this->actingAs($fixture['user'])->postJson($path, [...$payload, 'paid_at' => '2026-11-25T11:00:00+06:00'])
        ->assertUnprocessable()->assertJsonValidationErrors(['idempotency_key']);

    expect(StudentTuitionPayment::query()->count())->toBe(1);
});

it('only exposes and accepts stage prices for a stage in the selected enrollment', function (): void {
    $fixture = enrollmentBillingFixture();
    $batch = Batch::query()->create([
        'course_id' => $fixture['course']->id,
        'branch_id' => $fixture['branch']->id,
        'code' => 'EB-BATCH-'.fake()->unique()->numerify('####'),
        'name' => 'Enrollment Billing Batch',
        'starts_on' => now()->toDateString(),
        'expected_ends_on' => now()->addYear()->toDateString(),
        'status' => 'active',
    ]);
    $stage = CourseStage::query()->create([
        'course_id' => $fixture['course']->id,
        'code' => 'EB-STAGE-'.fake()->unique()->numerify('####'),
        'name' => 'Stage One',
        'sequence_number' => 1,
        'duration_months' => 6,
        'is_active' => true,
    ]);
    $stageRecord = StudentStageRecord::query()->create([
        'enrollment_id' => $fixture['enrollment']->id,
        'course_stage_id' => $stage->id,
        'batch_id' => $batch->id,
        'started_on' => now()->toDateString(),
        'expected_completion_on' => now()->addMonths(6)->toDateString(),
        'status' => 'ongoing',
    ]);
    $this->actingAs($fixture['user'])
        ->getJson('/api/v1/students/search?student_id='.$fixture['student']->student_code)
        ->assertOk()->assertJsonPath('data.enrollments.0.stage_records.0.id', $stageRecord->id);
    $stagePrice = StudentExpensePriceVersion::query()->create([
        'student_expense_type_id' => $fixture['expenseType']->id,
        'branch_id' => $fixture['branch']->id,
        'course_id' => $fixture['course']->id,
        'course_stage_id' => $stage->id,
        'version_number' => 2,
        'amount' => '350.00',
        'effective_from' => '2020-01-01',
        'status' => 'approved',
    ]);
    $basePath = "/api/v1/enrollments/{$fixture['enrollment']->id}";

    $this->actingAs($fixture['user'])->getJson("$basePath/expenses/options")
        ->assertOk()->assertJsonMissing(['id' => $stagePrice->id]);
    $this->actingAs($fixture['user'])->getJson("$basePath/expenses/options?student_stage_record_id={$stageRecord->id}")
        ->assertOk()->assertJsonFragment(['id' => $stagePrice->id]);
    $this->actingAs($fixture['user'])->postJson("$basePath/expenses", [
        'student_expense_type_id' => $fixture['expenseType']->id,
        'price_version_id' => $stagePrice->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['price_version_id']);
    $this->actingAs($fixture['user'])->postJson("$basePath/expenses", [
        'student_expense_type_id' => $fixture['expenseType']->id,
        'price_version_id' => $stagePrice->id,
        'student_stage_record_id' => $stageRecord->id,
    ])->assertCreated()->assertJsonPath('data.unit_amount', '350.00');

    $expense = StudentExpense::query()->firstOrFail();
    $stagePrice->update(['amount' => '900.00']);

    expect($expense->enrollment_id)->toBe($fixture['enrollment']->id)
        ->and($expense->student_stage_record_id)->toBe($stageRecord->id)
        ->and((string) $expense->fresh()->unit_amount)->toBe('350.00');
});

it('rejects a cash account scoped to another branch', function (): void {
    $fixture = enrollmentBillingFixture();
    $otherBranch = Branch::query()->create([
        'institute_id' => $fixture['institute']->id,
        'code' => 'EBA-'.fake()->unique()->numerify('####'),
        'name' => 'Other Billing Branch',
        'phone' => '01700000034',
        'address' => 'Other branch address',
        'is_active' => true,
    ]);
    $otherAccount = FinancialAccount::query()->create([
        'institute_id' => $fixture['institute']->id,
        'branch_id' => $otherBranch->id,
        'code' => 'EB-OTHER-'.fake()->unique()->numerify('####'),
        'name' => 'Other Branch Drawer',
        'account_type' => 'cash',
        'currency_code' => 'BDT',
        'opening_balance' => '0.00',
        'opening_balance_as_of' => now(),
        'is_active' => true,
    ]);

    $this->actingAs($fixture['user'])->postJson("/api/v1/enrollments/{$fixture['enrollment']->id}/tuition/payments", [
        'amount' => '100.00', 'financial_account_id' => $otherAccount->id, 'idempotency_key' => 'wrong-cash-account',
    ])->assertUnprocessable()->assertJsonValidationErrors(['financial_account_id']);
    expect(StudentTuitionPayment::query()->count())->toBe(0);
});
