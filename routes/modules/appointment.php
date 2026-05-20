<?php

use App\Http\Controllers\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum', 'role:owner,manager,receptionist')->prefix('appointments')->group(function () {
    Route::get('/', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
    Route::put('/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
    Route::post('/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::post('/{appointment}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
    Route::put('/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::get('/availability/barber/{barber}', [AppointmentController::class, 'available'])->name('appointments.available');
});
