<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClaudeSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'daemon_url' => ['required', 'url'],
            'project_path' => ['required', 'string', 'max:500'],
            'epic_id' => ['nullable', 'uuid', 'exists:epics,id'],
            'feature_id' => ['nullable', 'uuid', 'exists:features,id'],
            'task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
        ]);

        $session = ClaudeSession::create([
            ...$validated,
            'last_seen_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $session->id]], 201);
    }

    public function heartbeat(ClaudeSession $session): JsonResponse
    {
        $session->update(['last_seen_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function destroy(ClaudeSession $session): Response
    {
        $session->delete();

        return response()->noContent();
    }
}
