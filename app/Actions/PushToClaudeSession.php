<?php

namespace App\Actions;

use App\Models\ClaudeSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushToClaudeSession
{
    public function handle(string $entityType, string $entityId, string $message): void
    {
        $column = $entityType.'_id';

        ClaudeSession::where($column, $entityId)
            ->where('last_seen_at', '>', now()->subMinutes(15))
            ->each(function (ClaudeSession $session) use ($message) {
                $this->push($session, $message);
            });
    }

    private function push(ClaudeSession $session, string $message): void
    {
        try {
            Http::timeout(5)->post($session->daemon_url.'/push', [
                'message' => $message,
                'project_path' => $session->project_path,
            ]);
        } catch (\Exception $e) {
            Log::debug('Claude session push failed', [
                'session' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
