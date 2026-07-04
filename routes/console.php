<?php

use App\Jobs\SendAppointmentReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lembrete 24h antes — todo dia às 9h
Schedule::job(new SendAppointmentReminders('24h'))->dailyAt('09:00');

// Lembrete 1h antes — a cada 15min (granularidade da janela)
Schedule::job(new SendAppointmentReminders('1h'))->everyFifteenMinutes();

// Purga das contas excluídas cuja janela de recuperação (30 dias) expirou — todo dia às 3h
Schedule::command('accounts:purge')->dailyAt('03:00');

// Backup diário do banco (local; envio externo congelado) — todo dia às 3h30
Schedule::command('db:backup')->dailyAt('03:30');
