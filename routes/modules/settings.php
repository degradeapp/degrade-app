<?php

use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [SettingsController::class, 'getProfile'])->name('profile.get');
        Route::put('/profile', [SettingsController::class, 'updateProfile'])->name('profile.update');
        Route::put('/profile/password', [SettingsController::class, 'updatePassword'])->name('profile.password.update');
        Route::post('/profile/avatar', [SettingsController::class, 'updateAvatar'])->name('profile.avatar.update');
        Route::delete('/profile/avatar', [SettingsController::class, 'deleteAvatar'])->name('profile.avatar.delete');

        Route::middleware('role:owner,manager')->group(function () {
            Route::get('/tenant/settings', [SettingsController::class, 'getTenantSettings'])->name('tenant.settings.get');
            Route::put('/tenant/settings', [SettingsController::class, 'updateTenantSettings'])->name('tenant.settings.update');
            Route::put('/tenant/business-hours', [SettingsController::class, 'updateBusinessHours'])->name('tenant.business-hours.update');

            Route::get('/notification-settings', [NotificationSettingsController::class, 'show'])->name('notification-settings.show');
            Route::put('/notification-settings', [NotificationSettingsController::class, 'update'])->name('notification-settings.update');

            Route::post('/tenant/logo', [SettingsController::class, 'updateLogo'])->name('tenant.logo.update');
            Route::delete('/tenant/logo', [SettingsController::class, 'deleteLogo'])->name('tenant.logo.delete');
        });

        Route::middleware('role:owner')->group(function () {
            Route::get('/tenant/team', [SettingsController::class, 'listTeam'])->name('tenant.team.list');
            Route::post('/tenant/team', [SettingsController::class, 'inviteTeamMember'])->name('tenant.team.invite');
            Route::delete('/tenant/team/{user}', [SettingsController::class, 'removeTeamMember'])->name('tenant.team.remove');

            // Exclusão da conta/barbearia (encerra tudo). Exige a senha no corpo.
            Route::delete('/account', [SettingsController::class, 'deleteAccount'])->name('account.delete');
        });
    });
});
