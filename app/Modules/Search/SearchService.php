<?php

namespace App\Modules\Search;

use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

        $cacheKey = "search:{$tenantId}:".hash('sha256', mb_strtolower($query)).":page:{$page}";
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

    private function searchCustomers(int $tenantId, string $query): Collection
    {
        return Customer::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
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
        return Barber::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
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
        return Service::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
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

    public function clearCache(int $tenantId): void
    {
        Cache::flush();
    }
}
