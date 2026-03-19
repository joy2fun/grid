<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashFlow>
 */
class CashFlowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'amount' => fake()->randomFloat(2, -50000, 50000),
            'notes' => fake()->optional(0.7)->sentence(),
        ];
    }

    /**
     * Cash out flow (negative amount).
     */
    public function outflow(): static
    {
        return $this->state(fn (array $attributes): array => [
            'amount' => -abs(fake()->randomFloat(2, 1000, 50000)),
        ]);
    }

    /**
     * Cash in flow (positive amount).
     */
    public function inflow(): static
    {
        return $this->state(fn (array $attributes): array => [
            'amount' => abs(fake()->randomFloat(2, 1000, 50000)),
        ]);
    }
}
