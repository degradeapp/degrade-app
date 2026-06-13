<?php

namespace App\Modules\Search;

use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class SearchService
{
    public function __construct(private int $resultsPerPage = 20) {}

    public function search(int $tenantId, string $query, int $page = 1): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [
                'results' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $this->resultsPerPage,
                'has_more' => false,
            ];
        }

        $cacheKey = "search:{$tenantId}:v".$this->cacheVersion($tenantId).':'.hash('sha256', mb_strtolower($query)).":page:{$page}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $allResults = collect()
            ->merge($this->searchCustomers($tenantId, $query))
            ->merge($this->searchBarbers($tenantId, $query))
            ->merge($this->searchServices($tenantId, $query))
            ->sortByDesc('relevance')
            ->values();

        $total = $allResults->count();
        $paginated = $allResults
            ->slice(($page - 1) * $this->resultsPerPage, $this->resultsPerPage)
            ->values();

        $result = [
            'results' => $paginated->toArray(),
            'total' => $total,
            'page' => $page,
            'per_page' => $this->resultsPerPage,
            'has_more' => $total > ($page * $this->resultsPerPage),
        ];

        Cache::put($cacheKey, $result, 30);

        return $result;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(Str::ascii($value));
    }

    private function digitsOnly(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    private function matchesName(string $name, string $query): bool
    {
        return str_contains($this->normalize($name), $this->normalize($query));
    }

    private function matchesPhone(?string $phone, string $query): bool
    {
        $digits = $this->digitsOnly($query);

        return $digits !== '' && str_contains($this->digitsOnly($phone), $digits);
    }

    /**
     * E1: em Postgres (prod) narra os candidatos NO BANCO (unaccent ILIKE no nome
     * + dígitos do telefone), evitando hidratar a tabela inteira. O filtro em PHP
     * abaixo continua sendo a AUTORIDADE do match, então o resultado é idêntico em
     * qualquer banco — aqui só reduzimos I/O. SQLite (dev/teste) não pré-filtra: o
     * volume é pequeno e LIKE no SQLite não é accent-insensitive como o filtro PHP.
     */
    private function prefilter(Builder $q, string $query, bool $withPhone): Builder
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $q;
        }

        $like = '%'.mb_strtolower(trim($query)).'%';
        $digits = $this->digitsOnly($query);

        return $q->where(function (Builder $w) use ($like, $digits, $withPhone) {
            $w->whereRaw('unaccent(lower(name)) like unaccent(?)', [$like]);
            if ($withPhone && $digits !== '') {
                $w->orWhereRaw("regexp_replace(coalesce(phone, ''), '\\D', '', 'g') like ?", ['%'.$digits.'%']);
            }
        });
    }

    private function searchCustomers(int $tenantId, string $query): Collection
    {
        return $this->prefilter(
            Customer::where('tenant_id', $tenantId)->whereNull('deleted_at'),
            $query,
            withPhone: true,
        )
            ->get()
            ->filter(fn (Customer $c) => $this->matchesName($c->name, $query) || $this->matchesPhone($c->phone, $query))
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'type' => 'customer',
                'name' => $customer->name,
                'phone' => $customer->phone,
                'metadata' => [
                    'total_visits' => $customer->total_visits,
                    'total_spent' => $customer->total_spent,
                    'last_visit_at' => $customer->last_visit_at?->toIso8601String(),
                ],
                'relevance' => $this->calculateRelevance($customer->name, $query),
            ])
            ->values();
    }

    private function searchBarbers(int $tenantId, string $query): Collection
    {
        return $this->prefilter(
            Barber::where('tenant_id', $tenantId)->whereNull('deleted_at'),
            $query,
            withPhone: true,
        )
            ->get()
            ->filter(fn (Barber $b) => $this->matchesName($b->name, $query) || $this->matchesPhone($b->phone, $query))
            ->map(fn (Barber $barber) => [
                'id' => $barber->id,
                'type' => 'barber',
                'name' => $barber->name,
                'phone' => $barber->phone,
                'metadata' => [
                    'is_active' => $barber->is_active,
                    'commission' => $barber->default_commission_percentage,
                ],
                'relevance' => $this->calculateRelevance($barber->name, $query),
            ])
            ->values();
    }

    private function searchServices(int $tenantId, string $query): Collection
    {
        return $this->prefilter(
            Service::where('tenant_id', $tenantId)->whereNull('deleted_at'),
            $query,
            withPhone: false,
        )
            ->get()
            ->filter(fn (Service $s) => $this->matchesName($s->name, $query))
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'type' => 'service',
                'name' => $service->name,
                'phone' => null,
                'metadata' => [
                    'price' => $service->price,
                    'is_active' => $service->is_active,
                ],
                'relevance' => $this->calculateRelevance($service->name, $query),
            ])
            ->values();
    }

    private function calculateRelevance(string $field, string $query): float
    {
        $fieldNorm = $this->normalize($field);
        $termNorm = $this->normalize(trim($query, '%'));

        if ($fieldNorm === $termNorm) {
            return 3.0; // exact match
        }

        if (str_starts_with($fieldNorm, $termNorm)) {
            return 2.0; // prefix match
        }

        return 1.0; // substring match
    }

    private function cacheVersion(int $tenantId): int
    {
        return (int) Cache::get("search:{$tenantId}:ver", 1);
    }

    /**
     * Invalida o cache de busca SÓ deste tenant. Antes era Cache::flush(), que
     * apagava o cache do app inteiro (de TODOS os tenants) a cada alteração.
     * Versão por tenant: as chaves antigas simplesmente deixam de ser lidas.
     */
    public function clearCache(int $tenantId): void
    {
        Cache::put("search:{$tenantId}:ver", $this->cacheVersion($tenantId) + 1);
    }
}
