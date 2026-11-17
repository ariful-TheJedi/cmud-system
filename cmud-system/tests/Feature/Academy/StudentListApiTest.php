<?php

namespace Tests\Feature\Academy;

use App\Models\Academy\Student;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentListApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_list_returns_records_and_filter_options_for_the_registry_page(): void
    {
        $institute = Institute::query()->create([
            'code' => 'LIST-INSTITUTE',
            'name' => 'List Test Institute',
            'phone' => '01700000011',
            'email' => 'list-institute@example.test',
            'address' => 'Test address',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'institute_id' => $institute->id,
            'code' => 'LIST-BRANCH',
            'name' => 'List Test Branch',
            'phone' => '01700000012',
            'address' => 'Branch address',
            'is_active' => true,
        ]);

        Student::query()->create([
            'institute_id' => $institute->id,
            'admission_branch_id' => $branch->id,
            'student_code' => 'STU-LIST-0001',
            'admission_date' => '2026-11-01',
            'full_name' => 'Registry Test Student',
            'phone' => '01712345678',
            'phone_numbers' => ['01712345678'],
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/students?page=1&per_page=15&include_filters=1&sort_by=created_at&sort_direction=desc');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.student_code', 'STU-LIST-0001')
            ->assertJsonPath('data.0.full_name', 'Registry Test Student')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('filters.branches.0.id', $branch->id)
            ->assertJsonPath('filters.branches.0.name', 'List Test Branch');
    }
}
