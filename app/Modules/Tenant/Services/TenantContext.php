<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\Tenant;

class TenantContext
{
    protected ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->applyTimezone($tenant);
    }

    /**
     * Faz a requisição (ou job) rodar no fuso da loja. Como os horários são
     * guardados como hora local (wall-clock, sem conversão na entrada), rodar
     * tudo no fuso do tenant deixa now(), os casts de data, o effectiveStatus,
     * as janelas ("hoje"/semana) e a emissão ISO corretos de uma vez só, sem
     * precisar converter fuso em cada lugar. Fallback: padrão do app (Manaus).
     */
    protected function applyTimezone(Tenant $tenant): void
    {
        $tz = $tenant->setting('timezone', config('app.timezone'));

        if (! is_string($tz) || $tz === '') {
            return;
        }

        // Fuso inválido (digitação ruim salva no settings) não pode derrubar a request.
        try {
            new \DateTimeZone($tz);
        } catch (\Throwable $e) {
            return;
        }

        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);
    }

    public function get(): Tenant
    {
        if (! $this->tenant) {
            throw new \Exception('No tenant context set');
        }

        return $this->tenant;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): int
    {
        return $this->get()->id;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function isSet(): bool
    {
        return $this->tenant !== null;
    }
}
