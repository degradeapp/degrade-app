<?php

use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    Route::middleware('auth:sanctum', 'role:owner,manager,receptionist,barber', 'subscription.active')->prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/', [CustomerController::class, 'store'])->name('customers.store');
        // Antes de /{customer} pra rota fixa não cair no binding; só o dono exporta.
        Route::get('/export', [CustomerController::class, 'export'])->middleware('role:owner')->name('customers.export');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });
});
