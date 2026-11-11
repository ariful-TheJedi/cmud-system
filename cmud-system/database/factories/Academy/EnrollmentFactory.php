<?php

namespace Database\Factories\Academy;

use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Institute\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        $enrollmentDate = fake()->dateTimeBetween('-2 years', 'now');
        $expectedCompletion = (clone $enrollmentDate)->modify('+12 months');

        return [
            'enrollment_code' => fake()->unique()->bothify('ENR-####??'),
            'student_id' => fn () => Student::query()->firstOrCreate(
                ['student_code' => 'FACTORY-STUDENT'],
                [
                    'institute_id' => \App\Models\Institute\Institute::query()->value('id')
                        ?? \App\Models\Institute\Institute::query()->create(['code' => 'FACTORY-INSTITUTE', 'name' => 'Factory Institute'])->id,
                    'admission_branch_id' => \App\Models\Institute\Branch::query()->value('id')
                        ?? \App\Models\Institute\Branch::query()->create(['institute_id' => \App\Models\Institute\Institute::query()->value('id'), 'code' => 'FACTORY-BRANCH', 'name' => 'Factory Branch'])->id,
                    'admission_date' => now()->toDateString(),
                    'full_name' => fake()->name(),
                    'phone' => fake()->numerify('01#########'),
                    'present_address' => fake()->address(),
                    'qualification' => 'MBBS',
                    'status' => 'active',
                ],
            )->id,
            'course_id' => fn () => Course::query()->firstOrCreate(
                ['code' => 'FACTORY-COURSE'],
                [
                    'institute_id' => \App\Models\Institute\Institute::query()->value('id')
                        ?? \App\Models\Institute\Institute::query()->create(['code' => 'FACTORY-INSTITUTE', 'name' => 'Factory Institute'])->id,
                    'name' => 'Factory Course',
                    'duration_months' => 12,
                    'completion_period_months' => 15,
                    'is_active' => true,
                ],
            )->id,
            'branch_id' => fn () => Branch::query()->value('id')
                ?? Branch::query()->create([
                    'institute_id' => \App\Models\Institute\Institute::query()->value('id')
                        ?? \App\Models\Institute\Institute::query()->create(['code' => 'FACTORY-INSTITUTE', 'name' => 'Factory Institute'])->id,
                    'code' => 'FACTORY-BRANCH',
                    'name' => 'Factory Branch',
                ])->id,
            'batch_id' => null,
            'enrollment_date' => $enrollmentDate->format('Y-m-d'),
            'starts_on' => $enrollmentDate->format('Y-m-d'),
            'expected_completion_on' => $expectedCompletion->format('Y-m-d'),
            'completion_deadline_on' => (clone $expectedCompletion)->modify('+3 months')->format('Y-m-d'),
            'completed_on' => null,
            'status' => 'active',
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function inBatch(): static
    {
        return $this->state(fn (): array => [
            'batch_id' => Batch::factory(),
        ]);
    }
}
