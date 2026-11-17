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
use App\Modules\Academy\Http\Controllers\AcademyCatalogController;
use App\Modules\Academy\Http\Controllers\TeacherManagementController;

// Course CRUD includes subject_ids for syncing course modules through course_subject.
Route::prefix('academy-catalog')->name('academy.catalog.')->controller(AcademyCatalogController::class)->group(function (): void {
    Route::get('courses', 'courses')->name('courses.index');
    Route::post('courses', 'storeCourse')->name('courses.store');
    Route::get('courses/{course}', 'showCourse')->name('courses.show');
    Route::put('courses/{course}', 'updateCourse')->name('courses.update');
    Route::delete('courses/{course}', 'destroyCourse')->name('courses.destroy');

    // Batch CRUD keeps course and branch assignments explicit in the request.
    Route::get('batches', 'batches')->name('batches.index');
    Route::post('batches', 'storeBatch')->name('batches.store');
    Route::get('batches/{batch}', 'showBatch')->name('batches.show');
    Route::put('batches/{batch}', 'updateBatch')->name('batches.update');
    Route::delete('batches/{batch}', 'destroyBatch')->name('batches.destroy');

    // Subjects are reusable modules; course_ids synchronizes their many-to-many links.
    Route::get('subjects', 'subjects')->name('subjects.index');
    Route::post('subjects', 'storeSubject')->name('subjects.store');
    Route::get('subjects/{subject}', 'showSubject')->name('subjects.show');
    Route::put('subjects/{subject}', 'updateSubject')->name('subjects.update');
    Route::delete('subjects/{subject}', 'destroySubject')->name('subjects.destroy');
});

// Teacher CRUD and assignment management are isolated from course/batch/subject catalog actions.
Route::prefix('academy-catalog/teachers')
    ->name('academy.catalog.teachers.')
    ->controller(TeacherManagementController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{teacher}', 'show')->name('show');
        Route::put('/{teacher}', 'update')->name('update');
        Route::delete('/{teacher}', 'destroy')->name('destroy');
    });

// List/search students and retrieve a student profile by registration code.
Route::prefix('students')->name('students.')->controller(StudentController::class)->group(function (): void {
    Route::get('/', 'index')
        ->name('index');

    Route::get('/{studentCode}', 'show')
        ->name('show');
});

// Admission creates the student and their first enrollment. Route-level
// authentication middleware will be added with the application's access rules.
Route::prefix('students/admissions')
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

// Compatibility alias for student admission.
Route::prefix('student/admission')
    ->name('student.admission.')
    ->controller(StudentAdmissionController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
    });
