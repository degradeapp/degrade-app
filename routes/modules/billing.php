<?php

use App\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    Route::middleware('auth:sanctum', 'role:owner')->prefix('billing')->group(function () {
        Route::get('/', [BillingController::class, 'show'])->name('billing.show');
        Route::post('/select-plan', [BillingController::class, 'selectPlan'])->name('billing.select-plan');
        Route::post('/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    });
});
