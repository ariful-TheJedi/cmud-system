<?php

use App\Modules\Institute\Controllers\Institute\InstituteController;
use App\Modules\Institute\Controllers\Stuff\StuffController;
use Illuminate\Support\Facades\Route;

// Institute settings are addressed by institute ID and intentionally have no route middleware.
Route::prefix('settings/institutes')->name('settings.institutes.')->controller(InstituteController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{institute}', 'show')->name('show');
    Route::put('{institute}', 'update')->name('update');
});

// Stuff directory and profile routes delegate validation and persistence to module classes.
Route::prefix('stuff')->name('stuff.')->controller(StuffController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('/options', 'options')->name('options');
    Route::post('/', 'store')->name('store');
    Route::get('{stuff}/nid/download', 'downloadNid')->name('nid.download');
    Route::get('{stuff}', 'show')->name('show');
    Route::put('{stuff}', 'update')->name('update');
});
