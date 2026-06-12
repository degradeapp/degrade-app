<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::post('/webhooks/asaas', [WebhookController::class, 'handleAsaasWebhook'])->name('webhooks.asaas');
    Route::get('/health', [HealthController::class, 'check'])->name('health');

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});

require __DIR__.'/modules/search.php';
