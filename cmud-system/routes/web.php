<?php

use App\Modules\Academy\Http\Controllers\AcademyManagementPageController;
use App\Modules\Academy\Http\Controllers\StudentAdmissionController;
use App\Modules\Academy\Http\Controllers\StudentAdmissionPageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/pages/dashboard.php';
require __DIR__.'/settings.php';

Route::inertia('/', 'welcome')->name('home');

Route::redirect('/academy', '/academy/students')->name('academy.index');

// academy module
Route::get('/academy/students', fn () => Inertia::render('academy/students'))
    ->name('academy.students.index');

Route::get('/academy/manage', [AcademyManagementPageController::class, 'index'])
    ->name('academy.manage');

Route::inertia('/academy/class-routine', 'academy/class-routine')
    ->name('academy.class-routine');

Route::inertia('/settings/institute', 'settings/institute')
    ->name('settings.institute');

Route::get('/academy/students/admission', [StudentAdmissionPageController::class, 'create'])
    ->name('academy.students.admission.create');

Route::post('/academy/students/admissions', [StudentAdmissionController::class, 'store'])
    ->name('academy.students.admission.store');

Route::fallback(fn () => Inertia::render('errors/404')
    ->toResponse(request())
    ->setStatusCode(404));
