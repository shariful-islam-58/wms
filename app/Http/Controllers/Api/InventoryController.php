<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StockMutationRequest;
use App\Http\Requests\Api\TransferStockRequest;
use App\Http\Resources\Api\InventoryResource;
use App\Models\Inventory;
use App\Models\User;
use App\Services\Wms\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $inventories = Inventory::query()
            ->with(['product', 'location'])
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('location_id'), fn ($query) => $query->where('location_id', $request->integer('location_id')))
            ->orderBy('id')
            ->paginate(max(1, min(100, (int) $request->integer('per_page', 15))));

        return InventoryResource::collection($inventories);
    }

    public function receive(StockMutationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->inventoryService->receive(
            $user,
            (int) $request->validated('product_id'),
            (int) $request->validated('location_id'),
            (string) $request->validated('quantity'),
            $request->validated('reference'),
        );

        return response()->json([
            'data' => new InventoryResource($result['inventory']),
            'reference_number' => $result['reference_number'],
        ]);
    }

    public function transfer(TransferStockRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->inventoryService->transfer(
            $user,
            (int) $request->validated('product_id'),
            (int) $request->validated('from_location_id'),
            (int) $request->validated('to_location_id'),
            (string) $request->validated('quantity'),
            $request->validated('reference'),
        );

        return response()->json([
            'data' => [
                'source' => new InventoryResource($result['source']),
                'destination' => new InventoryResource($result['destination']),
            ],
            'reference_number' => $result['reference_number'],
        ]);
    }

    public function dispatchStock(StockMutationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->inventoryService->dispatch(
            $user,
            (int) $request->validated('product_id'),
            (int) $request->validated('location_id'),
            (string) $request->validated('quantity'),
            $request->validated('reference'),
        );

        return response()->json([
            'data' => new InventoryResource($result['inventory']),
            'reference_number' => $result['reference_number'],
        ]);
    }
}
