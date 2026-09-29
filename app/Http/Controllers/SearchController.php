<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Modules\Search\Resources\SearchResultResource;
use App\Modules\Search\SearchService;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __construct(private SearchService $searchService) {}

    public function index(SearchRequest $request): JsonResponse
    {
        $tenantId = app('tenant')?->id ?? auth()->user()?->tenant_id;

        if (! $tenantId) {
            return response()->json([
                'error' => 'Tenant não identificado',
            ], 403);
        }

        $query = $request->input('q');
        $page = (int) ($request->input('page', 1) ?? 1);

        $results = $this->searchService->search($tenantId, $query, $page);

        // O cache da busca é por barbearia (não por papel), então o recorte por papel é
        // feito AQUI, depois do cache. Recepção e barbeiro não veem o celular nem a
        // comissão dos colegas, nem quanto cada cliente gastou.
        $items = collect($results['results']);
        if (! $request->user()?->canSeeFinance()) {
            $items = $items->map(function (array $item) {
                if ($item['type'] === 'barber') {
                    $item['phone'] = null;
                    unset($item['metadata']['commission']);
                }
                if ($item['type'] === 'customer') {
                    unset($item['metadata']['total_spent']);
                }

                return $item;
            });
        }

        return response()->json([
            'data' => SearchResultResource::collection($items),
            'pagination' => [
                'total' => $results['total'],
                'page' => $results['page'],
                'per_page' => $results['per_page'],
                'has_more' => $results['has_more'],
            ],
        ]);
    }
}
