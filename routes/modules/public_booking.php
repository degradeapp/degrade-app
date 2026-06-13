<?php

use App\Http\Controllers\PublicBookingController;
use Illuminate\Support\Facades\Route;

/**
 * Link público de agendamento — SEM auth, escopado pelo slug do tenant.
 * Rate limit por IP (limiters em AppServiceProvider):
 *  - public-booking: leituras (catálogo/horários), 30/min por IP
 *  - public-booking-create: criação, 5/min e 20/hora por IP
 */
Route::prefix('api/public/agendar/{slug}')->name('api.public.booking.')->group(function () {
    Route::middleware('throttle:public-booking')->group(function () {
        Route::get('/', [PublicBookingController::class, 'catalog'])->name('catalog');
        Route::get('/horarios', [PublicBookingController::class, 'slots'])->name('slots');
    });

    Route::middleware('throttle:public-booking-create')
        ->post('/', [PublicBookingController::class, 'store'])->name('store');
});
