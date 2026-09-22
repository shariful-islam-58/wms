<?php

namespace App\Services\Wms;

use App\Enums\StockMovementType;
use App\Exceptions\WmsConflictException;
use App\Jobs\ProcessLowStockAlert;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    /**
     * @return array{inventory: Inventory, reference_number: string}
     */
    public function receive(
        User $actor,
        int $productId,
        int $locationId,
        string $quantity,
        ?string $reference = null,
    ): array {
        return DB::transaction(function () use ($actor, $productId, $locationId, $quantity, $reference): array {
            $product = $this->requireActiveProduct($productId);
            $location = $this->requireActiveLocation($locationId);
            $referenceNumber = $this->resolveReference($reference);

            $inventory = $this->lockOrCreateBalance($product->id, $location->id);
            $inventory->quantity = bcadd((string) $inventory->quantity, $quantity, 4);
            $inventory->save();

            StockMovement::query()->create([
                'product_id' => $product->id,
                'source_location_id' => null,
                'destination_location_id' => $location->id,
                'quantity' => $quantity,
                'type' => StockMovementType::Receive,
                'reference_number' => $referenceNumber,
                'performed_by_user_id' => $actor->id,
                'created_at' => now(),
            ]);

            $this->enqueueLowStockCheck($product->id);

            return [
                'inventory' => $inventory->fresh(['product', 'location']) ?? $inventory,
                'reference_number' => $referenceNumber,
            ];
        });
    }

    /**
     * @return array{source: Inventory, destination: Inventory, reference_number: string}
     */
    public function transfer(
        User $actor,
        int $productId,
        int $fromLocationId,
        int $toLocationId,
        string $quantity,
        ?string $reference = null,
    ): array {
        if ($fromLocationId === $toLocationId) {
            throw new WmsConflictException('Source and destination locations must differ.');
        }

        return DB::transaction(function () use ($actor, $productId, $fromLocationId, $toLocationId, $quantity, $reference): array {
            $product = $this->requireActiveProduct($productId);
            $this->requireActiveLocation($fromLocationId);
            $this->requireActiveLocation($toLocationId);
            $referenceNumber = $this->resolveReference($reference);

            $orderedIds = [$fromLocationId, $toLocationId];
            sort($orderedIds);

            $balances = [];
            foreach ($orderedIds as $locationId) {
                $balances[$locationId] = $this->lockOrCreateBalance($product->id, $locationId);
            }

            $source = $balances[$fromLocationId];
            $destination = $balances[$toLocationId];

            if (bccomp((string) $source->quantity, $quantity, 4) < 0) {
                throw new WmsConflictException('Insufficient stock at source location.');
            }

            $source->quantity = bcsub((string) $source->quantity, $quantity, 4);
            $source->save();

            $destination->quantity = bcadd((string) $destination->quantity, $quantity, 4);
            $destination->save();

            $now = now();

            StockMovement::query()->create([
                'product_id' => $product->id,
                'source_location_id' => $fromLocationId,
                'destination_location_id' => null,
                'quantity' => $quantity,
                'type' => StockMovementType::Transfer,
                'reference_number' => $referenceNumber,
                'performed_by_user_id' => $actor->id,
                'created_at' => $now,
            ]);

            StockMovement::query()->create([
                'product_id' => $product->id,
                'source_location_id' => null,
                'destination_location_id' => $toLocationId,
                'quantity' => $quantity,
                'type' => StockMovementType::Transfer,
                'reference_number' => $referenceNumber,
                'performed_by_user_id' => $actor->id,
                'created_at' => $now,
            ]);

            $this->enqueueLowStockCheck($product->id);

            return [
                'source' => $source->fresh(['product', 'location']) ?? $source,
                'destination' => $destination->fresh(['product', 'location']) ?? $destination,
                'reference_number' => $referenceNumber,
            ];
        });
    }

    /**
     * @return array{inventory: Inventory, reference_number: string}
     */
    public function dispatch(
        User $actor,
        int $productId,
        int $locationId,
        string $quantity,
        ?string $reference = null,
    ): array {
        return DB::transaction(function () use ($actor, $productId, $locationId, $quantity, $reference): array {
            $product = $this->requireActiveProduct($productId);
            $this->requireActiveLocation($locationId);
            $referenceNumber = $this->resolveReference($reference);

            $inventory = $this->lockOrCreateBalance($product->id, $locationId);

            if (bccomp((string) $inventory->quantity, $quantity, 4) < 0) {
                throw new WmsConflictException('Insufficient stock at location.');
            }

            $inventory->quantity = bcsub((string) $inventory->quantity, $quantity, 4);
            $inventory->save();

            StockMovement::query()->create([
                'product_id' => $product->id,
                'source_location_id' => $locationId,
                'destination_location_id' => null,
                'quantity' => $quantity,
                'type' => StockMovementType::Dispatch,
                'reference_number' => $referenceNumber,
                'performed_by_user_id' => $actor->id,
                'created_at' => now(),
            ]);

            $this->enqueueLowStockCheck($product->id);

            return [
                'inventory' => $inventory->fresh(['product', 'location']) ?? $inventory,
                'reference_number' => $referenceNumber,
            ];
        });
    }

    private function requireActiveProduct(int $productId): Product
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            throw new WmsConflictException('Product not found.');
        }

        if (! $product->isActive()) {
            throw new WmsConflictException('Product is inactive.');
        }

        return $product;
    }

    private function requireActiveLocation(int $locationId): Location
    {
        $location = Location::query()->with('warehouse')->find($locationId);

        if ($location === null) {
            throw new WmsConflictException('Location not found.');
        }

        if (! $location->isActive() || ! $location->warehouse->isActive()) {
            throw new WmsConflictException('Location or warehouse is inactive.');
        }

        return $location;
    }

    private function resolveReference(?string $reference): string
    {
        $trimmed = $reference !== null ? trim($reference) : '';

        return $trimmed !== '' ? $trimmed : 'WMS-'.Str::upper(Str::ulid());
    }

    private function lockOrCreateBalance(int $productId, int $locationId): Inventory
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $existing = Inventory::query()
                ->where('product_id', $productId)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            try {
                Inventory::query()->create([
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'quantity' => 0,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Peer won the insert; loop and lock the winning row.
            }
        }

        $inventory = Inventory::query()
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if ($inventory === null) {
            throw new WmsConflictException('Unable to lock inventory balance.');
        }

        return $inventory;
    }

    private function enqueueLowStockCheck(int $productId): void
    {
        DB::afterCommit(function () use ($productId): void {
            ProcessLowStockAlert::dispatch($productId);
        });
    }
}
