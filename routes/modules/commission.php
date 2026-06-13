<?php

use App\Http\Controllers\CommissionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    Route::middleware('auth:sanctum', 'role:owner,manager', 'subscription.active')->prefix('commissions')->group(function () {
        Route::get('/', [CommissionController::class, 'index'])->name('commissions.index');
        // Antes de /{commission} pra não serem capturadas como id.
        Route::get('/pending-summary', [CommissionController::class, 'pendingSummary'])->name('commissions.pending-summary');
        Route::post('/pay-barber', [CommissionController::class, 'payBarber'])->name('commissions.pay-barber');
        Route::get('/{commission}', [CommissionController::class, 'show'])->name('commissions.show');
        Route::post('/{commission}/mark-as-paid', [CommissionController::class, 'markAsPaid'])->name('commissions.mark-as-paid');
    });
});
