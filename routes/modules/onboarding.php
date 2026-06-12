<?php

use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:owner', 'onboarding.incomplete'])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');

    Route::prefix('api/onboarding')->name('api.onboarding.')->group(function () {
        Route::post('business', [OnboardingController::class, 'saveBusiness'])->name('business');
        Route::post('hours', [OnboardingController::class, 'saveHours'])->name('hours');
        Route::post('service', [OnboardingController::class, 'saveFirstService'])->name('service');
        Route::post('complete', [OnboardingController::class, 'complete'])->name('complete');
    });
});
