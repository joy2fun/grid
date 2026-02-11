<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trade>
 */
class TradeFactory extends Factory
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
            'type' => fake()->randomElement(['buy', 'sell']),
            'price' => fake()->randomFloat(2, 10, 100),
            'quantity' => fake()->numberBetween(100, 1000),
            'executed_at' => now(),
        ];
    }
}
