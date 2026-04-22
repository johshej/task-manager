<?php

namespace Database\Factories;

use App\Enums\FeatureStatus;
use App\Models\Epic;
use App\Models\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'epic_id' => Epic::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(FeatureStatus::cases()),
            'order_index' => fake()->numberBetween(0, 100),
        ];
    }
}
