<?php

namespace App\Modules\Whatsapp\Enums;

enum WhatsappBotState: string
{
    case greeting = 'greeting';
    case choosing_service = 'choosing_service';
    case choosing_barber = 'choosing_barber';
    case choosing_date = 'choosing_date';
    case choosing_slot = 'choosing_slot';
    case confirming = 'confirming';
    case done = 'done';
    case human_handoff = 'human_handoff';

    public function label(): string
    {
        return match ($this) {
            self::greeting => 'Saudação',
            self::choosing_service => 'Escolhendo serviço',
            self::choosing_barber => 'Escolhendo barbeiro',
            self::choosing_date => 'Escolhendo data',
            self::choosing_slot => 'Escolhendo horário',
            self::confirming => 'Confirmando',
            self::done => 'Concluído',
            self::human_handoff => 'Atendimento humano',
        };
    }
}
