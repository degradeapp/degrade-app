<?php

use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum', 'role:owner,manager')->prefix('services')->group(function () {
    Route::get('/', [ServiceController::class, 'index'])->name('services.index');
    Route::post('/', [ServiceController::class, 'store'])->name('services.store');
    Route::get('/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::put('/{service}', [ServiceController::class, 'update'])->name('services.update');
    Route::delete('/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

    Route::post('/{service}/barbers/{barber}', [ServiceController::class, 'attachBarber'])->name('services.barbers.attach');
    Route::delete('/{service}/barbers/{barber}', [ServiceController::class, 'detachBarber'])->name('services.barbers.detach');
});
