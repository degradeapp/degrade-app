<?php

use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    // Trocar a unidade ativa: dono e gerente (gerente também vê consolidado).
    Route::middleware('auth:sanctum', 'role:owner,manager')->prefix('units')->group(function () {
        Route::post('/switch', [UnitController::class, 'switch'])->name('units.switch');
    });

    // Gestão de unidades (criar/editar/remover): só o dono.
    Route::middleware('auth:sanctum', 'role:owner', 'subscription.active')->prefix('units')->group(function () {
        Route::get('/', [UnitController::class, 'index'])->name('units.index');
        Route::post('/', [UnitController::class, 'store'])->name('units.store');
        Route::put('/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    });
});
