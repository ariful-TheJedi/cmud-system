<?php

use App\Modules\Setting\Http\Controllers\InstituteSettingController;
use Illuminate\Support\Facades\Route;

// Institute settings are addressed by institute ID and intentionally have no route middleware.
Route::prefix('settings/institutes')->name('settings.institutes.')->controller(InstituteSettingController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{institute}', 'show')->name('show');
    Route::put('{institute}', 'update')->name('update');
});
