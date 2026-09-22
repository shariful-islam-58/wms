<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'unit' => fake()->randomElement(['ea', 'kg', 'box', 'pallet']),
            'status' => ProductStatus::Active,
            'low_stock_threshold' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductStatus::Inactive,
        ]);
    }

    public function withLowStockThreshold(string|float $threshold = 10): static
    {
        return $this->state(fn (): array => [
            'low_stock_threshold' => $threshold,
        ]);
    }
}
