<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WmsConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLocationRequest;
use App\Http\Requests\Api\UpdateLocationRequest;
use App\Http\Resources\Api\LocationResource;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationController extends Controller
{
    public function index(Warehouse $warehouse): AnonymousResourceCollection
    {
        $locations = $warehouse->locations()->orderBy('code')->paginate(15);

        return LocationResource::collection($locations);
    }

    public function store(StoreLocationRequest $request, Warehouse $warehouse): JsonResponse
    {
        $location = $warehouse->locations()->create($request->validated());

        return (new LocationResource($location))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Location $location): LocationResource
    {
        return new LocationResource($location);
    }

    public function update(UpdateLocationRequest $request, Location $location): LocationResource
    {
        $location->update($request->validated());

        return new LocationResource($location->fresh());
    }

    public function destroy(Location $location): JsonResponse
    {
        if (Inventory::query()->where('location_id', $location->id)->exists()) {
            throw new WmsConflictException('Location has inventory; deactivate instead of deleting.');
        }

        $location->delete();

        return response()->json(null, 204);
    }
}
