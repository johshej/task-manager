<?php

namespace Database\Factories;

use App\Models\EpicTemplate;
use App\Models\EpicTemplateFeature;
use App\Models\FeatureTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EpicTemplateFeature>
 */
class EpicTemplateFeatureFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'epic_template_id' => EpicTemplate::factory(),
            'feature_template_id' => FeatureTemplate::factory(),
            'order_index' => fake()->numberBetween(0, 100),
        ];
    }
}
