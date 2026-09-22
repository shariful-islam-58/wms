<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\StockMovementResource;
use App\Services\Wms\StockAuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class StockMovementController extends Controller
{
    public function __construct(private StockAuditService $stockAuditService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'warehouse_id' => ['sometimes', 'integer', 'exists:warehouses,id'],
            'type' => ['sometimes', 'nullable', Rule::enum(StockMovementType::class)],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->stockAuditService->paginate($filters);

        return StockMovementResource::collection($paginator);
    }
}
