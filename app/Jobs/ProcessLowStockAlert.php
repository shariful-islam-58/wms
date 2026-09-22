<?php

namespace App\Jobs;

use App\Models\Inventory;
use App\Models\LowStockAlert;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessLowStockAlert implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $productId) {}

    public function handle(): void
    {
        $product = Product::query()->find($this->productId);

        if ($product === null || $product->low_stock_threshold === null) {
            return;
        }

        $total = (string) Inventory::query()
            ->where('product_id', $product->id)
            ->sum('quantity');

        if (bccomp($total, (string) $product->low_stock_threshold, 4) >= 0) {
            return;
        }

        $alert = LowStockAlert::query()->create([
            'product_id' => $product->id,
            'total_quantity' => $total,
            'threshold' => $product->low_stock_threshold,
            'created_at' => now(),
        ]);

        Log::info('WMS low-stock alert', [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'total_quantity' => $total,
            'threshold' => (string) $product->low_stock_threshold,
            'alert_id' => $alert->id,
        ]);

        $alert->notified_at = now();
        $alert->save();
    }
}
