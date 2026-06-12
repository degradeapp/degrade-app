<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth')->name('auth.register');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth')->name('auth.login');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('auth.logout');
        Route::post('forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:auth')->name('auth.forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth')->name('auth.reset-password');
    });
});
