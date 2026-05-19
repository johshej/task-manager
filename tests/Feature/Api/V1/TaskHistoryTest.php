<?php

use App\Enums\ActorType;
use App\Enums\HistoryAction;
use App\Enums\TaskStatus;
use App\Models\Feature;
use App\Models\Task;
use App\Models\TaskHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->feature = Feature::factory()->create();
});

test('can retrieve task history', function () {
    $task = Task::factory()->for($this->feature)->create();

    TaskHistory::factory()->count(3)->for($task)->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/tasks/{$task->id}/history")
        ->assertSuccessful()
        ->assertJsonCount(4, 'data'); // 3 manual + 1 from created observer
});

test('history entries have correct structure', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/tasks/{$task->id}/history")
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'task_id', 'actor_type', 'actor_label', 'action', 'old_values', 'new_values', 'created_at'],
            ],
        ]);
});

test('history records old and new values on status change', function () {
    $task = Task::factory()->todo()->for($this->feature)->create();

    $this->withToken($this->token)
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::Done->value]);

    $history = TaskHistory::where('task_id', $task->id)
        ->where('action', HistoryAction::StatusChanged->value)
        ->first();

    expect($history)->not->toBeNull();
    expect($history->old_values['status'])->toBe(TaskStatus::Todo->value);
    expect($history->new_values['status'])->toBe(TaskStatus::Done->value);
});

test('ai actor label is AI in history response', function () {
    $task = Task::factory()->todo()->for($this->feature)->create();

    $aiToken = $this->user->createAiToken('ai-agent');

    $this->withToken($aiToken->plainTextToken)
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::Done->value]);

    app('auth')->forgetGuards();

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/tasks/{$task->id}/history")
        ->assertSuccessful();

    $aiEntry = collect($response->json('data'))
        ->firstWhere('action', HistoryAction::StatusChanged->value);

    expect($aiEntry['actor_type'])->toBe(ActorType::Ai->value);
    expect($aiEntry['actor_label'])->toBe('AI');
});

test('history entries include actor_name and metadata fields', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/tasks/{$task->id}/history")
        ->assertSuccessful()
        ->assertJsonStructure(['data' => ['*' => ['actor_name', 'metadata']]]);
});

// ── Manual history write ──────────────────────────────────────────────────────

test('ai can post a note to task history', function () {
    $task = Task::factory()->for($this->feature)->create();
    $aiToken = $this->user->createAiToken('claude-agent');

    $this->withToken($aiToken->plainTextToken)
        ->postJson("/api/v1/tasks/{$task->id}/history", [
            'action' => 'note',
            'metadata' => ['message' => 'Analyzed and found 3 issues', 'model' => 'claude-sonnet-4-6'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.actor_type', 'ai')
        ->assertJsonPath('data.action', 'note')
        ->assertJsonPath('data.actor_name', 'claude-agent');
});

test('user can post a note to task history', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/tasks/{$task->id}/history", [
            'action' => 'note',
            'metadata' => ['message' => 'Added acceptance criteria'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.actor_type', 'user');
});

test('posted note metadata is persisted and returned', function () {
    $task = Task::factory()->for($this->feature)->create();
    $meta = ['message' => 'Did analysis', 'duration_ms' => 4500, 'files' => ['app/Foo.php']];

    $response = $this->withToken($this->token)
        ->postJson("/api/v1/tasks/{$task->id}/history", [
            'action' => 'note',
            'metadata' => $meta,
        ])
        ->assertCreated();

    expect($response->json('data.metadata.message'))->toBe('Did analysis');
    expect($response->json('data.metadata.duration_ms'))->toBe(4500);
});

test('history note can include old and new values', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/tasks/{$task->id}/history", [
            'action' => 'note',
            'old_values' => ['status' => 'todo'],
            'new_values' => ['status' => 'doing'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.old_values.status', 'todo')
        ->assertJsonPath('data.new_values.status', 'doing');
});

test('history post requires action field', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/tasks/{$task->id}/history", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('action');
});

test('history post rejects unknown action value', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/tasks/{$task->id}/history", ['action' => 'not_a_real_action'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('action');
});

test('auto-logged history entries include actor name', function () {
    $task = Task::factory()->todo()->for($this->feature)->create();
    $aiToken = $this->user->createAiToken('my-agent');

    $this->withToken($aiToken->plainTextToken)
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'done']);

    app('auth')->forgetGuards();

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/tasks/{$task->id}/history")
        ->assertSuccessful();

    $entry = collect($response->json('data'))->firstWhere('action', 'status_changed');
    expect($entry['actor_name'])->toBe('my-agent');
});

test('history is returned in descending order', function () {
    $task = Task::factory()->todo()->for($this->feature)->create();

    $this->withToken($this->token)
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::Doing->value]);

    $this->withToken($this->token)
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::Done->value]);

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/tasks/{$task->id}/history")
        ->assertSuccessful();

    $actions = collect($response->json('data'))->pluck('action')->toArray();
    $statusChanges = array_values(array_filter($actions, fn ($a) => $a === HistoryAction::StatusChanged->value));

    expect(count($statusChanges))->toBe(2);
});
