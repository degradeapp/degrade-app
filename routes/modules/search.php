<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    Route::middleware('auth:sanctum', 'throttle:api', 'subscription.active')->group(function () {
        Route::get('/search', [SearchController::class, 'index'])->name('search');
        Route::post('/search', [SearchController::class, 'index']);
    });
});
