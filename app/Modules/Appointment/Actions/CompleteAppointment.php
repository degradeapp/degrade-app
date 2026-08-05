<?php

namespace App\Modules\Appointment\Actions;

use App\Enums\AppointmentStatus;
use App\Events\AppointmentCompleted;
use App\Modules\Appointment\Models\Appointment;

readonly class CompleteAppointment
{
    public function __invoke(Appointment $appointment, int $userId): Appointment
    {
        // IDEMPOTENTE de propósito. O dono opera pelo celular, às vezes em 3G:
        // tocar "Concluir" duas vezes porque a tela demorou é comum. Sem esta
        // guarda o segundo toque gerava comissão duplicada (pagava o barbeiro
        // duas vezes), contava a visita duas vezes no cliente e reenviava a
        // mensagem de agradecimento. Repetir agora é operação nula.
        if ($appointment->status === AppointmentStatus::completed) {
            return $appointment;
        }

        // Cancelado / falta são estados finais: concluir depois deles criaria
        // comissão para um atendimento que não aconteceu.
        if (in_array($appointment->status, [AppointmentStatus::cancelled, AppointmentStatus::no_show], true)) {
            throw new \Exception('Este atendimento está como "'.$appointment->status->label().'" e não pode ser concluído.');
        }

        $appointment->update([
            'status' => AppointmentStatus::completed,
            'completed_at' => now(),
        ]);

        AppointmentCompleted::dispatch($appointment);

        return $appointment;
    }
}
