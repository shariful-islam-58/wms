<?php

namespace App\Http\Resources\Api;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'source_location_id' => $this->source_location_id,
            'destination_location_id' => $this->destination_location_id,
            'quantity' => $this->quantity,
            'type' => $this->type->value,
            'reference_number' => $this->reference_number,
            'performed_by_user_id' => $this->performed_by_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
