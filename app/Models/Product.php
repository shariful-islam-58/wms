<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property string $unit
 * @property ProductStatus $status
 * @property string|null $low_stock_threshold
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['sku', 'name', 'description', 'unit', 'status', 'low_stock_threshold'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'low_stock_threshold' => 'decimal:4',
        ];
    }

    /**
     * @return HasMany<Inventory, $this>
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return HasMany<LowStockAlert, $this>
     */
    public function lowStockAlerts(): HasMany
    {
        return $this->hasMany(LowStockAlert::class);
    }

    public function isActive(): bool
    {
        return $this->status === ProductStatus::Active;
    }
}
