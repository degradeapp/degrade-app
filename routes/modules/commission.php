<?php

use App\Http\Controllers\CommissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum', 'role:owner,manager')->prefix('commissions')->group(function () {
    Route::get('/', [CommissionController::class, 'index'])->name('commissions.index');
    Route::get('/{commission}', [CommissionController::class, 'show'])->name('commissions.show');
});
