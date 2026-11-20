<?php

namespace App\Modules\Academy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academy\Services\AcademyPageDataService;
use Inertia\Inertia;
use Inertia\Response;

class AcademyManagementPageController extends Controller
{
    public function index(AcademyPageDataService $pages): Response
    {
        return Inertia::render('academy/management', $pages->managementPageData());
    }
}
