<?php

namespace App\Modules\Academy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $enrollment = $this->latestEnrollment;

        return [
            'id' => $this->id,
            'student_code' => $this->student_code,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'phone_numbers' => $this->phone_numbers,
            'status' => $this->status,
            'admission_date' => $this->admission_date?->toDateString(),
            'admission_branch' => $this->whenLoaded('admissionBranch', fn () => [
                'id' => $this->admissionBranch?->id,
                'name' => $this->admissionBranch?->name,
            ]),
            'latest_enrollment' => $enrollment ? [
                'id' => $enrollment->id,
                'enrollment_code' => $enrollment->enrollment_code,
                'status' => $enrollment->status,
                'enrollment_date' => $enrollment->enrollment_date?->toDateString(),
                'course' => $enrollment->course ? [
                    'id' => $enrollment->course->id,
                    'name' => $enrollment->course->name,
                ] : null,
                'branch' => $enrollment->branch ? [
                    'id' => $enrollment->branch->id,
                    'name' => $enrollment->branch->name,
                ] : null,
                'batch' => $enrollment->batch ? [
                    'id' => $enrollment->batch->id,
                    'name' => $enrollment->batch->name,
                ] : null,
            ] : null,
        ];
    }
}
