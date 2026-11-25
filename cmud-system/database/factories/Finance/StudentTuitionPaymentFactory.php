<?php

namespace Database\Factories\Finance;

use App\Models\Academy\Enrollment;
use App\Models\Finance\FinancialAccount;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Institute\Institute;
use App\Models\Security\User;
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
            'enrollment_id' => Enrollment::factory()->state([
                'status' => 'ongoing',
                'tuition_number' => fake()->unique()->bothify('FACTORY-TUITION-####??'),
                'tuition_original_amount' => '1000.00',
                'tuition_waiver_amount' => '0.00',
                'tuition_issued_on' => now()->toDateString(),
                'tuition_status' => 'issued',
            ]),
            'payment_number' => fake()->unique()->bothify('PAY-####??'),
            'payment_method_id' => fn () => PaymentMethod::query()->value('id')
                ?? PaymentMethod::query()->create([
                    'institute_id' => Institute::query()->value('id')
                        ?? Institute::query()->create(['code' => 'FACTORY-INSTITUTE', 'name' => 'Factory Institute'])->id,
                    'code' => 'factory-cash', 'name' => 'Factory Cash',
                ])->id,
            'financial_account_id' => fn () => FinancialAccount::query()->value('id')
                ?? FinancialAccount::query()->create([
                    'institute_id' => Institute::query()->value('id'),
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
