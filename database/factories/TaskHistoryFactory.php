<?php

namespace Database\Factories;

use App\Enums\ActorType;
use App\Enums\HistoryAction;
use App\Models\Task;
use App\Models\TaskHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskHistory>
 */
class TaskHistoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'changed_by_user_id' => null,
            'changed_by_token_id' => null,
            'actor_type' => fake()->randomElement(ActorType::cases()),
            'action' => fake()->randomElement(HistoryAction::cases()),
            'old_values' => null,
            'new_values' => null,
            'created_at' => now(),
        ];
    }

    public function byUser(int $userId): static
    {
        return $this->state([
            'changed_by_user_id' => $userId,
            'actor_type' => ActorType::User,
        ]);
    }

    public function byAi(int $tokenId): static
    {
        return $this->state([
            'changed_by_token_id' => $tokenId,
            'actor_type' => ActorType::Ai,
        ]);
    }
}
