<?php

/**
 * Student and Academy API routes.
 *
 * This file is loaded inside the application's `/api/v1` prefix. Keep route
 * declarations grouped by the controller and business action they expose.
 */

use Illuminate\Support\Facades\Route;
use App\Modules\Academy\Http\Controllers\StudentController;
use App\Modules\Academy\Http\Controllers\StudentAdmissionController;
use App\Modules\Academy\Http\Controllers\StudentEnrollmentController;
use App\Modules\Academy\Http\Controllers\StudentUpdateController;

// List/search students and retrieve a student profile by registration code.
Route::prefix('students')->name('students.')->controller(StudentController::class)->group(function (): void {
    Route::get('/', 'index')
        ->name('index');

    Route::get('/{studentCode}', 'show')
        ->name('show');
});

// Admission creates the student and their first enrollment. Authentication is
// required here; StudentAdmissionRequest also checks the user's permission.
Route::middleware('auth')
    ->prefix('students/admissions')
    ->name('students.admissions.')
    ->controller(StudentAdmissionController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
    });

// Add another course enrollment to an existing student. The controller looks
// up the student using the public student registration code in the URL.
Route::prefix('students/{studentCode}/enrollments')
    ->name('students.enrollments.')
    ->controller(StudentEnrollmentController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
    });

// Provide the student profile data and branch choices needed by the edit page,
// then accept the profile update. These routes currently have no auth middleware.
Route::get('student/{studentCode}/edit', [StudentUpdateController::class, 'edit'])
    ->name('student.edit.show');
Route::put('student/{studentCode}/edit', [StudentUpdateController::class, 'update'])
    ->name('student.edit.update');

// Compatibility alias for student admission. It calls the same controller
// action and has the same authentication and permission checks as the plural URL.
Route::middleware('auth')
    ->prefix('student/admission')
    ->name('student.admission.')
    ->controller(StudentAdmissionController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
    });
