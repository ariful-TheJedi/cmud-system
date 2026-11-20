<?php

namespace Tests\Feature\Academy\EnrollmentController;

use App\Models\Academy\CertificateReissueRequest;
use Tests\Support\Academy\EnrollmentController\StudentOverviewTestCase;

class EnrollmentCertificateOverviewTest extends StudentOverviewTestCase
{
    public function test_certificate_eligibility_requires_completed_academic_progress(): void
    {
        $course = $this->createCourse('CERTIFICATE-ELIGIBILITY');
        $batch = $this->createBatch($course, 'CERTIFICATE-ELIGIBILITY');
        $enrollment = $this->createEnrollment($course, $batch, 'CERTIFICATE-ELIGIBILITY');
        $stage = $this->createCourseStage($course, 'CERTIFICATE-ELIGIBILITY', 1);
        $stageRecord = $this->createStageRecord($enrollment, $stage, $batch, 'Required stage', 'pending');

        $this->getJson('/api/v1/enrollments/'.$enrollment->id.'/certificate-eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.status', 'not_eligible')
            ->assertJsonPath('data.outstanding_requirements.0', 'Stage '.$stage->name.' is not completed.')
            ->assertJsonPath(
                'data.outstanding_requirements.1',
                'The enrollment must be completed before a certificate can be issued.',
            );

        $stageRecord->update(['status' => 'completed', 'completed_on' => '2025-08-01']);
        $enrollment->update(['status' => 'completed']);

        $this->getJson('/api/v1/enrollments/'.$enrollment->id.'/certificate-eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.status', 'eligible')
            ->assertJsonCount(0, 'data.outstanding_requirements');
    }

    public function test_certificate_endpoints_return_multiple_independent_statuses_and_reissue_history(): void
    {
        $course = $this->createCourse('CERTIFICATE-A');
        $batch = $this->createBatch($course, 'CERTIFICATE-A');
        $enrollment = $this->createEnrollment($course, $batch, 'CERTIFICATE-A');
        $template = $this->createCertificateTemplate($course, 'CERTIFICATE-A');
        $original = $this->createCertificate($enrollment, $template, 'ORIGINAL', 'issued');
        $replacement = $this->createCertificate($enrollment, $template, 'REPLACEMENT', 'processing');
        $this->createCertificate($enrollment, $template, 'PENDING', 'pending');
        $delivered = $this->createCertificate($enrollment, $template, 'DELIVERED', 'delivered');
        $delivered->update([
            'printed_at' => '2025-10-01 10:00:00',
            'received_by' => 'Overview Test Student',
            'delivery_method' => 'in_person',
            'delivery_reference' => 'RECEIPT-CERT-01',
        ]);
        CertificateReissueRequest::query()->create([
            'original_certificate_id' => $original->id,
            'replacement_certificate_id' => $replacement->id,
            'requested_by' => $this->user->id,
            'reason' => 'Original certificate was damaged.',
            'requested_at' => '2025-11-01 10:00:00',
            'decision_at' => '2025-11-02 10:00:00',
            'status' => 'processing',
            'decision_note' => 'Replacement is being prepared.',
        ]);

        $this->getJson('/api/v1/enrollments/'.$enrollment->id.'/certificates')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.name', $template->name)
            ->assertJsonPath('data.0.status', 'issued')
            ->assertJsonPath('data.0.reissue_history.0.replacement_certificate.certificate_number', $replacement->certificate_number)
            ->assertJsonPath('data.3.status', 'delivered')
            ->assertJsonPath('data.3.received_by', 'Overview Test Student')
            ->assertJsonPath('data.3.delivery_method', 'in_person')
            ->assertJsonPath('data.3.delivery_reference', 'RECEIPT-CERT-01')
            ->assertJsonPath('data.3.printed_at', '2025-10-01T10:00:00+00:00')
            ->assertJsonPath('data.3.delivered_at', '2025-10-02T12:00:00+00:00');

        $this->getJson('/api/v1/students/'.$this->student->student_code.'/certificates')
            ->assertOk()
            ->assertJsonPath('data.0.enrollment.enrollment_code', $enrollment->enrollment_code);

        $this->getJson('/api/v1/certificates/'.$original->id)
            ->assertOk()
            ->assertJsonPath('data.certificate_number', $original->certificate_number)
            ->assertJsonPath('data.reissue_history.0.status', 'processing');
    }
}
