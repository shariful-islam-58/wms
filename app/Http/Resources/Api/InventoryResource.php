<?php

namespace App\Http\Resources\Api;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Inventory
 */
class InventoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'location_id' => $this->location_id,
            'quantity' => $this->quantity,
            'product' => new ProductResource($this->whenLoaded('product')),
            'location' => new LocationResource($this->whenLoaded('location')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
