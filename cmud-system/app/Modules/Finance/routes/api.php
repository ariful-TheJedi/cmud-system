<?php

use App\Modules\Finance\Controllers\InstituteBill\InstituteBillController;
use App\Modules\Finance\Controllers\StudentEnrollmentBillingController;
use App\Modules\Finance\Controllers\StudentTuitionPaymentController;
use Illuminate\Support\Facades\Route;

// Student finance endpoints are session-authenticated and each service operation rechecks branch scope.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/student-payments/options', [StudentEnrollmentBillingController::class, 'registerOptions'])
        ->name('student-payments.options');
    Route::get('/student-payments', [StudentEnrollmentBillingController::class, 'register'])
        ->name('student-payments.register');
    Route::get('/students/{studentCode}/enrollments', [StudentEnrollmentBillingController::class, 'enrollments'])
        ->name('students.finance.enrollments');
    Route::prefix('enrollments/{enrollment}')->name('enrollments.finance.')->group(function (): void {
        Route::get('/billing', [StudentEnrollmentBillingController::class, 'overview'])->name('billing');
        Route::get('/tuition', [StudentEnrollmentBillingController::class, 'tuition'])->name('tuition');
        Route::get('/payment-options', [StudentEnrollmentBillingController::class, 'paymentOptions'])->name('payment-options');
        Route::post('/tuition/{tuition}/payments', [StudentEnrollmentBillingController::class, 'storeTuitionPayment'])
            ->whereNumber('tuition')->name('tuition.payments.store');
        Route::get('/expenses/options', [StudentEnrollmentBillingController::class, 'expenseOptions'])->name('expenses.options');
        Route::get('/expenses', [StudentEnrollmentBillingController::class, 'expenses'])->name('expenses.index');
        Route::post('/expenses', [StudentEnrollmentBillingController::class, 'storeExpense'])->name('expenses.store');
        Route::get('/expenses/{expense}', [StudentEnrollmentBillingController::class, 'showExpense'])
            ->whereNumber('expense')->name('expenses.show');
        Route::post('/expenses/{expense}/payments', [StudentEnrollmentBillingController::class, 'storeExpensePayment'])
            ->whereNumber('expense')->name('expenses.payments.store');
        Route::get('/payments', [StudentEnrollmentBillingController::class, 'payments'])->name('payments.index');
    });
});

// Tuition payment endpoints use the signed-in recorder for branch and audit policy.
Route::prefix('student-tuitions')->name('student-tuitions.')->middleware(['web', 'auth'])->group(function (): void {
    Route::get('/{tuition}/payment-options', [StudentTuitionPaymentController::class, 'options'])
        ->whereNumber('tuition')
        ->name('payment-options');
    Route::post('/{tuition}/payments', [StudentTuitionPaymentController::class, 'storeCashPayment'])
        ->whereNumber('tuition')
        ->name('payments.store');
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
