<?php

use App\Http\Controllers\WhatsappController;
use Illuminate\Support\Facades\Route;

// Webhook público (Meta verifica via GET, manda eventos via POST)
Route::get('/webhooks/whatsapp', [WhatsappController::class, 'verify'])->name('whatsapp.webhook.verify');
Route::post('/webhooks/whatsapp', [WhatsappController::class, 'webhook'])->name('whatsapp.webhook');

Route::prefix('api')->name('api.')->group(function () {
    // Credenciais da integração (Phone ID, access token, webhook) = nível de conta,
    // mesma sensibilidade de cobrança/acessos. Só o DONO mexe.
    Route::middleware('auth:sanctum', 'role:owner')->prefix('whatsapp')->group(function () {
        Route::get('/account', [WhatsappController::class, 'listAccounts'])->name('whatsapp.account.get');
        Route::put('/account', [WhatsappController::class, 'upsertAccount'])->name('whatsapp.account.upsert');
    });

    // Caixa de entrada (conversas com clientes) = operacional, dono e gerente.
    Route::middleware('auth:sanctum', 'role:owner,manager', 'subscription.active')->prefix('whatsapp')->group(function () {
        Route::get('/conversations', [WhatsappController::class, 'listConversations'])->name('whatsapp.conversations');
        Route::get('/conversations/{conversation}', [WhatsappController::class, 'showConversation'])->name('whatsapp.conversation.show');
    });
});
