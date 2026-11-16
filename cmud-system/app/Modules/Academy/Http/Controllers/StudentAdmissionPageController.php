<?php

namespace App\Modules\Academy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Batch;
use App\Models\Academy\MedicalCollege;
use App\Models\Institute\Branch;
use Inertia\Inertia;
use Inertia\Response;

class StudentAdmissionPageController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('academy/student-admission', [
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
        ]);
    }
}
