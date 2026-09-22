<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WmsConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProductRequest;
use App\Http\Requests\Api\UpdateProductRequest;
use App\Http\Resources\Api\ProductResource;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->orderBy('sku')
            ->paginate(max(1, min(100, (int) $request->integer('per_page', 15))));

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::query()->create($request->validated());

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->fresh());
    }

    public function destroy(Product $product): JsonResponse
    {
        if (Inventory::query()->where('product_id', $product->id)->exists()) {
            throw new WmsConflictException('Product has inventory; deactivate instead of deleting.');
        }

        $product->delete();

        return response()->json(null, 204);
    }
}
