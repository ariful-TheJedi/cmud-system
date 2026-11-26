<?php

use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
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

/** Create a minimal isolated branch billing setup against the migrated test schema. */
function studentPaymentApiFixture(): array
{
    $institute = Institute::query()->create(['code' => 'SPA-'.fake()->unique()->numerify('####'), 'name' => 'Student Payment API Institute', 'phone' => '01700000001', 'email' => fake()->unique()->safeEmail(), 'address' => 'API fixture', 'is_active' => true]);
    $branch = Branch::query()->create(['institute_id' => $institute->id, 'code' => 'SPB-'.fake()->unique()->numerify('####'), 'name' => 'Student Payment Branch', 'phone' => '01700000002', 'address' => 'API fixture branch', 'is_active' => true]);
    $student = Student::factory()->create(['institute_id' => $institute->id, 'admission_branch_id' => $branch->id, 'student_code' => 'SP-STU-'.fake()->unique()->numerify('####'), 'full_name' => 'Receipt Test Student']);
    $course = Course::query()->create(['institute_id' => $institute->id, 'code' => 'SPC-'.fake()->unique()->numerify('####'), 'name' => 'Receipt Test Course', 'duration_months' => 12, 'completion_period_months' => 15, 'is_active' => true]);
    $enrollment = Enrollment::query()->create(['enrollment_code' => 'SPE-'.fake()->unique()->numerify('####'), 'student_id' => $student->id, 'course_id' => $course->id, 'branch_id' => $branch->id, 'enrollment_date' => now()->toDateString(), 'expected_completion_on' => now()->addYear()->toDateString(), 'completion_deadline_on' => now()->addMonths(15)->toDateString(), 'status' => 'ongoing', 'is_active' => true]);
    EnrollmentTuition::set(['enrollment_id' => $enrollment->id, 'tuition_number' => 'SPT-'.fake()->unique()->numerify('####'), 'original_amount' => '1000.00', 'waiver_amount' => '100.00', 'issued_on' => now()->toDateString(), 'status' => 'issued']);
    $user = User::query()->create(['name' => 'Student Billing Operator', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'is_active' => true, 'branch_id' => $branch->id]);
    $cash = PaymentMethod::query()->create(['institute_id' => $institute->id, 'code' => 'sp_cash', 'name' => 'Cash', 'is_active' => true]);
    $mobile = PaymentMethod::query()->create(['institute_id' => $institute->id, 'code' => 'sp_mobile_banking', 'name' => 'Mobile Banking', 'requires_transaction_reference' => true, 'requires_sender_phone' => true, 'is_active' => true]);
    $item = StudentExpenseType::query()->create(['institute_id' => $institute->id, 'code' => 'SPX-'.fake()->unique()->numerify('####'), 'name' => 'Examination Fee', 'is_active' => true]);
    StudentExpensePriceVersion::query()->create(['student_expense_type_id' => $item->id, 'branch_id' => $branch->id, 'course_id' => $course->id, 'version_number' => 1, 'amount' => '200.00', 'effective_from' => '2020-01-01', 'status' => 'approved']);

    return compact('institute', 'branch', 'student', 'course', 'enrollment', 'user', 'cash', 'mobile', 'item');
}

function studentPaymentPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'student_id' => $fixture['student']->student_code,
        'enrollment_id' => $fixture['enrollment']->id,
        'amount' => '100.00',
        'paid_at' => now()->toDateString(),
        'payment_method_id' => $fixture['cash']->id,
        'idempotency_key' => (string) Illuminate\Support\Str::uuid(),
    ], $overrides);
}

/** Add the receiving bank account required only for mobile banking receipts. */
function studentPaymentReceivingAccount(array $fixture): FinancialAccount
{
    return FinancialAccount::query()->create([
        'institute_id' => $fixture['institute']->id,
        'branch_id' => $fixture['branch']->id,
        'code' => 'SPBANK-'.fake()->unique()->numerify('####'),
        'name' => 'Branch Mobile Payment Receiving Account',
        'account_type' => 'bank',
        'currency_code' => 'BDT',
        'opening_balance' => '0.00',
        'opening_balance_as_of' => now(),
        'is_active' => true,
    ]);
}

