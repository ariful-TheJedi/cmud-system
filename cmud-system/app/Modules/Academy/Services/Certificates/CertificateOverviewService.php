<?php

namespace App\Modules\Academy\Services\Certificates;

use App\Models\Academy\Certificate;
use App\Models\Academy\CertificateReissueRequest;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CertificateOverviewService
{
    /** @return array<int, array<string, mixed>> */
    public function forEnrollment(Enrollment $enrollment): array
    {
        return $this->formatCertificates(
            $enrollment->certificates()
                ->with([
                    'certificateTemplate:id,name',
                    'reissueRequests.replacementCertificate:id,certificate_number,status',
                ])
                ->orderBy('id')
                ->get(),
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function forStudent(Student $student): array
    {
        $certificates = Certificate::query()
            ->whereHas('enrollment', fn ($query) => $query->where('student_id', $student->id))
            ->with([
                'certificateTemplate:id,name',
                'enrollment:id,enrollment_code,course_id',
                'reissueRequests.replacementCertificate:id,certificate_number,status',
            ])
            ->orderByDesc('id')
            ->get();

        return $this->formatCertificates($certificates, true);
    }

    /** @return array<string, mixed> */
    public function find(Certificate $certificate): array
    {
        $certificate->load([
            'certificateTemplate:id,name',
            'enrollment:id,enrollment_code,student_id,course_id',
            'reissueRequests.replacementCertificate:id,certificate_number,status',
        ]);

        return $this->formatCertificate($certificate, true);
    }

    /**
     * @param  Collection<int, Certificate>  $certificates
     * @return array<int, array<string, mixed>>
     */
    private function formatCertificates(Collection $certificates, bool $includeEnrollment = false): array
    {
        return $certificates
            ->map(fn (Certificate $certificate): array => $this->formatCertificate($certificate, $includeEnrollment))
            ->all();
    }

    /** @return array<string, mixed> */
    private function formatCertificate(Certificate $certificate, bool $includeEnrollment): array
    {
        $data = [
            'id' => $certificate->id,
            'certificate_number' => $certificate->certificate_number,
            'name' => $certificate->certificateTemplate?->name,
            'recipient_name' => $certificate->recipient_name,
            'course_title' => $certificate->course_title,
            'status' => $certificate->getRawOriginal('status'),
            'issued_on' => $certificate->getRawOriginal('issued_on'),
            'certificate_file_path' => $certificate->certificate_file_path,
            'printed_at' => $this->formatDateTime($certificate->getRawOriginal('printed_at')),
            'delivered_at' => $this->formatDateTime($certificate->getRawOriginal('delivered_at')),
            'received_by' => $certificate->received_by,
            'delivery_method' => $certificate->delivery_method,
            'delivery_reference' => $certificate->delivery_reference,
            'reissue_history' => $certificate->reissueRequests
                ->sortBy('requested_at')
                ->values()
                ->map(fn (CertificateReissueRequest $request): array => [
                    'id' => $request->id,
                    'status' => $request->getRawOriginal('status'),
                    'reason' => $request->reason,
                    'requested_at' => $request->getRawOriginal('requested_at') === null
                        ? null
                        : Carbon::parse((string) $request->getRawOriginal('requested_at'))->toIso8601String(),
                    'decision_at' => $request->getRawOriginal('decision_at') === null
                        ? null
                        : Carbon::parse((string) $request->getRawOriginal('decision_at'))->toIso8601String(),
                    'decision_note' => $request->decision_note,
                    'replacement_certificate' => $request->replacementCertificate ? [
                        'id' => $request->replacementCertificate->id,
                        'certificate_number' => $request->replacementCertificate->certificate_number,
                        'status' => $request->replacementCertificate->getRawOriginal('status'),
                    ] : null,
                ])
                ->all(),
        ];

        if ($includeEnrollment) {
            $data['enrollment'] = $certificate->enrollment ? [
                'id' => $certificate->enrollment->id,
                'enrollment_code' => $certificate->enrollment->enrollment_code,
                'course_id' => $certificate->enrollment->course_id,
            ] : null;
        }

        return $data;
    }

    private function formatDateTime(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse((string) $value)->toIso8601String();
    }
}
