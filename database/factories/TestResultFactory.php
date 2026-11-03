<?php

namespace Database\Factories;

use App\Enums\TestResultStatus;
use App\Models\TestContext;
use App\Models\TestResult;
use App\Models\TestScenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestResult>
 */
class TestResultFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'test_scenario_id' => TestScenario::factory(),
            'test_context_id' => TestContext::factory(),
            'relevant' => true,
            'status' => TestResultStatus::Untested,
            'expectation' => null,
        ];
    }
}
