<?php

namespace Database\Factories;

use App\Models\TestContext;
use App\Models\TestMatrix;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestContext>
 */
class TestContextFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'test_matrix_id' => TestMatrix::factory(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'order_index' => fake()->numberBetween(0, 100),
        ];
    }
}
