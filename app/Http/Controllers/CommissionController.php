<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommissionResource;
use App\Modules\Commission\Models\Commission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommissionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $query = Commission::with('barber', 'appointment');

        if (request('status')) {
            $query->where('status', request('status'));
        }

        if (request('barber_id')) {
            $query->where('barber_id', request('barber_id'));
        }

        if (request('month')) {
            $month = request('month'); // format: YYYY-MM
            $parts = explode('-', $month);
            $query->byMonth((int) $parts[0], (int) $parts[1]);
        }

        $commissions = $query->paginate(15);

        return CommissionResource::collection($commissions);
    }

    public function show(Commission $commission): JsonResponse
    {
        $this->authorize('view', $commission);

        return response()->json(new CommissionResource($commission->load('barber', 'appointment')));
    }
}
