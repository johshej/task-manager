<?php

namespace App\Observers;

use App\Enums\ActorType;
use App\Enums\HistoryAction;
use App\Models\ApiToken;
use App\Models\Epic;
use App\Models\EpicHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class EpicObserver
{
    public function created(Epic $epic): void
    {
        $this->record($epic, HistoryAction::Created, null, $epic->getAttributes());
    }

    public function updated(Epic $epic): void
    {
        $dirty = $epic->getDirty();

        if (empty($dirty)) {
            return;
        }

        $old = array_intersect_key($epic->getOriginal(), $dirty);

        $this->record($epic, HistoryAction::Updated, $old, $dirty);
    }

    public function deleting(Epic $epic): void
    {
        $this->record($epic, HistoryAction::Deleted, $epic->getOriginal(), null);
    }

    private function record(Epic $epic, HistoryAction $action, ?array $oldValues, ?array $newValues): void
    {
        [$actorType, $actorName, $userId, $tokenId] = $this->resolveActor();

        EpicHistory::create([
            'epic_id' => $epic->id,
            'changed_by_user_id' => $userId,
            'changed_by_token_id' => $tokenId,
            'actor_type' => $actorType,
            'actor_name' => $actorName,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /** @return array{ActorType, string|null, string|null, string|null} */
    private function resolveActor(): array
    {
        $token = Request::user()?->currentAccessToken();

        if ($token instanceof ApiToken) {
            $actorType = $token->is_ai ? ActorType::Ai : ActorType::User;
            $userId = $token->is_ai ? null : $token->tokenable_id;

            return [$actorType, $token->name, $userId, $token->id];
        }

        $user = Auth::user();

        return [ActorType::User, $user?->name, $user?->id, null];
    }
}
