<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActorType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreHistoryRequest;
use App\Http\Resources\V1\EpicHistoryResource;
use App\Models\ApiToken;
use App\Models\Epic;
use App\Models\EpicHistory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class EpicHistoryController extends Controller
{
    public function index(Epic $epic): AnonymousResourceCollection
    {
        return EpicHistoryResource::collection(
            $epic->history()->get()
        );
    }

    public function store(StoreHistoryRequest $request, Epic $epic): EpicHistoryResource
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

        $history = EpicHistory::create([
            'epic_id' => $epic->id,
            'changed_by_user_id' => $userId,
            'changed_by_token_id' => $tokenId,
            'actor_type' => $actorType,
            'actor_name' => $actorName,
            ...$request->validated(),
        ]);

        return new EpicHistoryResource($history);
    }
}
