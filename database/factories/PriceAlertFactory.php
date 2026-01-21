<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceAlert>
 */
class PriceAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_id' => \App\Models\Stock::factory(),
            'threshold_type' => fake()->randomElement(['rise', 'drop']),
            'threshold_value' => fake()->randomFloat(2, 50, 200),
            'last_notified_at' => fake()->optional(0.7)->dateTime(),
            'is_active' => fake()->boolean(80),
        ];
    }
}
