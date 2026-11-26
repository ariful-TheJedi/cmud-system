<?php

use App\Modules\Finance\Controllers\InstituteBill\InstituteBillController;
use App\Modules\Finance\Controllers\InstituteOtherBill\InstituteOtherBillController;
use App\Modules\Finance\Controllers\StudentPaymentController;
use App\Http\Middleware\RequireBillingBranch;
use Illuminate\Support\Facades\Route;

// Student receipts remain session-authenticated and branch-scoped at the route and query layers.
Route::middleware(['web', 'auth', RequireBillingBranch::class])->group(function (): void {
    Route::get('/student-payments', [StudentPaymentController::class, 'index'])->name('student-payments.register');
    Route::post('/student-payments/tuition', [StudentPaymentController::class, 'storeTuition'])->name('student-payments.tuition.store');
    Route::post('/student-payments/other-expenses', [StudentPaymentController::class, 'storeOtherExpense'])
        ->name('student-payments.other-expenses.store');
});

Route::prefix('institute-bills')->name('institute-bills.')->middleware(['web', 'auth', RequireBillingBranch::class])->group(function (): void {
    Route::get('/options', [InstituteBillController::class, 'options'])->name('options');
    Route::get('/billing-items', [InstituteBillController::class, 'billingItems'])->name('billing-items');
    Route::get('/', [InstituteBillController::class, 'index'])->name('index');
    // Bill creation runs through web session auth so the recorder always comes from the signed-in user.
    Route::post('/', [InstituteBillController::class, 'store'])->name('store');
    Route::post('/{bill}/payments', [InstituteBillController::class, 'storeCashPayment'])
        ->whereNumber('bill')
        ->name('payments.store');
    Route::get('/{bill}', [InstituteBillController::class, 'show'])->whereNumber('bill')->name('show');
    // Status changes use the authenticated session user for approval audit fields.
    Route::patch('/{bill}/status', [InstituteBillController::class, 'updateStatus'])
        ->whereNumber('bill')
        ->name('status.update');
});

Route::prefix('institute-other-bills')->name('institute-other-bills.')
    ->middleware(['web', 'auth', RequireBillingBranch::class])->group(function (): void {
        Route::get('/options', [InstituteOtherBillController::class, 'options'])->name('options');
        Route::get('/', [InstituteOtherBillController::class, 'index'])->name('index');
        Route::post('/', [InstituteOtherBillController::class, 'store'])->name('store');
        Route::get('/{bill}', [InstituteOtherBillController::class, 'show'])->whereNumber('bill')->name('show');
        Route::put('/{bill}', [InstituteOtherBillController::class, 'update'])->whereNumber('bill')->name('update');
        Route::delete('/{bill}', [InstituteOtherBillController::class, 'destroy'])->whereNumber('bill')->name('destroy');
    });
