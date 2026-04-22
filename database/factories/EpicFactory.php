<?php

namespace Database\Factories;

use App\Enums\EpicStatus;
use App\Models\Epic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Epic>
 */
class EpicFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(EpicStatus::cases()),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => EpicStatus::Active]);
    }
}
