<?php

use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api', 'role:owner,manager', 'subscription.active'])->group(function () {
    Route::get('/reports', [ReportsController::class, 'indexPage'])->name('reports.page');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/reports/summary', [ReportsController::class, 'summary'])->name('reports.summary');
    });
});
