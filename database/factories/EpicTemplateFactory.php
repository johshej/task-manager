<?php

namespace Database\Factories;

use App\Models\EpicTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EpicTemplate>
 */
class EpicTemplateFactory extends Factory
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
