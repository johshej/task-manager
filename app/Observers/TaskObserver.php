<?php

namespace App\Observers;

use App\Enums\ActorType;
use App\Enums\HistoryAction;
use App\Models\ApiToken;
use App\Models\Task;
use App\Models\TaskHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class TaskObserver
{
    public function created(Task $task): void
    {
        $this->record($task, HistoryAction::Created, null, $task->getAttributes());
    }

    public function updated(Task $task): void
    {
        $dirty = $task->getDirty();

        if (empty($dirty)) {
            return;
        }

        $old = array_intersect_key($task->getOriginal(), $dirty);
        $action = $this->resolveAction($dirty);

        $this->record($task, $action, $old, $dirty);
    }

    public function deleting(Task $task): void
    {
        $this->record($task, HistoryAction::Deleted, $task->getOriginal(), null);
    }

    private function resolveAction(array $dirty): HistoryAction
    {
        if (array_key_exists('status', $dirty)) {
            return HistoryAction::StatusChanged;
        }

        if (array_key_exists('assigned_to', $dirty)) {
            return HistoryAction::Assigned;
        }

        if (array_key_exists('priority', $dirty)) {
            return HistoryAction::PriorityChanged;
        }

        return HistoryAction::Updated;
    }

    private function record(Task $task, HistoryAction $action, ?array $oldValues, ?array $newValues): void
    {
        [$actorType, $userId, $tokenId] = $this->resolveActor();

        TaskHistory::create([
            'task_id' => $task->id,
            'changed_by_user_id' => $userId,
            'changed_by_token_id' => $tokenId,
            'actor_type' => $actorType,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /** @return array{ActorType, string|null, string|null} */
    private function resolveActor(): array
    {
        $token = Request::user()?->currentAccessToken();

        if ($token instanceof ApiToken) {
            $actorType = $token->is_ai ? ActorType::Ai : ActorType::User;
            $userId = $token->is_ai ? null : $token->tokenable_id;

            return [$actorType, $userId, $token->id];
        }

        $userId = Auth::id();

        return [ActorType::User, $userId, null];
    }
}
