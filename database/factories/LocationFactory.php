<?php

namespace Database\Factories;

use App\Enums\LocationStatus;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'code' => strtoupper(fake()->unique()->bothify('LOC-###')),
            'name' => fake()->words(2, true),
            'status' => LocationStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => LocationStatus::Inactive,
        ]);
    }
}
