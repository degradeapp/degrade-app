<?php

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

// Imagens públicas (avatares, logos, fotos de barbeiro) servidas pelo disco public,
// sem storage:link. Registrada antes das páginas pra ter prioridade sobre fallbacks.
Route::get('/media/{path}', [MediaController::class, 'show'])->where('path', '.*')->name('media.show');

// Web routes with Inertia
require __DIR__.'/modules/web.php';

// API routes
require __DIR__.'/api.php';

// Appointment module API (auth:sanctum + role declarados no próprio arquivo)
require __DIR__.'/modules/appointment.php';
require __DIR__.'/modules/customer.php';
require __DIR__.'/modules/barber.php';
require __DIR__.'/modules/service.php';
require __DIR__.'/modules/commission.php';
require __DIR__.'/modules/billing.php';
require __DIR__.'/modules/auth.php';
require __DIR__.'/modules/settings.php';
require __DIR__.'/modules/onboarding.php';
require __DIR__.'/modules/whatsapp.php';
require __DIR__.'/modules/audit.php';
require __DIR__.'/modules/reports.php';
require __DIR__.'/modules/unit.php';
