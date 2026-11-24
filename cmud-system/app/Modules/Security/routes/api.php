<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Security\Controllers\UserControl\UserManagementController;

// User accounts are separate from employee profiles and link through stuff.user_id.
Route::prefix('users')->name('users.')->controller(UserManagementController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('branches', 'branches')->name('branches');
    Route::get('eligible-employees', 'eligibleEmployees')->name('eligible-employees');
    Route::post('/', 'store')->name('store');
    Route::post('from-employee', 'storeFromEmployee')->name('from-employee');
    Route::put('{user}', 'update')->whereNumber('user')->name('update');
});
