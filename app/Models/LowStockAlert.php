<?php

namespace App\Models;

use Database\Factories\LowStockAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $total_quantity
 * @property string $threshold
 * @property Carbon|null $notified_at
 * @property Carbon|null $created_at
 * @property-read Product $product
 */
#[Fillable(['product_id', 'total_quantity', 'threshold', 'notified_at', 'created_at'])]
class LowStockAlert extends Model
{
    /** @use HasFactory<LowStockAlertFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_quantity' => 'decimal:4',
            'threshold' => 'decimal:4',
            'notified_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
