<?php

namespace App\Enums;

enum BillingPlan: string
{
    case solo = 'solo';
    case barbearia = 'barbearia';
    case rede = 'rede';

    public function price(): float
    {
        return match ($this) {
            self::solo => 59.00,
            self::barbearia => 119.00,
            self::rede => 219.00,
        };
    }

    public function barberLimit(): int
    {
        return match ($this) {
            self::solo => 1,
            self::barbearia => 4,
            self::rede => 10,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::solo => 'Solo',
            self::barbearia => 'Barbearia ⭐',
            self::rede => 'Rede',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::solo => '1 barbeiro, WhatsApp lembretes, email support',
            self::barbearia => '4 barbeiros, bot 24h, múltiplos serviços, comissões, inbox WhatsApp',
            self::rede => '10 barbeiros, múltiplas unidades, API pública, onboarding dedicado',
        };
    }
}
