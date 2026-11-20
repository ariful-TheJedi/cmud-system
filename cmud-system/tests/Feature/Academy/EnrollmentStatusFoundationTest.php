<?php

namespace Tests\Feature\Academy;

use App\Enums\Academy\EnrollmentStatus;
use App\Enums\Academy\StudentStatus;
use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnrollmentStatusFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_enums_expose_the_approved_student_and_enrollment_values(): void
    {
        $this->assertSame(
            ['registered', 'active', 'deactivated', 'suspended'],
            array_map(fn (StudentStatus $status): string => $status->value, StudentStatus::cases()),
        );
        $this->assertSame(
            ['enrolled', 'ongoing', 'on_hold', 'completed', 'discontinued', 'cancelled'],
            array_map(fn (EnrollmentStatus $status): string => $status->value, EnrollmentStatus::cases()),
        );
    }

    public function test_multiple_enrollments_keep_independent_statuses(): void
    {
        [$student, $course, $batch, $branch] = $this->createAcademicRecords();

        $completed = $student->enrollments()->create([
            'enrollment_code' => 'ENR-STATUS-COMPLETED',
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'batch_id' => $batch->id,
            'enrollment_date' => '2026-01-10',
            'starts_on' => '2026-02-01',
            'expected_completion_on' => '2026-12-01',
            'completion_deadline_on' => '2027-03-01',
            'status' => EnrollmentStatus::Completed,
            'is_active' => false,
        ]);

        $ongoing = $student->enrollments()->create([
            'enrollment_code' => 'ENR-STATUS-ONGOING',
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'batch_id' => $batch->id,
            'enrollment_date' => '2026-03-10',
            'starts_on' => '2026-04-01',
            'expected_completion_on' => '2027-03-01',
            'completion_deadline_on' => '2027-06-01',
            'status' => EnrollmentStatus::Ongoing,
            'is_active' => true,
        ]);

        $this->assertSame('completed', $completed->fresh()->status->value);
        $this->assertSame('ongoing', $ongoing->fresh()->status->value);
        $this->assertSame(2, Enrollment::query()->where('student_id', $student->id)->count());
    }

    public function test_invalid_enrollment_status_is_rejected_by_the_database(): void
    {
        [$student, $course, $batch, $branch] = $this->createAcademicRecords();

        $this->expectException(QueryException::class);

        DB::table('enrollments')->insert([
            'enrollment_code' => 'ENR-STATUS-INVALID',
            'student_id' => $student->id,
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'batch_id' => $batch->id,
            'enrollment_date' => '2026-01-10',
            'starts_on' => '2026-02-01',
            'expected_completion_on' => '2026-12-01',
            'completion_deadline_on' => '2027-03-01',
            'status' => 'invalid_status',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{Student, Course, Batch, Branch} */
    private function createAcademicRecords(): array
    {
        $institute = Institute::query()->create([
            'code' => 'STATUS-INSTITUTE',
            'name' => 'Status Test Institute',
            'phone' => '01700000021',
            'email' => 'status-institute@example.test',
            'address' => 'Test address',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'institute_id' => $institute->id,
            'code' => 'STATUS-BRANCH',
            'name' => 'Status Test Branch',
            'phone' => '01700000022',
            'address' => 'Branch address',
            'is_active' => true,
        ]);
        $course = Course::query()->create([
            'institute_id' => $institute->id,
            'code' => 'STATUS-COURSE',
            'name' => 'Status Test Course',
            'duration_months' => 12,
            'completion_period_months' => 15,
            'is_active' => true,
        ]);
        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'code' => 'STATUS-BATCH',
            'name' => 'Status Test Batch',
            'starts_on' => '2026-02-01',
            'expected_ends_on' => '2026-12-01',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'institute_id' => $institute->id,
            'admission_branch_id' => $branch->id,
            'student_code' => 'STU-STATUS-0001',
            'admission_date' => '2026-01-10',
            'full_name' => 'Status Test Student',
            'phone' => '01712345000',
            'phone_numbers' => ['01712345000'],
            'status' => StudentStatus::Active,
        ]);

        return [$student, $course, $batch, $branch];
    }
}
