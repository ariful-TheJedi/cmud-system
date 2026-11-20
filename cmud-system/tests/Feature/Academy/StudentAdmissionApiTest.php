<?php

namespace Tests\Feature\Academy;

use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use App\Models\Security\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAdmissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_admission_creates_a_student_and_initial_course_enrollment(): void
    {
        $institute = Institute::query()->create([
            'code' => 'TEST-INSTITUTE',
            'name' => 'Test Institute',
            'phone' => '01700000001',
            'email' => 'test-institute@example.test',
            'address' => 'Test Institute address',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'institute_id' => $institute->id,
            'code' => 'TEST-DHK',
            'name' => 'Test Dhaka Branch',
            'phone' => '01700000002',
            'address' => 'Test Branch address',
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'institute_id' => $institute->id,
            'code' => 'TEST-COURSE',
            'name' => 'Test Academy Course',
            'duration_months' => 12,
            'completion_period_months' => 15,
            'is_active' => true,
        ]);

        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'code' => 'TEST-BATCH-01',
            'name' => 'Test Batch 01',
            'starts_on' => '2026-12-01',
            'expected_ends_on' => '2027-12-01',
            'status' => 'open',
        ]);

        $user = User::query()->create([
            'name' => 'Test Super Admin',
            'email' => 'test-super-admin@example.test',
            'password' => 'test-password',
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/student/admission', [
            'student_code' => 'STU-TEST-0001',
            'admission_branch_id' => $branch->id,
            'batch_id' => $batch->id,
            'full_name' => 'Test Student',
            'phone_numbers' => ['01712345678', '01812345678'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.student.full_name', 'Test Student')
            ->assertJsonStructure([
                'data' => [
                    'student' => ['student_code', 'full_name'],
                    'enrollment' => ['enrollment_code'],
                ],
            ]);

        $studentCode = $response->json('data.student.student_code');
        $enrollmentCode = $response->json('data.enrollment.enrollment_code');

        $this->assertIsString($studentCode);
        $this->assertStringStartsWith('STU-', $studentCode);
        $this->assertIsString($enrollmentCode);
        $this->assertStringStartsWith('ENR-', $enrollmentCode);

        $student = Student::query()->where('student_code', $studentCode)->firstOrFail();

        $this->assertDatabaseHas('students', [
            'student_code' => $studentCode,
            'institute_id' => $institute->id,
            'admission_branch_id' => $branch->id,
            'full_name' => 'Test Student',
            'phone' => '01712345678',
            'status' => 'active',
        ]);

        $this->assertSame(['01712345678', '01812345678'], $student->phone_numbers);

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'batch_id' => $batch->id,
            'status' => 'enrolled',
        ]);

        $enrollment = Enrollment::query()->where('student_id', $student->id)->firstOrFail();

        $this->assertSame(now()->toDateString(), $enrollment->enrollment_date->toDateString());
        $this->assertSame('2028-03-01', $enrollment->expected_completion_on->toDateString());
        $this->assertSame(1, Student::query()->where('student_code', $studentCode)->count());
        $this->assertSame(1, Enrollment::query()->where('student_id', $student->id)->count());
    }
}
