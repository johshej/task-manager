<?php

namespace Database\Factories;

use App\Models\ClaudeSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClaudeSession>
 */
class ClaudeSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'daemon_url' => 'http://100.64.0.'.fake()->numberBetween(1, 254).':7373',
            'project_path' => '/home/'.fake()->userName().'/projects/'.fake()->slug(),
            'last_seen_at' => now(),
        ];
    }
}
