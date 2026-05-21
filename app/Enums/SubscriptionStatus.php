<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case trial = 'trial';
    case active = 'active';
    case pastDue = 'past_due';
    case suspended = 'suspended';
    case cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::trial => 'Período de Teste',
            self::active => 'Ativo',
            self::pastDue => 'Vencido',
            self::suspended => 'Suspenso',
            self::cancelled => 'Cancelado',
        };
    }

    public function isActive(): bool
    {
        return $this === self::active;
    }

    public function isTrialing(): bool
    {
        return $this === self::trial;
    }
}
