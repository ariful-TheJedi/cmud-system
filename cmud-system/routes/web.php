<?php

use App\Modules\Academy\Controllers\Admissions\StudentAdmissionController;
use App\Modules\Academy\Controllers\Admissions\StudentAdmissionPageController;
use App\Modules\Academy\Controllers\Catalog\AcademyManagementPageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/pages/dashboard.php';
require app_path('Modules/Setting/routes/web.php');

Route::redirect('/', '/dashboard')->name('home');

Route::inertia('/staff', 'staff')->name('staff.index');

Route::redirect('/academy', '/academy/students')->name('academy.index');

// academy module
Route::get('/academy/students', fn () => Inertia::render('academy/students'))
    ->name('academy.students.index');

Route::inertia('/academy/students/exam-control', 'academy/students/exam-control')
    ->name('academy.students.exam-control');

Route::redirect('/academy/manage', '/academy/manage/courses')
    ->name('academy.manage');

Route::prefix('academy/manage')->name('academy.manage.')->group(function () {
    Route::get('/courses', [AcademyManagementPageController::class, 'courses'])->name('courses');
    Route::get('/batches', [AcademyManagementPageController::class, 'batches'])->name('batches');
    Route::get('/subjects', [AcademyManagementPageController::class, 'subjects'])->name('subjects');
    Route::get('/teachers', [AcademyManagementPageController::class, 'teachers'])->name('teachers');
});

Route::inertia('/academy/certificates', 'academy/certificates')
    ->name('academy.certificates');

Route::inertia('/academy/class-routine', 'academy/class-routine')
    ->name('academy.class-routine');

// Demo billing screens; payment APIs will be integrated separately.
Route::redirect('/billings', '/billings/student-bill')->name('billings.index');
Route::inertia('/billings/student-bill', 'billings/student-bill')->name('billings.student-bill');
Route::inertia('/billings/institute-billing', 'billings/institute-billing')->name('billings.institute-billing');

// Demo finance reports; API integration will follow.
Route::redirect('/finance', '/finance/student-revenue')->name('finance.index');
Route::inertia('/finance/student-revenue', 'finance/student-revenue')->name('finance.student-revenue');
Route::inertia('/finance/institute-expenditure', 'finance/institute-expenditure')->name('finance.institute-expenditure');

Route::inertia('/settings/institute', 'settings/institute')
    ->name('settings.institute');

Route::get('/academy/students/admission', [StudentAdmissionPageController::class, 'create'])
    ->name('academy.students.admission.create');

Route::post('/academy/students/admissions', [StudentAdmissionController::class, 'store'])
    ->name('academy.students.admission.store');

Route::fallback(fn () => Inertia::render('errors/404')
    ->toResponse(request())
    ->setStatusCode(404));
