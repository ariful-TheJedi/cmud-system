<?php

namespace App\Modules\Academy\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Modules\Academy\Services\Shared\AcademyPageDataService;
use Inertia\Inertia;
use Inertia\Response;

class AcademyManagementPageController extends Controller
{
    public function courses(AcademyPageDataService $pages): Response
    {
        return Inertia::render('academy/management/courses', $pages->managementPageData());
    }

    public function batches(AcademyPageDataService $pages): Response
    {
        return Inertia::render('academy/management/batches', $pages->managementPageData());
    }

    public function subjects(AcademyPageDataService $pages): Response
    {
        return Inertia::render('academy/management/subjects', $pages->managementPageData());
    }

    public function teachers(AcademyPageDataService $pages): Response
    {
        return Inertia::render('academy/management/teachers', $pages->managementPageData());
    }
}
