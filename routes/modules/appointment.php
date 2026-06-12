<?php

use App\Http\Controllers\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    Route::middleware('auth:sanctum', 'role:owner,manager,receptionist,barber')->prefix('appointments')->group(function () {
        Route::get('/', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
        Route::put('/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::post('/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::post('/{appointment}/no-show', [AppointmentController::class, 'noShow'])->name('appointments.no-show');
        Route::post('/{appointment}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
        Route::put('/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
        Route::get('/availability/barber/{barber}', [AppointmentController::class, 'available'])->name('appointments.available');
        Route::get('/availability/barber/{barber}/day', [AppointmentController::class, 'daySchedule'])->name('appointments.availability.day');
    });
});
