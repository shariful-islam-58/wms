<?php

namespace Database\Factories;

use App\Models\LowStockAlert;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LowStockAlert>
 */
class LowStockAlertFactory extends Factory
{
    protected $model = LowStockAlert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'total_quantity' => fake()->randomFloat(4, 0, 50),
            'threshold' => 100,
            'notified_at' => null,
            'created_at' => now(),
        ];
    }
}
