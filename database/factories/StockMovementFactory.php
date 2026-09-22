<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'source_location_id' => null,
            'destination_location_id' => Location::factory(),
            'quantity' => fake()->randomFloat(4, 1, 100),
            'type' => StockMovementType::Receive,
            'reference_number' => 'REF-'.fake()->unique()->numerify('########'),
            'performed_by_user_id' => User::factory(),
            'created_at' => now(),
        ];
    }
}
