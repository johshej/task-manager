<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActorType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreHistoryRequest;
use App\Http\Resources\V1\FeatureHistoryResource;
use App\Models\ApiToken;
use App\Models\Feature;
use App\Models\FeatureHistory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class FeatureHistoryController extends Controller
{
    public function index(Feature $feature): AnonymousResourceCollection
    {
        return FeatureHistoryResource::collection(
            $feature->history()->get()
        );
    }

    public function store(StoreHistoryRequest $request, Feature $feature): FeatureHistoryResource
    {
        $token = $request->user()?->currentAccessToken();
        $actorType = ActorType::User;
        $actorName = null;
        $userId = Auth::id();
        $tokenId = null;

        if ($token instanceof ApiToken) {
            $actorType = $token->is_ai ? ActorType::Ai : ActorType::User;
            $actorName = $token->name;
            $userId = $token->is_ai ? null : $token->tokenable_id;
            $tokenId = $token->id;
        } else {
            $actorName = Auth::user()?->name;
        }

        $history = FeatureHistory::create([
            'feature_id' => $feature->id,
            'changed_by_user_id' => $userId,
            'changed_by_token_id' => $tokenId,
            'actor_type' => $actorType,
            'actor_name' => $actorName,
            ...$request->validated(),
        ]);

        return new FeatureHistoryResource($history);
    }
}
