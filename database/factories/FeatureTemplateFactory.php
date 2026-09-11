<?php

namespace Database\Factories;

use App\Models\FeatureTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureTemplate>
 */
class FeatureTemplateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
