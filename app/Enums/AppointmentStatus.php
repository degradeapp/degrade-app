<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case scheduled = 'scheduled';
    case confirmed = 'confirmed';
    case in_progress = 'in_progress';
    // Derivado (nunca persistido): atendimento cujo horário já passou mas que
    // ainda não foi concluído explicitamente. Pede ação do barbeiro/dono para
    // virar 'completed' e gerar a comissão.
    case awaiting_completion = 'awaiting_completion';
    case completed = 'completed';
    case cancelled = 'cancelled';
    case no_show = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::scheduled => 'Agendado',
            self::confirmed => 'Confirmado',
            self::in_progress => 'Em Atendimento',
            self::awaiting_completion => 'A concluir',
            self::completed => 'Concluído',
            self::cancelled => 'Cancelado',
            self::no_show => 'Não Compareceu',
        };
    }
}
