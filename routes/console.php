<?php

use App\Http\Controllers\HealthController;
use App\Jobs\SendAppointmentReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Encerra os trials vencidos (status trial -> suspended). Morava no app/Console/Kernel.php,
// que o Laravel 12 NÃO carrega (bootstrap/app.php não o registra): nunca rodou.
Schedule::command('trial:expire')->dailyAt('02:00')->withoutOverlapping();

// Lembrete 24h antes — todo dia às 9h
Schedule::job(new SendAppointmentReminders('24h'))->dailyAt('09:00');

// Lembrete 1h antes — a cada 15min (granularidade da janela)
Schedule::job(new SendAppointmentReminders('1h'))->everyFifteenMinutes()->withoutOverlapping();

// Purga das contas excluídas cuja janela de recuperação (30 dias) expirou — todo dia às 3h
Schedule::command('accounts:purge')->dailyAt('03:00')->withoutOverlapping();

// Backup diário do banco (local; envio externo congelado) — todo dia às 3h30
Schedule::command('db:backup')->dailyAt('03:30')->withoutOverlapping();

// Batimento do scheduler: /api/health acusa 503 se o cron parar (senão lembrete,
// backup e trial:expire morrem em silêncio).
Schedule::call(fn () => Cache::put(HealthController::SCHEDULER_HEARTBEAT_KEY, now()->getTimestamp()))
    ->everyMinute()
    ->name('health-heartbeat');
