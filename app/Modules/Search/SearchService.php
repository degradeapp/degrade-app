<?php

namespace App\Modules\Search;

use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

readonly class SearchService
{
    public function __construct(private int $resultsPerPage = 20) {}

    public function search(int $tenantId, string $query, int $page = 1): array
    {
        if (mb_strlen($query) < 2) {
            return [
                'results' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $this->resultsPerPage,
                'has_more' => false,
            ];
        }

        $cacheKey = "search:{$tenantId}:".hash('sha256', $query).":page:{$page}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $query = trim($query);
        $searchTerm = "%{$query}%";

        $customers = $this->searchCustomers($tenantId, $searchTerm);
        $barbers = $this->searchBarbers($tenantId, $searchTerm);
        $services = $this->searchServices($tenantId, $searchTerm);

        $allResults = collect()
            ->merge($customers)
            ->merge($barbers)
            ->merge($services)
            ->sortByDesc('relevance')
            ->values();

        $total = count($allResults);
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

    private function searchCustomers(int $tenantId, string $searchTerm): Collection
    {
        return Customer::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($searchTerm) {
                $q->whereRaw('name ILIKE ?', [$searchTerm])
                    ->orWhereRaw('phone ILIKE ?', [$searchTerm]);
            })
            ->get()
            ->map(function (Customer $customer) use ($searchTerm) {
                return [
                    'id' => $customer->id,
                    'type' => 'customer',
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'metadata' => [
                        'total_visits' => $customer->total_visits,
                        'total_spent' => $customer->total_spent,
                        'last_visit_at' => $customer->last_visit_at?->toIso8601String(),
                    ],
                    'relevance' => $this->calculateRelevance($customer->name, $searchTerm),
                ];
            });
    }

    private function searchBarbers(int $tenantId, string $searchTerm): Collection
    {
        return Barber::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($searchTerm) {
                $q->whereRaw('name ILIKE ?', [$searchTerm])
                    ->orWhereRaw('phone ILIKE ?', [$searchTerm]);
            })
            ->get()
            ->map(function (Barber $barber) use ($searchTerm) {
                return [
                    'id' => $barber->id,
                    'type' => 'barber',
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'metadata' => [
                        'is_active' => $barber->is_active,
                        'commission' => $barber->default_commission_percentage,
                    ],
                    'relevance' => $this->calculateRelevance($barber->name, $searchTerm),
                ];
            });
    }

    private function searchServices(int $tenantId, string $searchTerm): Collection
    {
        return Service::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereRaw('name ILIKE ?', [$searchTerm])
            ->get()
            ->map(function (Service $service) use ($searchTerm) {
                return [
                    'id' => $service->id,
                    'type' => 'service',
                    'name' => $service->name,
                    'phone' => null,
                    'metadata' => [
                        'duration_minutes' => $service->duration_minutes,
                        'price' => $service->price,
                        'is_active' => $service->is_active,
                    ],
                    'relevance' => $this->calculateRelevance($service->name, $searchTerm),
                ];
            });
    }

    private function calculateRelevance(string $field, string $searchTerm): float
    {
        $fieldLower = mb_strtolower($field);
        $termLower = trim(mb_strtolower($searchTerm), '%');

        if ($fieldLower === $termLower) {
            return 3.0; // exact match
        }

        if (str_starts_with($fieldLower, $termLower)) {
            return 2.0; // prefix match
        }

        return 1.0; // substring match
    }

    public function clearCache(int $tenantId): void
    {
        Cache::flush(); // In production, use more targeted cache invalidation
    }
}
