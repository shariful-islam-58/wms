<?php

namespace Database\Factories;

use App\Enums\WarehouseStatus;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('WH-###')),
            'name' => fake()->company().' Warehouse',
            'address' => fake()->optional()->address(),
            'status' => WarehouseStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => WarehouseStatus::Inactive,
        ]);
    }
}
