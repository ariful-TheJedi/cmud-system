<?php

use Illuminate\Support\Facades\Route;

use App\Modules\Finance\Controllers\InstituteBill\InstituteBillController;

Route::prefix('institute-bills')->name('institute-bills.')->group(function (): void {
    Route::get('/options', [InstituteBillController::class, 'options'])->name('options');
    Route::get('/billing-items', [InstituteBillController::class, 'billingItems'])->name('billing-items');
    Route::get('/', [InstituteBillController::class, 'index'])->name('index');
    // Bill creation runs through web session auth so the recorder always comes from the signed-in user.
    Route::post('/', [InstituteBillController::class, 'store'])->middleware(['web', 'auth'])->name('store');
    Route::get('/{bill}', [InstituteBillController::class, 'show'])->whereNumber('bill')->name('show');
});
