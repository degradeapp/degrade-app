<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Health check pro monitor externo (UptimeRobot): 200 se tudo ok, 503 se algo falhou.
 * É público, então só diz ok/error por componente — nunca mensagem de exceção (que
 * vazaria host, usuário do banco, caminhos). O detalhe do erro vai pro log/Sentry.
 *
 * Cobre as falhas SILENCIOSAS de um VPS: worker da fila morto (jobs envelhecendo),
 * cron parado (sem batimento do scheduler = sem lembrete, sem backup, sem trial:expire)
 * e disco enchendo (backup diário + logs).
 */
class HealthController extends Controller
{
    public const SCHEDULER_HEARTBEAT_KEY = 'health:scheduler-heartbeat';

    public function check(): JsonResponse
    {
        $components = [
            'database' => $this->safely(fn () => DB::select('SELECT 1') !== null),
            'queue' => $this->safely(fn () => ! DB::table('jobs')
                ->where('available_at', '<', now()->subMinutes(10)->getTimestamp())
                ->whereNull('reserved_at')
                ->exists()),
            'scheduler' => $this->safely(fn () => (int) Cache::get(self::SCHEDULER_HEARTBEAT_KEY, 0) >= now()->subMinutes(5)->getTimestamp()),
            'storage' => $this->safely(function () {
                $total = @disk_total_space(storage_path());
                $free = @disk_free_space(storage_path());

                return $total && $free !== false && ($free / $total) > 0.10;
            }),
        ];

        $healthy = ! in_array('error', $components, true);

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'components' => $components,
        ], $healthy ? 200 : 503);
    }

    private function safely(callable $check): string
    {
        try {
            return $check() ? 'ok' : 'error';
        } catch (\Throwable $e) {
            report($e);

            return 'error';
        }
    }
}
