<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Modules\Academy\Http\Controllers\StudentAdmissionPageController;

require __DIR__.'/pages/dashboard.php';
require __DIR__.'/settings.php';

Route::inertia('/', 'welcome')->name('home');


//academy module 
Route::get('/academy/students', fn () => Inertia::render('academy/students'))
    ->name('academy.students.index');

Route::get('/academy/students/admission', [StudentAdmissionPageController::class, 'create'])
    ->name('academy.students.admission.create');

Route::fallback(fn () => Inertia::render('errors/404')
    ->toResponse(request())
    ->setStatusCode(404));
