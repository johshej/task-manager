<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTokenRequest;
use App\Http\Resources\V1\TokenResource;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TokenController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = ApiToken::where('tokenable_type', $request->user()::class)
            ->where('tokenable_id', $request->user()->id)
            ->latest()
            ->get();

        return TokenResource::collection($tokens);
    }

    public function store(StoreTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $newToken = $request->user()->createApiToken(
            name: $validated['name'],
            isAi: $validated['is_ai'] ?? false,
            abilities: $validated['abilities'] ?? ['*'],
            version: $validated['version'] ?? null,
        );

        return (new TokenResource($newToken->accessToken))
            ->withPlainToken($newToken->plainTextToken)
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, ApiToken $apiToken): JsonResponse
    {
        abort_if(
            $apiToken->tokenable_id !== $request->user()->id
            || $apiToken->tokenable_type !== $request->user()::class,
            403,
        );

        $apiToken->delete();

        return response()->json(null, 204);
    }
}
