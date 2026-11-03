<?php

namespace Database\Factories;

use App\Models\TestMatrix;
use App\Models\TestScenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestScenario>
 */
class TestScenarioFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'test_matrix_id' => TestMatrix::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(),
            'order_index' => fake()->numberBetween(0, 100),
        ];
    }
}
