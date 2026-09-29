<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Str;

/**
 * O endereço do link público de agendamento (/agendar/{slug}). É o que o dono põe na
 * bio do Instagram e imprime no QR do balcão, então precisa ser legível
 * ("barbearia-do-joao"), único e estável.
 *
 * O cadastro nasce com um slug aleatório (barbearia-xxxxxxxx) porque o nome da loja
 * só é informado no onboarding; lá ele vira o nome de verdade.
 */
class TenantSlug
{
    public const MIN = 3;

    public const MAX = 40;

    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Palavras que não podem ser endereço de barbearia (confusão com o próprio app). */
    private const RESERVED = [
        'admin', 'api', 'app', 'agendar', 'billing', 'degrade', 'degradeapp', 'suporte',
        'login', 'register', 'cadastro', 'termos', 'terms', 'privacy', 'privacidade',
        'teste', 'demo', 'www', 'painel', 'configuracoes', 'settings',
    ];

    /** Slug aleatório do cadastro, ainda não trocado pelo nome da barbearia. */
    public static function isPlaceholder(?string $slug): bool
    {
        return (bool) preg_match('/^barbearia-[a-z0-9]{8}$/', (string) $slug);
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, self::RESERVED, true);
    }

    /**
     * Endereço a partir do nome ("Barbearia do João" → "barbearia-do-joao"). Se já
     * existir (inclusive conta excluída ainda na janela de 30 dias), numera: -2, -3...
     */
    public static function fromName(string $name, ?int $ignoreTenantId = null): string
    {
        $base = Str::limit(Str::slug($name), self::MAX - 3, '');
        $base = trim($base, '-');

        if (strlen($base) < self::MIN || self::isReserved($base)) {
            $base = 'barbearia-'.$base;
            $base = trim(Str::limit($base, self::MAX - 3, ''), '-');
        }

        $slug = $base;
        for ($n = 2; self::taken($slug, $ignoreTenantId); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    public static function taken(string $slug, ?int $ignoreTenantId = null): bool
    {
        return Tenant::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreTenantId, fn ($q) => $q->where('id', '!=', $ignoreTenantId))
            ->exists();
    }
}