it('returns running enrollments and records tuition payments without an issued tuition bill', function (): void {
    $fixture = studentPaymentApiFixture();
    $fixture['enrollment']->update([
        'tuition_number' => null,
        'tuition_original_amount' => null,
        'tuition_status' => null,
    ]);
    $this->actingAs($fixture['user'])
        ->getJson('/api/v1/students/search?student_code='.$fixture['student']->student_code)
        ->assertOk()
        ->assertJsonPath('data.student.student_code', $fixture['student']->student_code)
        ->assertJsonPath('data.enrollments.0.id', $fixture['enrollment']->id);

    $this->actingAs($fixture['user'])->postJson('/api/v1/student-payments/tuition', [
        ...studentPaymentPayload($fixture),
        'enrollment_id' => null,
    ])->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');

    $this->actingAs($fixture['user'])->postJson('/api/v1/student-payments/tuition', studentPaymentPayload($fixture, ['amount' => '1500.00']))
        ->assertCreated()->assertJsonPath('data.status', 'confirmed');
    expect(StudentTuitionPayment::query()->firstOrFail()->enrollment_id)->toBe($fixture['enrollment']->id)
        ->and(StudentTuitionPayment::query()->firstOrFail()->financial_account_id)->toBeNull();

    $this->actingAs($fixture['user'])->getJson('/api/v1/students/'.$fixture['student']->student_code)
        ->assertOk()
        ->assertJsonPath('data.enrollments.0.tuition.payments.0.payment_number', StudentTuitionPayment::query()->firstOrFail()->payment_number);
    $this->actingAs($fixture['user'])->getJson('/api/v1/enrollments/'.$fixture['enrollment']->id.'/financial-summary')
        ->assertOk()
        ->assertJsonPath('data.unbilled_tuition_payments.0.payment_number', StudentTuitionPayment::query()->firstOrFail()->payment_number);

    $this->actingAs($fixture['user'])->postJson('/api/v1/student-payments/other-expenses', studentPaymentPayload($fixture, [
        'student_expense_type_id' => $fixture['item']->id,
        'amount' => '200.00',
    ]))->assertCreated();
});

it('lists selectable tuition enrollment and records cash against the explicitly selected enrollment', function (): void {
    $fixture = studentPaymentApiFixture();
    $otherEnrollment = Enrollment::query()->create([
        'enrollment_code' => 'SPE-SECOND-'.fake()->unique()->numerify('####'),
        'student_id' => $fixture['student']->id,
        'course_id' => $fixture['course']->id,
        'branch_id' => $fixture['branch']->id,
        'enrollment_date' => now()->subDay()->toDateString(),
        'expected_completion_on' => now()->addYear()->toDateString(),
        'completion_deadline_on' => now()->addMonths(15)->toDateString(),
        'status' => 'ongoing',
        'is_active' => true,
    ]);
    $this->actingAs($fixture['user'])->getJson('/api/v1/students/search?student_code='.$fixture['student']->student_code)
        ->assertOk()->assertJsonCount(2, 'data.enrollments');

    $this->actingAs($fixture['user'])->postJson('/api/v1/student-payments/tuition', studentPaymentPayload($fixture))
        ->assertCreated()->assertJsonPath('data.status', 'confirmed');

    expect(StudentTuitionPayment::query()->firstOrFail()->enrollment_id)->toBe($fixture['enrollment']->id)
        ->and(StudentTuitionPayment::query()->firstOrFail()->financial_account_id)->toBeNull()
        ->and($otherEnrollment->exists)->toBeTrue();
});

it('keeps mobile banking receipts pending and enforces transaction and payer fields', function (): void {
    $fixture = studentPaymentApiFixture();
    $payload = studentPaymentPayload($fixture, ['payment_method_id' => $fixture['mobile']->id]);
    $verifiedDetails = $payload + [
        'transaction_reference' => 'BK123456',
        'payer_phone' => '01700000000',
    ];

    $this->actingAs($fixture['user'])->postJson('/api/v1/student-payments/tuition', $payload)->assertUnprocessable();
    $this->actingAs($fixture['user'])->postJson('/api/v1/student-payments/tuition', $verifiedDetails)
        ->assertUnprocessable()->assertJsonValidationErrors('payment_method_id');
    $account = studentPaymentReceivingAccount($fixture);
    $this->actingAs($fixture['user'])->postJson('/api/v1/student-payments/tuition', $verifiedDetails)
        ->assertCreated()->assertJsonPath('data.status', 'pending');

    $payment = StudentTuitionPayment::query()->firstOrFail();
    expect($payment->verified_at)->toBeNull()
        ->and($payment->verified_by)->toBeNull()
        ->and($payment->financial_account_id)->toBe($account->id);
});

