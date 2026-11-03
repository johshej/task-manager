<?php

namespace Database\Factories;

use App\Models\Epic;
use App\Models\TestMatrix;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestMatrix>
 */
class TestMatrixFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'epic_id' => Epic::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'order_index' => fake()->numberBetween(0, 100),
        ];
    }
}
