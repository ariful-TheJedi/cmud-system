<?php

namespace Database\Factories\Academy;

use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use App\Models\Academy\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'institute_id' => fn () => Institute::query()->firstOrCreate(
                ['code' => 'FACTORY-INSTITUTE'],
                ['name' => 'Factory Institute', 'is_active' => true],
            )->id,
            'admission_branch_id' => fn (array $attributes) => Branch::query()->firstOrCreate(
                ['institute_id' => $attributes['institute_id'], 'code' => 'FACTORY-BRANCH'],
                ['name' => 'Factory Branch', 'is_active' => true],
            )->id,
            'student_code' => fake()->unique()->bothify('STU-####??'),
            'admission_date' => fake()->date(),
            'full_name' => fake()->name(),
            'father_name' => fake()->name('male'),
            'mother_name' => fake()->name('female'),
            'date_of_birth' => fake()->date(),
            'gender' => fake()->randomElement(['male', 'female']),
            'phone' => fake()->numerify('01#########'),
            'alternative_phone' => null,
            'email' => fake()->unique()->safeEmail(),
            'present_address' => fake()->address(),
            'permanent_address' => fake()->address(),
            'medical_college_id' => null,
            'qualification' => fake()->randomElement(['MBBS', 'BDS']),
            'graduation_year' => fake()->numberBetween(2000, (int) now()->format('Y')),
            'bmdc_number' => fake()->optional()->numerify('A-########'),
            'photo_path' => null,
            'documents' => null,
            'status' => 'active',
            'notes' => null,
        ];
    }

}
