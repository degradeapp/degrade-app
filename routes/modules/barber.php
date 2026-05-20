<?php

use App\Http\Controllers\BarberController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum', 'role:owner,manager')->prefix('barbers')->group(function () {
    Route::get('/', [BarberController::class, 'index'])->name('barbers.index');
    Route::post('/', [BarberController::class, 'store'])->name('barbers.store');
    Route::get('/{barber}', [BarberController::class, 'show'])->name('barbers.show');
    Route::put('/{barber}', [BarberController::class, 'update'])->name('barbers.update');
    Route::delete('/{barber}', [BarberController::class, 'destroy'])->name('barbers.destroy');

    Route::put('/{barber}/schedule/{day}', [BarberController::class, 'schedule'])->name('barbers.schedule.upsert');
    Route::post('/{barber}/time-off', [BarberController::class, 'timeOff'])->name('barbers.time-off.create');
    Route::delete('/{barber}/time-off/{date}', [BarberController::class, 'deleteTimeOff'])->name('barbers.time-off.delete');
});
