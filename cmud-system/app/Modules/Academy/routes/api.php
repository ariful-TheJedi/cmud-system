<?php

use App\Modules\Academy\Controllers\AcademicProgress\AcademicProgressController;
use App\Modules\Academy\Controllers\AcademicProgress\StudentAcademicOverviewController;
use App\Modules\Academy\Controllers\Admissions\StudentAdmissionController;
use App\Modules\Academy\Controllers\Catalog\AcademyCatalogController;
use App\Modules\Academy\Controllers\Certificates\CertificateOverviewController;
use App\Modules\Academy\Controllers\Communications\StudentCommunicationController;
use App\Modules\Academy\Controllers\Communications\StudentNotificationController;
use App\Modules\Academy\Controllers\Enrollments\EnrollmentController;
use App\Modules\Academy\Controllers\Enrollments\StudentEnrollmentController;
use App\Modules\Academy\Controllers\Students\StudentController;
use App\Modules\Academy\Requests\StudentSearchRequest;
use App\Modules\Academy\Controllers\Students\StudentUpdateController;
use App\Modules\Academy\Controllers\Teachers\TeacherManagementController;
use App\Modules\Finance\Controllers\StudentFinancialOverviewController;
use App\Modules\Finance\Controllers\StudentPaymentHistoryController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/ExamControl.php';

/*
 * The root API route file loads this module beneath /api/v1. Keep URL and
 * controller mappings here; validation and workflow rules belong in requests
 * and services.
 */

// Manage course structures and the groups, subjects, and teachers that use them.
Route::prefix('academy-catalog')->name('academy.catalog.')->controller(AcademyCatalogController::class)->group(function (): void {
    // Courses own the stages used to track enrollment progress.
    Route::get('courses', 'courses')->name('courses.index');
    Route::post('courses', 'storeCourse')->name('courses.store');
    Route::get('courses/{course}', 'showCourse')->name('courses.show');
    Route::put('courses/{course}', 'updateCourse')->name('courses.update');
    Route::delete('courses/{course}', 'destroyCourse')->name('courses.destroy');
    Route::get('courses/{course}/stages', 'stages')->name('courses.stages.index');
    Route::post('courses/{course}/stages', 'storeStage')->name('courses.stages.store');
    Route::get('courses/{course}/stages/{stage}', 'showStage')->name('courses.stages.show');
    Route::put('courses/{course}/stages/{stage}', 'updateStage')->name('courses.stages.update');
    Route::delete('courses/{course}/stages/{stage}', 'destroyStage')->name('courses.stages.destroy');

    // Batches assign a course offering to a particular branch and schedule.
    Route::get('batches', 'batches')->name('batches.index');
    Route::post('batches', 'storeBatch')->name('batches.store');
    Route::get('batches/{batch}', 'showBatch')->name('batches.show');
    Route::put('batches/{batch}', 'updateBatch')->name('batches.update');
    Route::delete('batches/{batch}', 'destroyBatch')->name('batches.destroy');

    // Subjects and teachers are maintained independently from enrollment records.
    Route::get('subjects', 'subjects')->name('subjects.index');
    Route::post('subjects', 'storeSubject')->name('subjects.store');
    Route::get('subjects/{subject}', 'showSubject')->name('subjects.show');
    Route::put('subjects/{subject}', 'updateSubject')->name('subjects.update');
    Route::delete('subjects/{subject}', 'destroySubject')->name('subjects.destroy');

    Route::get('teachers', [TeacherManagementController::class, 'index'])->name('teachers.index');
    Route::post('teachers', [TeacherManagementController::class, 'store'])->name('teachers.store');
    Route::get('teachers/{teacher}', [TeacherManagementController::class, 'show'])->name('teachers.show');
    Route::put('teachers/{teacher}', [TeacherManagementController::class, 'update'])->name('teachers.update');
    Route::delete('teachers/{teacher}', [TeacherManagementController::class, 'destroy'])->name('teachers.destroy');
});

// Student lookup and the separate academic, financial, certificate, and payment views.
// Keep the static path above students/{studentCode} so it is not captured as a student code.
Route::get('students/search', [StudentController::class, 'billingSearch'])
    ->middleware(['web', 'auth', \App\Http\Middleware\RequireBillingBranch::class])->name('students.finance.search');
Route::get('students', [StudentController::class, 'index'])->name('students.index');
Route::get('students/{studentCode}', [StudentController::class, 'show'])->name('students.show');
Route::get('students/{studentCode}/academic', [StudentAcademicOverviewController::class, 'student'])
    ->name('students.academic');
Route::get('students/{studentCode}/certificates', [CertificateOverviewController::class, 'student'])
    ->name('students.certificates');
Route::get('students/{studentCode}/financial-summary', [StudentFinancialOverviewController::class, 'student'])
    ->middleware(['web', 'auth'])->name('students.financial-summary');
