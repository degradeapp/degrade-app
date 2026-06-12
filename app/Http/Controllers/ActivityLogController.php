<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function indexPage(): Response
    {
        return Inertia::render('Audit/Index');
    }

    public function index(): JsonResponse
    {
        $tenantId = app('tenant')->id;

        // Tipo do modelo em pt-BR (minúsculo, pra ler "Criou serviço").
        $typeLabels = [
            'Service' => 'serviço',
            'Barber' => 'barbeiro',
            'Customer' => 'cliente',
            'Appointment' => 'agendamento',
            'Commission' => 'comissão',
            'User' => 'acesso',
            'Tenant' => 'barbearia',
            'BarberSchedule' => 'horário',
            'BarberTimeOff' => 'folga',
            'AppointmentService' => 'serviço do agendamento',
            'Subscription' => 'assinatura',
        ];

        $logs = DB::table('activity_log')
            ->leftJoin('users', 'users.id', '=', 'activity_log.user_id')
            ->where('activity_log.tenant_id', $tenantId)
            ->select(
                'activity_log.id',
                'activity_log.action',
                'activity_log.model_type',
                'activity_log.model_id',
                'activity_log.old_values',
                'activity_log.new_values',
                'activity_log.metadata',
                'activity_log.created_at',
                'users.name as user_name',
                'users.email as user_email',
            )
            ->orderByDesc('activity_log.created_at')
            ->limit(100)
            ->get()
            ->map(function ($r) use ($typeLabels) {
                $basename = class_basename($r->model_type ?? '');

                // Em remoção o registro já não existe: o nome vem do old_values.
                $raw = $r->action === 'deleted' ? $r->old_values : $r->new_values;
                $values = is_string($raw) ? json_decode($raw, true) : $raw;
                $values = is_array($values) ? $values : [];

                return [
                    'id' => $r->id,
                    'action' => $r->action,
                    'model_label' => $typeLabels[$basename] ?? mb_strtolower($basename),
                    'model_id' => $r->model_id,
                    'entity_label' => $this->entityLabel($basename, $values),
                    'user_name' => $r->user_name,
                    'user_email' => $r->user_email,
                    'metadata' => is_string($r->metadata) ? json_decode($r->metadata, true) : $r->metadata,
                    'created_at' => $r->created_at,
                ];
            });

        return response()->json(['data' => $logs]);
    }

    /**
     * Rótulo legível da entidade a partir dos valores já gravados na auditoria
     * (sem query extra). Ex.: serviço/cliente/barbeiro → nome; comissão → valor.
     */
    private function entityLabel(string $basename, array $values): ?string
    {
        return match ($basename) {
            'Service', 'Barber', 'Customer', 'User' => $values['name'] ?? null,
            'Commission' => isset($values['amount'])
                ? 'R$ '.number_format((float) $values['amount'], 2, ',', '.')
                : null,
            'BarberTimeOff' => $values['reason'] ?? null,
            default => null,
        };
    }
}
