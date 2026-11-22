<?php

namespace App\Modules\Academy\Controllers\Admissions;

use App\Http\Controllers\Controller;
use App\Modules\Academy\Services\Shared\AcademyPageDataService;
use Inertia\Inertia;
use Inertia\Response;

class StudentAdmissionPageController extends Controller
{
    public function create(AcademyPageDataService $pages): Response
    {
        return Inertia::render('academy/student-admission', $pages->admissionPageData());
    }
}
