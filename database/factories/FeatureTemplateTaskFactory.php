<?php

namespace Database\Factories;

use App\Models\FeatureTemplate;
use App\Models\FeatureTemplateTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureTemplateTask>
 */
class FeatureTemplateTaskFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'feature_template_id' => FeatureTemplate::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'priority' => fake()->numberBetween(0, 10),
            'order_index' => fake()->numberBetween(0, 100),
        ];
    }
}
