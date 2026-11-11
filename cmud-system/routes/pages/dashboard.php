<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', fn () => Inertia::render('dashboard/index'))
        ->name('dashboard');
});
