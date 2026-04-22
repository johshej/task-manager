<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Feature;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'feature_id' => Feature::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(TaskStatus::cases()),
            'priority' => fake()->numberBetween(0, 10),
            'assigned_to' => null,
            'order_index' => fake()->numberBetween(0, 100),
        ];
    }

    public function todo(): static
    {
        return $this->state(['status' => TaskStatus::Todo]);
    }

    public function doing(): static
    {
        return $this->state(['status' => TaskStatus::Doing]);
    }
}
