<?php

namespace App\Modules\Academy\Services\Shared;

use App\Models\Academy\Batch;
use App\Models\Academy\MedicalCollege;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use Illuminate\Database\Eloquent\Collection;

class AcademyPageDataService
{
    /** @return array{institutes: Collection<int, Institute>, branches: Collection<int, Branch>} */
    public function managementPageData(): array
    {
        return [
            'institutes' => Institute::query()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'institute_id', 'name']),
        ];
    }

    /**
     * @return array{
     *     branches: Collection<int, Branch>,
     *     batches: Collection<int, Batch>,
     *     medicalColleges: Collection<int, MedicalCollege>
     * }
     */
    public function admissionPageData(): array
    {
        return [
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'institute_id', 'name']),
            'batches' => Batch::query()
                ->with(['branch:id,institute_id,name', 'course:id,name'])
                ->orderBy('starts_on')
                ->get(['id', 'course_id', 'branch_id', 'name', 'starts_on', 'status']),
            'medicalColleges' => MedicalCollege::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
