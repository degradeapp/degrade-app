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

        return response()->json([
            'data' => SearchResultResource::collection(collect($results['results'])),
            'pagination' => [
                'total' => $results['total'],
                'page' => $results['page'],
                'per_page' => $results['per_page'],
                'has_more' => $results['has_more'],
            ],
        ]);
    }
}
