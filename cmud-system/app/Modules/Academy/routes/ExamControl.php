<?php

use App\Modules\Academy\Controllers\ExamControl\ExamControlController;
use Illuminate\Support\Facades\Route;

// Exam control is isolated so its routes and dependencies can evolve together.
Route::prefix('exam-control')->name('exam-control.')->middleware(['web', 'auth'])->group(function (): void {
    Route::get('batches', [ExamControlController::class, 'batches'])->name('batches.index');
    Route::get('students', [ExamControlController::class, 'students'])->name('students.index');
    Route::patch('stages/{stageRecord}/status', [ExamControlController::class, 'updateStatus'])->name('stages.status.update');
});
