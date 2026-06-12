<?php

use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:owner,manager'])->group(function () {
    Route::get('/audit', [ActivityLogController::class, 'indexPage'])->name('audit.page');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/audit', [ActivityLogController::class, 'index'])->name('audit.index');
    });
});
