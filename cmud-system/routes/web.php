<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/pages/dashboard.php';
require __DIR__.'/settings.php';

Route::inertia('/', 'welcome')->name('home');

Route::fallback(fn () => Inertia::render('errors/404')
    ->toResponse(request())
    ->setStatusCode(404));
