<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case scheduled = 'scheduled';
    case completed = 'completed';
    case cancelled = 'cancelled';
    case no_show = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::scheduled => 'Agendado',
            self::completed => 'Concluído',
            self::cancelled => 'Cancelado',
            self::no_show => 'Não Compareceu',
        };
    }
}
