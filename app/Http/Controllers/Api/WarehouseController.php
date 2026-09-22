<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WmsConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreWarehouseRequest;
use App\Http\Requests\Api\UpdateWarehouseRequest;
use App\Http\Resources\Api\WarehouseResource;
use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $warehouses = Warehouse::query()
            ->orderBy('code')
            ->paginate(max(1, min(100, (int) $request->integer('per_page', 15))));

        return WarehouseResource::collection($warehouses);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::query()->create($request->validated());

        return (new WarehouseResource($warehouse))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource($warehouse);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $warehouse->update($request->validated());

        return new WarehouseResource($warehouse->fresh());
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $hasInventory = Inventory::query()
            ->whereHas('location', fn ($query) => $query->where('warehouse_id', $warehouse->id))
            ->exists();

        if ($hasInventory) {
            throw new WmsConflictException('Warehouse has inventory; deactivate instead of deleting.');
        }

        $warehouse->delete();

        return response()->json(null, 204);
    }
}
