<?php

use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Finance\FinancialAccount;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\StudentTuition;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use App\Models\Security\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Build a tuition ledger and its branch-local cash collection configuration. */
function tuitionPaymentContext(array $overrides = []): array
{
    $institute = Institute::query()->create([
        'code' => 'TUI-'.fake()->unique()->numerify('####'),
        'name' => 'Tuition Payment Institute',
        'phone' => '01700000021',
        'email' => fake()->unique()->safeEmail(),
        'address' => 'Tuition institute address',
        'is_active' => true,
    ]);
    $branch = Branch::query()->create([
        'institute_id' => $institute->id,
        'code' => 'TBR-'.fake()->unique()->numerify('####'),
        'name' => 'Tuition Branch',
        'phone' => '01700000023',
        'address' => 'Tuition branch address',
        'is_active' => true,
    ]);
    $student = Student::factory()->create([
        'institute_id' => $institute->id,
        'admission_branch_id' => $branch->id,
    ]);
    $course = Course::query()->create([
        'institute_id' => $institute->id,
        'code' => 'TC-'.fake()->unique()->numerify('####'),
        'name' => 'Tuition Course',
        'duration_months' => 12,
        'completion_period_months' => 15,
        'is_active' => true,
        'status' => 'active',
        'is_available_for_enrollment' => true,
    ]);
    $enrollment = Enrollment::query()->create([
        'enrollment_code' => 'TEN-'.fake()->unique()->numerify('####'),
        'student_id' => $student->id,
        'course_id' => $course->id,
        'branch_id' => $branch->id,
        'enrollment_date' => now()->toDateString(),
        'expected_completion_on' => now()->addYear()->toDateString(),
        'completion_deadline_on' => now()->addMonths(15)->toDateString(),
        'status' => 'ongoing',
        'is_active' => true,
    ]);
    $tuition = StudentTuition::query()->create(array_merge([
        'enrollment_id' => $enrollment->id,
        'tuition_number' => 'TT-'.fake()->unique()->numerify('####'),
        'original_amount' => '1000.00',
        'waiver_amount' => '100.00',
        'issued_on' => now()->toDateString(),
        'status' => 'issued',
    ], $overrides));
    $user = User::query()->create([
        'name' => 'Tuition Cashier',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'branch_id' => $branch->id,
    ]);
    PaymentMethod::query()->create([
        'institute_id' => $institute->id,
        'code' => 'CASH-'.fake()->unique()->numerify('####'),
        'name' => 'Cash',
        'is_active' => true,
    ]);
    $account = FinancialAccount::query()->create([
        'institute_id' => $institute->id,
        'branch_id' => $branch->id,
        'code' => 'TCA-'.fake()->unique()->numerify('####'),
        'name' => 'Tuition Cash Drawer',
        'account_type' => 'cash',
        'currency_code' => 'BDT',
        'opening_balance' => '0.00',
        'opening_balance_as_of' => now(),
        'is_active' => true,
    ]);

    return [$institute, $branch, $student, $enrollment, $tuition, $user, $account];
}

it('records confirmed cash against issued tuition and returns the updated due', function (): void {
    [, , , , $tuition, $user, $account] = tuitionPaymentContext();

    $this->actingAs($user)
        ->postJson("/api/v1/student-tuitions/{$tuition->id}/payments", [
            'amount' => '250.00',
            'financial_account_id' => $account->id,
            'idempotency_key' => 'tuition-payment-success-001',
        ])
        ->assertCreated()
        ->assertJsonPath('data.tuition.payable_amount', '1000.00')
        ->assertJsonPath('data.tuition.paid_amount', '250.00')
        ->assertJsonPath('data.tuition.due_amount', '750.00')
        ->assertJsonPath('data.payment.status', 'confirmed');

    // A retry with the same caller key returns the original receipt without duplicating the ledger row.
    $this->actingAs($user)
        ->postJson("/api/v1/student-tuitions/{$tuition->id}/payments", [
            'amount' => '250.00',
            'financial_account_id' => $account->id,
            'idempotency_key' => 'tuition-payment-success-001',
        ])
        ->assertOk()->assertJsonPath('data.idempotent_replay', true);

    expect(StudentTuitionPayment::query()->count())->toBe(1);
});

it('hides another branch tuition from branch users but allows a superuser to collect', function (): void {
    [, , , , $tuition, , $account] = tuitionPaymentContext();
    $otherInstitute = Institute::query()->create([
        'code' => 'OTH-'.fake()->unique()->numerify('####'),
        'name' => 'Other Institute',
        'phone' => '01700000022',
        'email' => fake()->unique()->safeEmail(),
        'address' => 'Other institute address',
        'is_active' => true,
    ]);
    $otherBranch = Branch::query()->create([
        'institute_id' => $otherInstitute->id,
        'code' => 'OBR-'.fake()->unique()->numerify('####'),
        'name' => 'Other Branch',
        'phone' => '01700000024',
        'address' => 'Other branch address',
        'is_active' => true,
    ]);
    $otherUser = User::query()->create([
        'name' => 'Other Branch Cashier',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'branch_id' => $otherBranch->id,
    ]);

    $this->actingAs($otherUser)
        ->postJson("/api/v1/student-tuitions/{$tuition->id}/payments", [
            'amount' => '100.00',
            'financial_account_id' => $account->id,
            'idempotency_key' => 'tuition-payment-cross-branch-001',
        ])
        ->assertNotFound();

    $superUser = User::query()->create([
        'name' => 'Finance Superuser',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'is_super_admin' => true,
    ]);
    $this->actingAs($superUser)
        ->postJson("/api/v1/student-tuitions/{$tuition->id}/payments", [
            'amount' => '100.00',
            'financial_account_id' => $account->id,
            'idempotency_key' => 'tuition-payment-superuser-001',
        ])
        ->assertCreated();
});

it('rejects overpayment and prevents collection against non-issued tuition', function (): void {
    [, , , , $tuition, $user, $account] = tuitionPaymentContext();

    $this->actingAs($user)
        ->postJson("/api/v1/student-tuitions/{$tuition->id}/payments", [
            'amount' => '1000.01',
            'financial_account_id' => $account->id,
            'idempotency_key' => 'tuition-payment-overpay-001',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    $tuition->update(['status' => 'pending']);
    $this->actingAs($user)
        ->postJson("/api/v1/student-tuitions/{$tuition->id}/payments", [
            'amount' => '10.00',
            'financial_account_id' => $account->id,
            'idempotency_key' => 'tuition-payment-pending-001',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tuition']);

    expect(StudentTuitionPayment::query()->count())->toBe(0);
});

it('requires authentication and returns only branch-compatible cash accounts in options', function (): void {
    [, , , , $tuition, $user, $account] = tuitionPaymentContext();

    $this->getJson("/api/v1/student-tuitions/{$tuition->id}/payment-options")
        ->assertUnauthorized();

    $this->actingAs($user)
        ->getJson("/api/v1/student-tuitions/{$tuition->id}/payment-options")
        ->assertOk()
        ->assertJsonPath('data.cash_accounts.0.id', $account->id);
});
