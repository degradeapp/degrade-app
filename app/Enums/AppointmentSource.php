<?php

namespace App\Enums;

enum AppointmentSource: string
{
    case customer = 'customer';
    case walk_in = 'walk_in';
    case phone = 'phone';
    case whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::customer => 'Auto-agendamento',
            self::walk_in => 'Walk-in',
            self::phone => 'Telefone',
            self::whatsapp => 'WhatsApp',
        };
    }
}
