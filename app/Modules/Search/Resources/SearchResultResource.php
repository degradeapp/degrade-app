<?php

namespace App\Modules\Search\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'type' => $this['type'],
            'name' => $this['name'],
            'phone' => $this['phone'],
            'metadata' => $this['metadata'],
        ];
    }
}
