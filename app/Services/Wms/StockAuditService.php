<?php

namespace App\Services\Wms;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class StockAuditService
{
    /**
     * @param  array{product_id?: int|null, warehouse_id?: int|null, type?: string|null, from?: string|null, to?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));

        return StockMovement::query()
            ->with(['product', 'sourceLocation.warehouse', 'destinationLocation.warehouse', 'performedBy'])
            ->when(isset($filters['product_id']), fn (Builder $query) => $query->where('product_id', $filters['product_id']))
            ->when(
                isset($filters['type']) && $filters['type'] !== null && $filters['type'] !== '',
                function (Builder $query) use ($filters): void {
                    $query->where('type', StockMovementType::from($filters['type']));
                },
            )
            ->when(
                isset($filters['from']) && $filters['from'] !== null && $filters['from'] !== '',
                fn (Builder $query) => $query->where('created_at', '>=', $filters['from']),
            )
            ->when(
                isset($filters['to']) && $filters['to'] !== null && $filters['to'] !== '',
                fn (Builder $query) => $query->where('created_at', '<=', $filters['to']),
            )
            ->when(
                isset($filters['warehouse_id']),
                function (Builder $query) use ($filters): void {
                    // R-07: warehouse_id matches when source OR destination location's warehouse equals filter
                    $warehouseId = $filters['warehouse_id'];
                    $query->where(function (Builder $inner) use ($warehouseId): void {
                        $inner->whereHas(
                            'sourceLocation',
                            fn (Builder $location) => $location->where('warehouse_id', $warehouseId),
                        )->orWhereHas(
                            'destinationLocation',
                            fn (Builder $location) => $location->where('warehouse_id', $warehouseId),
                        );
                    });
                },
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
