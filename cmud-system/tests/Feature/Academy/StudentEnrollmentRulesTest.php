<?php

namespace Tests\Feature\Academy;

use App\Models\Academy\Batch;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StudentEnrollmentRulesTest extends TestCase
{
    use RefreshDatabase;

    private Institute $institute;

    private Branch $branch;

    private Course $course;

    private Batch $batch;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institute = Institute::query()->create([
            'code' => 'ENROLLMENT-RULES-INSTITUTE',
            'name' => 'Enrollment Rules Institute',
            'phone' => '01700000031',
            'email' => 'enrollment-rules@example.test',
            'address' => 'Test address',
            'is_active' => true,
        ]);

        $this->branch = Branch::query()->create([
            'institute_id' => $this->institute->id,
            'code' => 'ENROLLMENT-RULES-BRANCH',
            'name' => 'Enrollment Rules Branch',
            'phone' => '01700000032',
            'address' => 'Test branch address',
            'is_active' => true,
        ]);

        $this->course = Course::query()->create([
            'institute_id' => $this->institute->id,
            'code' => 'ENROLLMENT-RULES-COURSE',
            'name' => 'Enrollment Rules Course',
            'duration_months' => 12,
            'completion_period_months' => 15,
            'is_active' => true,
        ]);

        $this->batch = Batch::query()->create([
            'course_id' => $this->course->id,
            'branch_id' => $this->branch->id,
            'code' => 'ENROLLMENT-RULES-BATCH',
            'name' => 'Enrollment Rules Batch',
            'starts_on' => '2026-12-01',
            'expected_ends_on' => '2027-12-01',
            'status' => 'open',
        ]);

        $this->student = $this->createStudent('STU-ENROLLMENT-RULES-001');
    }

    public function test_it_enrolls_an_eligible_student_in_an_available_course_batch(): void
    {
        $response = $this->postEnrollment();

        $response
            ->assertCreated()
            ->assertJsonPath('data.enrollment.status', 'enrolled')
            ->assertJsonPath('data.enrollment.branch_id', $this->branch->id);

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'status' => 'enrolled',
        ]);
        $enrollment = Enrollment::query()->where('student_id', $this->student->id)->firstOrFail();
        $this->assertSame('2027-12-01', $enrollment->expected_completion_on->toDateString());
        $this->assertSame('2028-03-01', $enrollment->completion_deadline_on->toDateString());
    }

    public function test_it_allows_a_student_to_enroll_in_multiple_courses(): void
    {
        $otherCourse = Course::query()->create([
            'institute_id' => $this->institute->id,
            'code' => 'ENROLLMENT-RULES-COURSE-OTHER',
            'name' => 'Other Course',
            'duration_months' => 12,
            'completion_period_months' => 15,
            'is_active' => true,
        ]);
        $otherBatch = Batch::query()->create([
            'course_id' => $otherCourse->id,
            'branch_id' => $this->branch->id,
            'code' => 'ENROLLMENT-RULES-BATCH-OTHER',
            'name' => 'Other Course Batch',
            'starts_on' => '2027-01-01',
            'expected_ends_on' => '2028-01-01',
            'status' => 'upcoming',
        ]);

        $this->postEnrollment()->assertCreated();
        $this->postEnrollment($otherCourse, $otherBatch)->assertCreated();

        $this->assertSame(2, $this->student->enrollments()->count());
    }

    public function test_it_rejects_each_active_enrollment_status_as_a_duplicate(): void
    {
        foreach (['enrolled', 'ongoing', 'on_hold'] as $index => $status) {
            $student = $index === 0
                ? $this->student
                : $this->createStudent('STU-ENROLLMENT-RULES-ACTIVE-'.$index);
            $this->createEnrollment('ENR-RULES-ACTIVE-'.$index, $status, $student);

            $this->postEnrollment(null, null, $student)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('course_id');
        }
    }

    public function test_it_allows_a_new_enrollment_after_any_terminal_enrollment_status(): void
    {
        foreach (['completed', 'discontinued', 'cancelled'] as $index => $status) {
            $student = $index === 0
                ? $this->student
                : $this->createStudent('STU-ENROLLMENT-RULES-TERMINAL-'.$index);
            $this->createEnrollment('ENR-RULES-TERMINAL-'.$index, $status, $student);

            $this->postEnrollment(null, null, $student)->assertCreated();
        }
    }

    public function test_a_registered_student_is_eligible_for_enrollment(): void
    {
        $this->student->update(['status' => 'registered']);

        $this->postEnrollment()->assertCreated();
    }

    public function test_it_rejects_deactivated_and_suspended_students(): void
    {
        foreach (['deactivated', 'suspended'] as $status) {
            $this->student->update(['status' => $status]);

            $this->postEnrollment()
                ->assertUnprocessable()
                ->assertJsonValidationErrors('student');
        }

        $this->assertSame(0, $this->student->enrollments()->count());
    }

    public function test_it_rejects_an_inactive_course(): void
    {
        $this->course->update(['is_active' => false]);

        $this->postEnrollment()
            ->assertUnprocessable()
            ->assertJsonValidationErrors('course_id');
    }

    public function test_it_rejects_completed_and_cancelled_batches(): void
    {
        foreach (['completed', 'cancelled', 'inactive'] as $status) {
            $this->batch->update(['status' => $status]);

            $this->postEnrollment()
                ->assertUnprocessable()
                ->assertJsonValidationErrors('batch_id');
        }
    }

    public function test_it_rejects_enrollment_when_a_configured_batch_capacity_is_full(): void
    {
        $this->batch->update(['capacity' => 1]);
        $anotherStudent = $this->createStudent('STU-ENROLLMENT-RULES-002');
        $this->createEnrollment(
            'ENR-RULES-CAPACITY-001',
            'ongoing',
            $anotherStudent,
        );

        $this->postEnrollment()
            ->assertUnprocessable()
            ->assertJsonValidationErrors('batch_id');
    }

    public function test_it_rejects_a_batch_that_does_not_belong_to_the_selected_course(): void
    {
        $otherCourse = Course::query()->create([
            'institute_id' => $this->institute->id,
            'code' => 'ENROLLMENT-RULES-COURSE-OTHER',
            'name' => 'Other Course',
            'duration_months' => 12,
            'completion_period_months' => 15,
            'is_active' => true,
        ]);
        $this->batch->update(['course_id' => $otherCourse->id]);

        $this->postEnrollment()
            ->assertUnprocessable()
            ->assertJsonValidationErrors('batch_id');
    }

    public function test_it_rejects_a_course_from_another_student_institute(): void
    {
        $otherInstitute = Institute::query()->create([
            'code' => 'ENROLLMENT-RULES-OTHER-INSTITUTE',
            'name' => 'Other Institute',
            'phone' => '01700000033',
            'email' => 'other-enrollment-rules@example.test',
            'address' => 'Other test address',
            'is_active' => true,
        ]);
        $otherCourse = Course::query()->create([
            'institute_id' => $otherInstitute->id,
            'code' => 'ENROLLMENT-RULES-COURSE-OTHER-INSTITUTE',
            'name' => 'Other Institute Course',
            'duration_months' => 12,
            'completion_period_months' => 15,
            'is_active' => true,
        ]);
        $this->batch->update(['course_id' => $otherCourse->id]);

        $this->postEnrollment($otherCourse, $this->batch)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('course_id');
    }

    public function test_it_rejects_a_batch_branch_from_another_institute(): void
    {
        $otherInstitute = Institute::query()->create([
            'code' => 'ENROLLMENT-RULES-OTHER-INSTITUTE',
            'name' => 'Other Institute',
            'phone' => '01700000033',
            'email' => 'other-enrollment-rules@example.test',
            'address' => 'Other test address',
            'is_active' => true,
        ]);
        $otherBranch = Branch::query()->create([
            'institute_id' => $otherInstitute->id,
            'code' => 'ENROLLMENT-RULES-OTHER-BRANCH',
            'name' => 'Other Institute Branch',
            'phone' => '01700000034',
            'address' => 'Other branch address',
            'is_active' => true,
        ]);
        $this->batch->update(['branch_id' => $otherBranch->id]);

        $this->postEnrollment()
            ->assertUnprocessable()
            ->assertJsonValidationErrors('batch_id');
    }

    public function test_initial_admission_uses_the_same_course_availability_rules(): void
    {
        $this->course->update(['is_active' => false]);

        $this->postJson('/api/v1/student/admission', [
            'student_code' => 'STU-ENROLLMENT-RULES-NEW',
            'admission_branch_id' => $this->branch->id,
            'batch_id' => $this->batch->id,
            'full_name' => 'New Enrollment Rules Student',
            'phone_numbers' => ['01712345678'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('course_id');

        $this->assertDatabaseMissing('students', [
            'student_code' => 'STU-ENROLLMENT-RULES-NEW',
        ]);
    }

    public function test_batch_catalog_displays_enrolled_student_count_and_course_assignment(): void
    {
        $this->postEnrollment()->assertCreated();

        $batchResponse = $this->postJson('/api/v1/academy-catalog/batches', [
            'course_id' => $this->course->id,
            'branch_id' => $this->branch->id,
            'code' => 'ENROLLMENT-RULES-ASSIGNED-BATCH',
            'name' => 'Assigned Course Batch',
            'starts_on' => '2026-12-01',
            'expected_ends_on' => '2027-12-01',
            'capacity' => null,
            'status' => 'open',
        ])->assertCreated()
            ->assertJsonPath('data.course_id', $this->course->id)
            ->assertJsonPath('data.capacity', null);

        $response = $this->getJson('/api/v1/academy-catalog/batches?per_page=15')->assertOk();

        $response->assertJsonFragment([
            'id' => $this->batch->id,
            'course_id' => $this->course->id,
            'enrolled_students_count' => 1,
        ]);
        $this->getJson('/api/v1/academy-catalog/batches/'.$batchResponse->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.course.id', $this->course->id)
            ->assertJsonPath('data.enrolled_students_count', 0);
    }

    public function test_an_unused_batch_can_be_deleted_but_an_enrolled_batch_is_protected(): void
    {
        $this->postJson('/api/v1/academy-catalog/batches', [
            'course_id' => $this->course->id,
            'branch_id' => $this->branch->id,
            'code' => 'ENROLLMENT-RULES-DELETE-BATCH',
            'name' => 'Unused Batch',
            'starts_on' => '2026-12-01',
            'expected_ends_on' => '2027-12-01',
            'status' => 'open',
        ])->assertCreated();

        $deletableBatch = Batch::query()->where('code', 'ENROLLMENT-RULES-DELETE-BATCH')->firstOrFail();
        $this->deleteJson('/api/v1/academy-catalog/batches/'.$deletableBatch->id)->assertOk();
        $this->assertDatabaseMissing('batches', ['id' => $deletableBatch->id]);

        $this->postEnrollment()->assertCreated();
        $this->deleteJson('/api/v1/academy-catalog/batches/'.$this->batch->id)
            ->assertConflict()
            ->assertJsonPath('message', 'This batch is in use and cannot be deleted.');
        $this->assertDatabaseHas('batches', ['id' => $this->batch->id]);
    }

    private function postEnrollment(
        ?Course $course = null,
        ?Batch $batch = null,
        ?Student $student = null,
    ): TestResponse {
        $student ??= $this->student;

        return $this->postJson(
            '/api/v1/students/'.$student->student_code.'/enrollments',
            [
                'course_id' => ($course ?? $this->course)->id,
                'batch_id' => ($batch ?? $this->batch)->id,
                'enrollment_date' => '2026-11-20',
            ],
        );
    }

    private function createStudent(string $studentCode): Student
    {
        return Student::query()->create([
            'institute_id' => $this->institute->id,
            'admission_branch_id' => $this->branch->id,
            'student_code' => $studentCode,
            'admission_date' => '2026-01-01',
            'full_name' => 'Enrollment Rules Student',
            'phone' => '01712345000',
            'status' => 'active',
        ]);
    }

    private function createEnrollment(
        string $enrollmentCode,
        string $status,
        ?Student $student = null,
    ): void {
        ($student ?? $this->student)->enrollments()->create([
            'enrollment_code' => $enrollmentCode,
            'course_id' => $this->course->id,
            'branch_id' => $this->branch->id,
            'batch_id' => $this->batch->id,
            'enrollment_date' => '2026-01-01',
            'starts_on' => '2026-12-01',
            'expected_completion_on' => '2028-03-01',
            'completion_deadline_on' => '2028-03-01',
            'completed_on' => $status === 'completed' ? '2028-03-01' : null,
            'status' => $status,
            'is_active' => in_array($status, ['enrolled', 'ongoing', 'on_hold'], true),
        ]);
    }
}
