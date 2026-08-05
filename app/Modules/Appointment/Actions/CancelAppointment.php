<?php

namespace App\Modules\Appointment\Actions;

use App\Enums\AppointmentStatus;
use App\Events\AppointmentCancelled;
use App\Modules\Appointment\Models\Appointment;

readonly class CancelAppointment
{
    public function __invoke(Appointment $appointment, int $userId, ?string $reason = null): Appointment
    {
        // Idempotente, mesma razão do CompleteAppointment (toque duplo no 3G):
        // sem isto o segundo toque redisparava a mensagem de cancelamento.
        if ($appointment->status === AppointmentStatus::cancelled) {
            return $appointment;
        }

        // Concluído não volta atrás: a comissão já foi gerada e ficaria órfã
        // (paga) enquanto a receita sairia do relatório, que filtra por
        // 'completed'. Para desfazer um clique errado, o caminho é estornar a
        // comissão, não reescrever o histórico do atendimento.
        if ($appointment->status === AppointmentStatus::completed) {
            throw new \Exception('Um atendimento concluído não pode ser cancelado.');
        }

        $appointment->update([
            'status' => AppointmentStatus::cancelled,
        ]);

        AppointmentCancelled::dispatch($appointment);

        return $appointment;
    }
}
