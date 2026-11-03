<?php

namespace Database\Factories;

use App\Models\AutomatedTest;
use App\Models\TestScenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomatedTest>
 */
class AutomatedTestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'test_scenario_id' => TestScenario::factory(),
            'name' => fake()->sentence(5),
            'file_path' => 'tests/Feature/'.fake()->word().'Test.php',
            'kind' => fake()->randomElement(['feature', 'browser', 'unit']),
            'description' => fake()->optional()->sentence(),
            'order_index' => fake()->numberBetween(0, 100),
        ];
    }
}
