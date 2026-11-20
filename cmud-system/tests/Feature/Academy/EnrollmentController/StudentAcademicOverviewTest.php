<?php

namespace Tests\Feature\Academy\EnrollmentController;

use Tests\Support\Academy\EnrollmentController\StudentOverviewTestCase;

class StudentAcademicOverviewTest extends StudentOverviewTestCase
{
    public function test_student_academic_overview_keeps_multiple_enrollments_and_exam_attempts_independent(): void
    {
        $course = $this->createCourse('ACADEMIC-A');
        $batch = $this->createBatch($course, 'ACADEMIC-A');
        $enrollment = $this->createEnrollment($course, $batch, 'ACADEMIC-A', 'ongoing');
        $otherCourse = $this->createCourse('ACADEMIC-B');
        $otherBatch = $this->createBatch($otherCourse, 'ACADEMIC-B');
        $otherEnrollment = $this->createEnrollment($otherCourse, $otherBatch, 'ACADEMIC-B', 'completed');

        $firstStage = $this->createCourseStage($course, 'ACADEMIC-A-1', 1);
        $secondStage = $this->createCourseStage($course, 'ACADEMIC-A-2', 2);
        $completedRecord = $this->createStageRecord($enrollment, $firstStage, $batch, 'Completed stage', 'completed');
        $ongoingRecord = $this->createStageRecord($enrollment, $secondStage, $batch, 'Ongoing stage', 'ongoing');

        $this->createExam($completedRecord, 'Final examination', 1, 'appeared', 'failed');
        $passedExam = $this->createExam($completedRecord, 'Final examination', 2, 'appeared', 'passed');
        $passedExam->update([
            'total_marks' => '100.00',
            'obtained_marks' => '82.50',
            'grade' => 'A',
            'result_published_on' => '2025-09-10',
            'result_file_path' => 'exams/final-result.pdf',
            'remarks' => 'Passed on the second attempt.',
        ]);
        $this->createExam($ongoingRecord, 'Practical examination', 1, 'scheduled', 'pending');

        $this->getJson('/api/v1/students/'.$this->student->student_code.'/academic')
            ->assertOk()
            ->assertJsonPath('data.student_code', $this->student->student_code)
            ->assertJsonCount(2, 'data.enrollments')
            ->assertJsonPath('data.enrollments.0.enrollment_code', $otherEnrollment->enrollment_code)
            ->assertJsonPath('data.enrollments.0.status', 'completed')
            ->assertJsonPath('data.enrollments.1.enrollment_code', $enrollment->enrollment_code)
            ->assertJsonPath('data.enrollments.1.status', 'ongoing')
            ->assertJsonPath('data.enrollments.1.progress.completed_stages', 1)
            ->assertJsonPath('data.enrollments.1.progress.total_stages', 2)
            ->assertJsonPath('data.enrollments.1.progress.percentage', 50)
            ->assertJsonCount(2, 'data.enrollments.1.stages.0.exams')
            ->assertJsonPath('data.enrollments.1.stages.0.exams.1.attempt_number', 2)
            ->assertJsonPath('data.enrollments.1.stages.0.exams.1.obtained_marks', '82.50')
            ->assertJsonPath('data.enrollments.1.stages.0.exams.1.grade', 'A')
            ->assertJsonPath('data.enrollments.1.stages.0.exams.1.result_published_on', '2025-09-10')
            ->assertJsonPath('data.enrollments.1.stages.0.exams.1.result_file_path', 'exams/final-result.pdf');
    }

    public function test_enrollment_academic_endpoint_returns_only_the_requested_enrollment(): void
    {
        $course = $this->createCourse('ACADEMIC-DETAIL');
        $batch = $this->createBatch($course, 'ACADEMIC-DETAIL');
        $enrollment = $this->createEnrollment($course, $batch, 'ACADEMIC-DETAIL');
        $stage = $this->createCourseStage($course, 'ACADEMIC-DETAIL', 1);
        $this->createStageRecord($enrollment, $stage, $batch, 'Pending stage', 'pending');

        $this->getJson('/api/v1/enrollments/'.$enrollment->id.'/academic')
            ->assertOk()
            ->assertJsonPath('data.enrollment.enrollment_code', $enrollment->enrollment_code)
            ->assertJsonCount(1, 'data.stages');
    }
}
