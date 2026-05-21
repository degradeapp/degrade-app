<?php

use App\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum', 'role:owner')->prefix('billing')->group(function () {
    Route::get('/', [BillingController::class, 'show'])->name('billing.show');
    Route::post('/select-plan', [BillingController::class, 'selectPlan'])->name('billing.select-plan');
});
