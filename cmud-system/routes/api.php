<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/', fn () => response()->json([
        'message' => 'Welcome to the CMUD System API.',
        'version' => '1.0',
    ]))->name('api.v1.root');

    require app_path('Modules/Institute/routes/api.php');
    require app_path('Modules/Academy/routes/api.php');
    require app_path('Modules/Finance/routes/api.php');
    require app_path('Modules/Security/routes/api.php');
});