it('creates other bill and payment atomically and replays the same receipt key', function (): void {
    $fixture = studentPaymentApiFixture();
    $payload = studentPaymentPayload($fixture, ['student_expense_type_id' => $fixture['item']->id, 'amount' => '200.00']);

    $this->actingAs($fixture['user'])->postJson('/api/v1/student-payments/other-expenses', $payload)
        ->assertCreated()->assertJsonPath('data.bill.payable_amount', '200.00');
    $this->actingAs($fixture['user'])->postJson('/api/v1/student-payments/other-expenses', $payload)
        ->assertOk()->assertJsonPath('data.idempotent_replay', true);

    expect(StudentExpense::query()->count())->toBe(1)
        ->and(StudentExpensePayment::query()->count())->toBe(1)
        ->and(StudentExpensePayment::query()->firstOrFail()->financial_account_id)->toBeNull();
});

it('requires the receiving account for bKash other-expense payments', function (): void {
    $fixture = studentPaymentApiFixture();
    $payload = studentPaymentPayload($fixture, [
        'student_expense_type_id' => $fixture['item']->id,
        'payment_method_id' => $fixture['mobile']->id,
        'amount' => '200.00',
        'transaction_reference' => 'BK-EXP-123',
        'payer_phone' => '01700000000',
    ]);

    $this->actingAs($fixture['user'])->postJson('/api/v1/student-payments/other-expenses', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('payment_method_id');

    $account = studentPaymentReceivingAccount($fixture);
    $this->actingAs($fixture['user'])->postJson('/api/v1/student-payments/other-expenses', $payload)
        ->assertCreated()->assertJsonPath('data.payment.status', 'pending');

    expect(StudentExpensePayment::query()->firstOrFail()->financial_account_id)->toBe($account->id);
});

it('searches both receipt sources by name and payment date while applying branch scope before pagination', function (): void {
    $fixture = studentPaymentApiFixture();
    $tuitionPayment = StudentTuitionPayment::query()->create([
        'enrollment_id' => $fixture['enrollment']->id,
        'payment_number' => 'SPT-RECEIPT-1',
        'payment_method_id' => $fixture['cash']->id,
        'financial_account_id' => null,
        'amount' => '100.00', 'paid_at' => now()->startOfDay(), 'status' => 'confirmed',
        'verified_at' => now(), 'verified_by' => $fixture['user']->id, 'recorded_by' => $fixture['user']->id,
    ]);
    $bill = StudentExpense::query()->create([
        'expense_number' => 'SP-BILL-1', 'student_id' => $fixture['student']->id, 'enrollment_id' => $fixture['enrollment']->id,
        'branch_id' => $fixture['branch']->id, 'student_expense_type_id' => $fixture['item']->id,
        'student_expense_price_version_id' => StudentExpensePriceVersion::query()->firstOrFail()->id,
        'description' => 'Exam', 'quantity' => '1.000', 'unit_amount' => '200.00', 'waiver_amount' => '0.00',
        'issued_on' => now()->toDateString(), 'status' => 'issued', 'recorded_by' => $fixture['user']->id,
    ]);
    StudentExpensePayment::query()->create([
        'student_expense_id' => $bill->id, 'payment_number' => 'SP-EXP-RECEIPT-1', 'payment_method_id' => $fixture['cash']->id,
        'financial_account_id' => null, 'amount' => '50.00', 'paid_at' => now()->startOfDay(),
        'status' => 'confirmed', 'verified_at' => now(), 'verified_by' => $fixture['user']->id, 'recorded_by' => $fixture['user']->id,
    ]);

    $this->actingAs($fixture['user'])->getJson('/api/v1/student-payments?search=Receipt%20Test&date='.now()->toDateString())
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
    expect($tuitionPayment->exists)->toBeTrue();
});

it('keeps other branch student billing data hidden from a branch employee', function (): void {
    $fixture = studentPaymentApiFixture();
    $otherBranch = Branch::query()->create(['institute_id' => $fixture['institute']->id, 'code' => 'SP-OTHER-'.fake()->unique()->numerify('###'), 'name' => 'Other Branch', 'phone' => '01700000003', 'address' => 'Other branch', 'is_active' => true]);
    $otherStudent = Student::factory()->create(['institute_id' => $fixture['institute']->id, 'admission_branch_id' => $otherBranch->id, 'student_code' => 'OUT-'.fake()->unique()->numerify('####')]);
    Enrollment::query()->create(['enrollment_code' => 'OUT-ENR-'.fake()->unique()->numerify('####'), 'student_id' => $otherStudent->id, 'course_id' => $fixture['course']->id, 'branch_id' => $otherBranch->id, 'enrollment_date' => now()->toDateString(), 'expected_completion_on' => now()->addYear()->toDateString(), 'completion_deadline_on' => now()->addMonths(15)->toDateString(), 'status' => 'ongoing', 'is_active' => true]);

    $this->actingAs($fixture['user'])->getJson('/api/v1/students/search?student_code='.$otherStudent->student_code)->assertNotFound();
});
