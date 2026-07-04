<?php

namespace App\Enums;

enum BillingPlan: string
{
    case solo = 'solo';
    case barbearia = 'barbearia';

    public function price(): float
    {
        return match ($this) {
            self::solo => 59.00,
            self::barbearia => 119.00,
        };
    }

    /**
     * Limite ÚNICO de funcionários do plano (toda pessoa conta: dono, barbeiros,
     * gerente, recepção). Um número só evita brechas (ex.: cadastrar barbeiro
     * como recepcionista) e segue o padrão de mercado (cobrança por profissional).
     * É o ÚNICO diferencial entre os planos: todas as funcionalidades (bot de
     * WhatsApp 24h incluso) estão nos dois.
     */
    public function staffLimit(): int
    {
        return match ($this) {
            self::solo => 1,
            self::barbearia => 10,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::solo => 'Solo',
            self::barbearia => 'Barbearia',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::solo => '1 profissional · tudo incluso: agenda, bot de WhatsApp 24h, link de agendamento, comissões e relatórios',
            self::barbearia => 'Até 10 profissionais · tudo incluso: agenda, bot de WhatsApp 24h, link de agendamento, comissões e relatórios',
        };
    }
}
