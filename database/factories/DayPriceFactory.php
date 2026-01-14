<?php

namespace Database\Factories;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DayPrice>
 */
class DayPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_id' => Stock::factory(),
            'date' => fake()->date(),
            'open_price' => fake()->randomFloat(2, 10, 200),
            'high_price' => fake()->randomFloat(2, 10, 200),
            'low_price' => fake()->randomFloat(2, 10, 200),
            'close_price' => fake()->randomFloat(2, 10, 200),
            'volume' => fake()->numberBetween(1000, 1000000),
        ];
    }
}
