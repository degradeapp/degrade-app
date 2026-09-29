<?php

namespace App\Enums;

/**
 * Planos por FAIXA de tamanho de equipe (padrão do mercado: o AppBarber cobra
 * 1 / 2–5 / 6–15 profissionais). Decisão de 29/09/2026:
 * - 3 faixas: a barbearia típica (2 a 4 cadeiras, às vezes + recepção) cai na do meio
 *   com um salto pequeno sobre o Solo (+R$30), o que tira o incentivo de dividir o
 *   login do dono entre a equipe. Com 2 planos o salto era +R$60 (o dobro).
 * - Preço abaixo do líder em cada faixa (R$79,90 / R$109,90 / R$164,50).
 * Todas as funções em todos os planos: o único diferencial é o tamanho da equipe.
 */
enum BillingPlan: string
{
    case solo = 'solo';
    case equipe = 'equipe';
    case barbearia = 'barbearia';

    public function price(): float
    {
        return match ($this) {
            self::solo => 59.00,
            self::equipe => 89.00,
            self::barbearia => 139.00,
        };
    }

    /**
     * Limite ÚNICO de pessoas na equipe (toda pessoa conta: dono, barbeiros, gerente,
     * recepção). Um número só evita brechas (ex.: cadastrar barbeiro como
     * recepcionista). (Bot/lembretes de WhatsApp saíram da copy enquanto a integração
     * está parada: prometer o que não existe é propaganda enganosa.)
     */
    public function staffLimit(): int
    {
        return match ($this) {
            self::solo => 1,
            self::equipe => 5,
            self::barbearia => 15,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::solo => 'Solo',
            self::equipe => 'Equipe',
            self::barbearia => 'Barbearia',
        };
    }

    /** O plano em destaque ("Mais escolhido") nas telas de preço. */
    public function featured(): bool
    {
        return $this === self::equipe;
    }

    /**
     * Catálogo público (landing, tela de cobrança): derivado do enum, então preço e
     * limite nunca divergem da regra de negócio.
     *
     * @return array<int, array{plan: string, label: string, price: float, staff_limit: int, description: string, featured: bool}>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $plan) => [
            'plan' => $plan->value,
            'label' => $plan->label(),
            'price' => $plan->price(),
            'staff_limit' => $plan->staffLimit(),
            'description' => $plan->description(),
            'featured' => $plan->featured(),
        ], self::cases());
    }

    public function description(): string
    {
        return match ($this) {
            self::solo => 'Para quem atende sozinho · agenda, link de agendamento online, comissões e relatórios',
            self::equipe => 'Até 5 pessoas na equipe, contando você · agenda, link de agendamento online, comissões e relatórios',
            self::barbearia => 'Até 15 pessoas na equipe, contando você · agenda, link de agendamento online, comissões e relatórios',
        };
    }
}
