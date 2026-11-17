<?php

namespace App\Modules\Academy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use Inertia\Inertia;
use Inertia\Response;

/** Provides the reference lookups required to create Academy catalog records. */
class AcademyManagementPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('academy/management', [
            'institutes' => Institute::query()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'institute_id', 'name']),
        ]);
    }
}
