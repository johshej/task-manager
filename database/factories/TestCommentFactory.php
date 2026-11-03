<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TestComment;
use App\Models\TestScenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestComment>
 */
class TestCommentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'test_scenario_id' => TestScenario::factory(),
            'test_matrix_id' => fn (array $attributes) => TestScenario::find($attributes['test_scenario_id'])->test_matrix_id,
            'task_id' => Task::factory(),
        ];
    }
}
