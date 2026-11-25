<?php

use App\Modules\Finance\Controllers\InstituteBill\InstituteBillController;
use App\Modules\Finance\Controllers\StudentFinanceController;
use Illuminate\Support\Facades\Route;

// Student finance endpoints are session-authenticated and each service operation rechecks branch scope.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/student-payments/options', [StudentFinanceController::class, 'registerOptions'])
        ->name('student-payments.options');
    Route::get('/student-payment-charges', [StudentFinanceController::class, 'chargeRegister'])
        ->name('student-payment-charges.register');
    Route::get('/student-payments', [StudentFinanceController::class, 'register'])
        ->name('student-payments.register');
    Route::get('/students/{studentCode}/enrollments', [StudentFinanceController::class, 'enrollments'])
        ->name('students.finance.enrollments');
    Route::prefix('enrollments/{enrollment}')->name('enrollments.finance.')->group(function (): void {
        Route::get('/billing', [StudentFinanceController::class, 'overview'])->name('billing');
        Route::get('/tuition', [StudentFinanceController::class, 'tuition'])->name('tuition');
        Route::get('/payment-options', [StudentFinanceController::class, 'paymentOptions'])->name('payment-options');
        Route::post('/tuition/payments', [StudentFinanceController::class, 'storeTuitionPayment'])
            ->name('tuition.payments.store');
        Route::get('/tuition/payment-options', [StudentFinanceController::class, 'paymentOptions'])
            ->name('tuition.payment-options');
        Route::get('/expenses/options', [StudentFinanceController::class, 'expenseOptions'])->name('expenses.options');
        Route::get('/expenses', [StudentFinanceController::class, 'expenses'])->name('expenses.index');
        Route::post('/expenses', [StudentFinanceController::class, 'storeExpense'])->name('expenses.store');
        Route::get('/expenses/{expense}', [StudentFinanceController::class, 'showExpense'])
            ->whereNumber('expense')->name('expenses.show');
        Route::post('/expenses/{expense}/payments', [StudentFinanceController::class, 'storeExpensePayment'])
            ->whereNumber('expense')->name('expenses.payments.store');
        Route::get('/payments', [StudentFinanceController::class, 'payments'])->name('payments.index');
    });
});

Route::prefix('institute-bills')->name('institute-bills.')->middleware(['web', 'auth'])->group(function (): void {
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
