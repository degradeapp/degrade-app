<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\Tenant;

class TenantContext
{
    protected ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
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
