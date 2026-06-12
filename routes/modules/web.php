<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Auth routes (não requerem autenticação)
Route::middleware('guest')->group(function () {
    Route::inertia('/login', 'Auth/Login')->name('login');
    Route::post('/login', [AuthController::class, 'webLogin'])->middleware('throttle:auth')->name('login.store');
    Route::inertia('/register', 'Auth/Register')->name('register');
    Route::post('/register', [AuthController::class, 'webRegister'])->middleware('throttle:auth')->name('register.store');
    Route::inertia('/forgot-password', 'Auth/ForgotPassword')->name('password.request');
    Route::inertia('/reset-password', 'Auth/ResetPassword')->name('password.reset');
    Route::inertia('/reset-password/{token}', 'Auth/ResetPassword');
});

// Logout — só exige auth (funciona durante onboarding / trial expirado)
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'webLogout'])->name('logout');

// Páginas legais — públicas, acessíveis com ou sem login (linkadas no cadastro).
Route::inertia('/terms', 'Legal/Terms')->name('terms');
Route::inertia('/privacy', 'Legal/Privacy')->name('privacy');

// Authenticated routes. As PÁGINAS são gateadas por papel espelhando as APIs — assim
// navegar direto numa URL sem permissão cai no /403 limpo (não numa tela quebrada).
Route::middleware(['auth:sanctum', 'subscription.active', 'onboarding.completed'])->group(function () {
    // --- Todo membro logado (dono, gerente, recepção, barbeiro) ---
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::inertia('/settings', 'Settings/Index');        // hub "Mais" (filtra por papel por dentro)
    Route::inertia('/settings/profile', 'Settings/Profile');
    Route::inertia('/search', 'Search/Results');
    Route::inertia('/403', 'Errors/Forbidden');
    Route::inertia('/404', 'Errors/NotFound');
    Route::inertia('/500', 'Errors/ServerError');

    // --- Balcão: dono, gerente, recepção e barbeiro (agenda + clientes) ---
    // Barbeiro é operacional como a recepção (vê/trabalha a agenda), mas sem finanças
    // (escondidas no dashboard) e sem gestão (barbeiros/serviços/comissões/relatórios).
    Route::middleware('role:owner,manager,receptionist,barber')->group(function () {
        Route::get('/appointments', [AppointmentController::class, 'indexPage'])->name('appointments.index');
        Route::get('/appointments/create', [AppointmentController::class, 'createPage'])->name('appointments.create');
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'showPage'])->name('appointments.show');

        Route::inertia('/customers', 'Customers/Index');
        Route::inertia('/customers/create', 'Customers/Create');
        Route::inertia('/customers/{id}', 'Customers/Show')->where('id', '[0-9]+');
        Route::inertia('/customers/{id}/edit', 'Customers/Edit')->where('id', '[0-9]+');
    });

    // --- Gestão: só dono e gerente ---
    Route::middleware('role:owner,manager')->group(function () {
        Route::inertia('/barbers', 'Barbers/Index');
        Route::inertia('/barbers/create', 'Barbers/Create');
        Route::inertia('/barbers/{id}', 'Barbers/Show')->where('id', '[0-9]+');
        Route::inertia('/barbers/{id}/edit', 'Barbers/Edit')->where('id', '[0-9]+');
        Route::inertia('/barbers/{id}/schedule', 'Barbers/Schedule')->where('id', '[0-9]+');

        Route::inertia('/services', 'Services/Index');
        Route::inertia('/services/create', 'Services/Create');
        Route::inertia('/services/{id}/edit', 'Services/Edit')->where('id', '[0-9]+');

        Route::inertia('/commissions', 'Commission/Index');
        Route::inertia('/commissions/{id}', 'Commission/Show')->where('id', '[0-9]+');

        Route::inertia('/reports', 'Reports/Index');

        Route::inertia('/settings/business', 'Settings/Business');
        Route::inertia('/settings/hours', 'Settings/Hours');
        Route::inertia('/settings/notifications', 'Settings/Notifications');

        // Caixa de entrada (conversas) é operacional; o SETUP (credenciais) é só do dono, abaixo.
        Route::inertia('/whatsapp', 'WhatsApp/Inbox');
        Route::inertia('/whatsapp/{id}', 'WhatsApp/Conversation')->where('id', '[0-9]+');
    });

    // --- Só dono (conta: acessos, cobrança, credenciais de integração) ---
    Route::middleware('role:owner')->group(function () {
        Route::inertia('/settings/team', 'Settings/Team');
        Route::inertia('/settings/units', 'Settings/Units');
        Route::inertia('/billing', 'Billing/Index');
        Route::inertia('/whatsapp/setup', 'WhatsApp/Setup');
    });
});