Route::get('students/{studentCode}/payments', [StudentPaymentHistoryController::class, 'index'])
    ->middleware(['web', 'auth'])->name('students.payments');
Route::get('students/{studentCode}/payments/summary', [StudentPaymentHistoryController::class, 'summary'])
    ->middleware(['web', 'auth'])->name('students.payments.summary');
Route::post('students/admissions', [StudentAdmissionController::class, 'store'])
    ->name('students.admissions.store');
// Keep the singular path as a compatibility alias for existing admission clients.
Route::post('student/admission', [StudentAdmissionController::class, 'store'])
    ->name('student.admission.store');
Route::post('students/{studentCode}/enrollments', [StudentEnrollmentController::class, 'store'])
    ->name('students.enrollments.store');

// Enrollment assignment and status changes are recorded independently per enrollment.
Route::patch('enrollments/{enrollment}/batch', [EnrollmentController::class, 'transferBatch'])
    ->name('enrollments.batch.transfer');
Route::put('enrollments/{enrollment}/assignment', [EnrollmentController::class, 'updateAssignment'])
    ->name('enrollments.assignment.update');
Route::get('enrollments/{enrollment}/history', [EnrollmentController::class, 'history'])
    ->name('enrollments.history');
Route::patch('enrollments/{enrollment}/status', [EnrollmentController::class, 'updateStatus'])
    ->name('enrollments.status.update');
Route::get('enrollments/{enrollment}/academic', [StudentAcademicOverviewController::class, 'enrollment'])
    ->name('enrollments.academic');
Route::get('enrollments/{enrollment}/financial-summary', [StudentFinancialOverviewController::class, 'enrollment'])
    ->middleware(['web', 'auth'])->name('enrollments.financial-summary');
Route::get('enrollments/{enrollment}/certificates', [CertificateOverviewController::class, 'enrollment'])
    ->name('enrollments.certificates');
Route::get('certificates/{certificate}', [CertificateOverviewController::class, 'show'])
    ->name('certificates.show');
// Payment keys use a source prefix, such as tuition-42 or expense-42, to identify the table.
Route::get('student-payments/{paymentKey}', [StudentPaymentHistoryController::class, 'show'])
    ->where('paymentKey', '(tuition|expense)-[0-9]+')
    ->middleware(['web', 'auth'])->name('student-payments.show');

// Academic routes operate on the selected enrollment and its own stage records.
Route::prefix('enrollments/{enrollment}')
    ->name('enrollments.academics.')
    ->controller(AcademicProgressController::class)
    ->group(function (): void {
        Route::get('academic-progress', 'show')->name('show');
        Route::patch('stages/{stageRecord}', 'updateStageStatus')->name('stages.status.update');
        Route::post('stages/{stageRecord}/advance', 'advance')->name('stages.advance');
        Route::get('stages/{stageRecord}/exam-eligibility', 'examEligibility')->name('stages.exam-eligibility');
        Route::post('stages/{stageRecord}/exams', 'recordExamAttempt')->name('stages.exams.store');
        Route::post('academic-exceptions', 'requestException')->name('exceptions.store');
        Route::post('complete', 'complete')->name('complete');
        Route::get('certificate-eligibility', 'certificateEligibility')->name('certificate-eligibility');
    });

// Decisions and published results are addressed by their individual record IDs.
Route::patch('academic-exceptions/{exception}/decision', [AcademicProgressController::class, 'decideException'])
    ->name('academic-exceptions.decision');
Route::patch('exam-records/{exam}/result', [AcademicProgressController::class, 'publishExamResult'])
    ->name('exam-records.result.update');

// Record student contact history and membership in enrollment-related communication groups.
Route::prefix('students/{studentCode}')
    ->name('students.communications.')
    ->controller(StudentCommunicationController::class)
    ->group(function (): void {
        Route::get('communication-context', 'context')->name('context');
        Route::get('communications', 'index')->name('index');
        Route::post('communications', 'store')->name('store');
        Route::get('communication-groups', 'availableGroups')->name('groups.available');
        Route::get('communication-groups/memberships', 'memberships')->name('groups.memberships');
        Route::post('communication-groups/{group}', 'joinGroup')->name('groups.join');
        Route::delete('communication-groups/{group}', 'leaveGroup')->name('groups.leave');
    });

// Student notifications can be created, synchronized, listed, and marked as read.
Route::prefix('students/{studentCode}/notifications')
    ->name('students.notifications.')
    ->controller(StudentNotificationController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/synchronize', 'synchronize')->name('synchronize');
        Route::patch('/{notification}/read', 'markRead')->name('read');
    });

// Retain the existing student profile edit paths for current clients.
Route::get('student/{studentCode}/edit', [StudentUpdateController::class, 'edit'])
    ->name('student.edit.show');
Route::put('student/{studentCode}/edit', [StudentUpdateController::class, 'update'])
    ->name('student.edit.update');
