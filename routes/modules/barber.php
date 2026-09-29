<?php

use App\Http\Controllers\BarberController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    Route::middleware('auth:sanctum', 'throttle:api', 'role:owner,manager', 'subscription.active')->prefix('barbers')->group(function () {
        Route::get('/', [BarberController::class, 'index'])->name('barbers.index');
        Route::post('/', [BarberController::class, 'store'])->name('barbers.store');
        Route::get('/{barber}', [BarberController::class, 'show'])->name('barbers.show');
        Route::put('/{barber}', [BarberController::class, 'update'])->name('barbers.update');
        Route::delete('/{barber}', [BarberController::class, 'destroy'])->name('barbers.destroy');

        Route::post('/{barber}/photo', [BarberController::class, 'updatePhoto'])->name('barbers.photo.update');
        Route::delete('/{barber}/photo', [BarberController::class, 'deletePhoto'])->name('barbers.photo.delete');

        // {day} preso a 0-6: BarberSchedule.day_of_week tem cast pro enum DayOfWeek,
        // então um valor fora da semana estourava ValueError (HTTP 500) em vez de
        // 404. A restrição mata o valor inválido antes de chegar no controller.
        Route::put('/{barber}/schedule/{day}', [BarberController::class, 'schedule'])->name('barbers.schedule.upsert')->where('day', '[0-6]');
        Route::post('/{barber}/time-off', [BarberController::class, 'timeOff'])->name('barbers.time-off.create');
        // {date} preso a YYYY-MM-DD pela mesma razão: string arbitrária não deve
        // chegar ao parse de data.
        Route::delete('/{barber}/time-off/{date}', [BarberController::class, 'deleteTimeOff'])->name('barbers.time-off.delete')->where('date', '\d{4}-\d{2}-\d{2}');
    });
});
