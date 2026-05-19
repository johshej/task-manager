<?php

namespace App\Observers;

use App\Enums\ActorType;
use App\Enums\HistoryAction;
use App\Models\ApiToken;
use App\Models\Feature;
use App\Models\FeatureHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class FeatureObserver
{
    public function created(Feature $feature): void
    {
        $this->record($feature, HistoryAction::Created, null, $feature->getAttributes());
    }

    public function updated(Feature $feature): void
    {
        $dirty = $feature->getDirty();

        if (empty($dirty)) {
            return;
        }

        $old = array_intersect_key($feature->getOriginal(), $dirty);

        $this->record($feature, HistoryAction::Updated, $old, $dirty);
    }

    public function deleting(Feature $feature): void
    {
        $this->record($feature, HistoryAction::Deleted, $feature->getOriginal(), null);
    }

    private function record(Feature $feature, HistoryAction $action, ?array $oldValues, ?array $newValues): void
    {
        [$actorType, $actorName, $userId, $tokenId] = $this->resolveActor();

        FeatureHistory::create([
            'feature_id' => $feature->id,
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
