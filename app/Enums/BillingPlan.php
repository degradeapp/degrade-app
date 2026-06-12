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

    /**
     * Limite ÚNICO de funcionários do plano (toda pessoa conta: dono, barbeiros,
     * gerente, recepção). Um número só evita brechas (ex.: cadastrar barbeiro
     * como recepcionista) e segue o padrão de mercado (cobrança por profissional).
     */
    public function staffLimit(): int
    {
        return match ($this) {
            self::solo => 1,
            self::barbearia => 4,
            self::rede => 10,
        };
    }

    /**
     * Quantas unidades (locais) o plano permite. Só o Rede é multiunidade.
     * O limitador real de tamanho continua sendo o de staff (staffLimit).
     */
    public function unitLimit(): int
    {
        return match ($this) {
            self::solo => 1,
            self::barbearia => 1,
            self::rede => 10,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::solo => 'Solo',
            self::barbearia => 'Barbearia',
            self::rede => 'Rede',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::solo => '1 profissional · agenda, lembrete no WhatsApp, comissões e caixa',
            self::barbearia => 'Até 4 profissionais · bot de WhatsApp 24h, relatórios completos e suporte prioritário',
            self::rede => 'Até 10 profissionais · várias unidades, relatório consolidado e suporte dedicado',
        };
    }
}
