<?php

namespace Database\Factories\Finance;

use App\Models\Finance\FinancialAccount;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\StudentTuition;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Security\User;
use App\Models\Academy\Enrollment;
use App\Models\Academy\CoursePriceVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentTuitionPayment>
 */
class StudentTuitionPaymentFactory extends Factory
{
    protected $model = StudentTuitionPayment::class;

    public function definition(): array
    {
        return [
            'student_tuition_id' => fn () => StudentTuition::query()->firstOrCreate(
                ['tuition_number' => 'FACTORY-TUITION'],
                [
                    'enrollment_id' => Enrollment::query()->value('id')
                        ?? Enrollment::query()->create([
                            'enrollment_code' => 'FACTORY-ENROLLMENT',
                            'student_id' => \App\Models\Academy\Student::query()->value('id')
                                ?? \App\Models\Academy\Student::factory()->create()->id,
                            'course_id' => \App\Models\Academy\Course::query()->value('id')
                                ?? \App\Models\Academy\Course::query()->create([
                                    'institute_id' => \App\Models\Institute\Institute::query()->value('id')
                                        ?? \App\Models\Institute\Institute::query()->create(['code' => 'FACTORY-INSTITUTE', 'name' => 'Factory Institute'])->id,
                                    'code' => 'FACTORY-COURSE', 'name' => 'Factory Course', 'duration_months' => 12,
                                    'completion_period_months' => 15, 'is_active' => true,
                                ])->id,
                            'branch_id' => \App\Models\Institute\Branch::query()->value('id')
                                ?? \App\Models\Institute\Branch::query()->create([
                                    'institute_id' => \App\Models\Institute\Institute::query()->value('id'),
                                    'code' => 'FACTORY-BRANCH', 'name' => 'Factory Branch',
                                ])->id,
                            'enrollment_date' => now()->toDateString(),
                            'expected_completion_on' => now()->addYear()->toDateString(),
                            'completion_deadline_on' => now()->addMonths(15)->toDateString(),
                            'status' => 'active', 'is_active' => true,
                        ])->id,
                    'course_price_version_id' => CoursePriceVersion::query()->value('id')
                        ?? CoursePriceVersion::query()->create([
                            'course_id' => \App\Models\Academy\Course::query()->value('id'),
                            'branch_id' => \App\Models\Institute\Branch::query()->value('id'),
                            'version_number' => 1, 'amount' => 1000,
                            'effective_from' => now()->toDateString(), 'status' => 'active',
                        ])->id,
                    'original_amount' => 1000,
                    'waiver_amount' => 0,
                    'issued_on' => now()->toDateString(),
                    'status' => 'issued',
                ],
            )->id,
            'payment_number' => fake()->unique()->bothify('PAY-####??'),
            'payment_method_id' => fn () => PaymentMethod::query()->value('id')
                ?? PaymentMethod::query()->create([
                    'institute_id' => \App\Models\Institute\Institute::query()->value('id')
                        ?? \App\Models\Institute\Institute::query()->create(['code' => 'FACTORY-INSTITUTE', 'name' => 'Factory Institute'])->id,
                    'code' => 'factory-cash', 'name' => 'Factory Cash',
                ])->id,
            'financial_account_id' => fn () => FinancialAccount::query()->value('id')
                ?? FinancialAccount::query()->create([
                    'institute_id' => \App\Models\Institute\Institute::query()->value('id'),
                    'code' => 'FACTORY-CASH', 'name' => 'Factory Cash Account', 'account_type' => 'cash',
                    'opening_balance_as_of' => now(),
                ])->id,
            'amount' => fake()->randomFloat(2, 100, 50000),
            'paid_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'transaction_reference' => fake()->optional()->uuid(),
            'payer_phone' => fake()->numerify('01#########'),
            'proof_file_path' => null,
            'status' => 'pending',
            'verification_method' => null,
            'verified_at' => null,
            'verified_by' => null,
            'verification_reference' => null,
            'verification_response' => null,
            'rejection_reason' => null,
            'recorded_by' => User::factory(),
            'notes' => null,
        ];
    }
}
