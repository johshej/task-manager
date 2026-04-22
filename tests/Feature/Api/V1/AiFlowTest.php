<?php

use App\Enums\ActorType;
use App\Enums\HistoryAction;
use App\Enums\TaskStatus;
use App\Models\Feature;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->aiToken = $this->user->createAiToken('test-ai-agent');
});

// ── Authentication ────────────────────────────────────────────────────────────

test('ai token can authenticate against the api', function () {
    $this->withToken($this->aiToken->plainTextToken)
        ->getJson('/api/v1/epics')
        ->assertSuccessful();
});

test('ai token is flagged as is_ai in token list', function () {
    $response = $this->withToken($this->aiToken->plainTextToken)
        ->getJson('/api/v1/tokens')
        ->assertSuccessful();

    $token = collect($response->json('data'))->first();
    expect($token['is_ai'])->toBeTrue();
});

// ── Epic management ───────────────────────────────────────────────────────────

test('ai agent can create an epic with an SSH repository URL', function () {
    $this->withToken($this->aiToken->plainTextToken)
        ->postJson('/api/v1/epics', [
            'name' => 'AI Epic',
            'repository_url' => 'git@github.com:johshej/ai-project.git',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'AI Epic')
        ->assertJsonPath('data.repository_url', 'git@github.com:johshej/ai-project.git');
});

test('ai agent epic creation rejects invalid repository URL', function () {
    $this->withToken($this->aiToken->plainTextToken)
        ->postJson('/api/v1/epics', [
            'name' => 'Bad Epic',
            'repository_url' => 'not-a-url',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('repository_url');
});

// ── Task history attribution ──────────────────────────────────────────────────

test('ai agent task creation is attributed to ai in history', function () {
    $feature = Feature::factory()->create();

    $this->withToken($this->aiToken->plainTextToken)
        ->postJson('/api/v1/tasks', [
            'feature_id' => $feature->id,
            'title' => 'AI Created Task',
        ])
        ->assertCreated();

    $task = Task::where('title', 'AI Created Task')->firstOrFail();

    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'action' => HistoryAction::Created->value,
        'actor_type' => ActorType::Ai->value,
        'changed_by_user_id' => null,
    ]);
});

test('ai agent status update is attributed to ai in history', function () {
    $task = Task::factory()->todo()->create();

    $this->withToken($this->aiToken->plainTextToken)
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::Doing->value])
        ->assertSuccessful();

    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'action' => HistoryAction::StatusChanged->value,
        'actor_type' => ActorType::Ai->value,
    ]);
});

test('ai agent priority update is attributed to ai in history', function () {
    $task = Task::factory()->create(['priority' => 3]);

    $this->withToken($this->aiToken->plainTextToken)
        ->patchJson("/api/v1/tasks/{$task->id}/priority", ['priority' => 8])
        ->assertSuccessful();

    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'action' => HistoryAction::PriorityChanged->value,
        'actor_type' => ActorType::Ai->value,
    ]);
});

test('task last_change reflects ai actor after ai status update', function () {
    $task = Task::factory()->todo()->create();

    $this->withToken($this->aiToken->plainTextToken)
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::Done->value]);

    $response = $this->withToken($this->aiToken->plainTextToken)
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertSuccessful();

    expect($response->json('data.last_change.actor_type'))->toBe(ActorType::Ai->value);
    expect($response->json('data.last_change.label'))->toBe('AI');
});

test('history correctly distinguishes ai and user entries on the same task', function () {
    $userToken = $this->user->createToken('user-client')->plainTextToken;
    $task = Task::factory()->todo()->create(['priority' => 3]);

    $this->withToken($this->aiToken->plainTextToken)
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::Doing->value])
        ->assertSuccessful();

    // Reset auth guard cache so the next request resolves the user token fresh
    app('auth')->forgetGuards();

    $this->withToken($userToken)
        ->patchJson("/api/v1/tasks/{$task->id}/priority", ['priority' => 9])
        ->assertSuccessful();

    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'actor_type' => ActorType::Ai->value,
        'action' => HistoryAction::StatusChanged->value,
    ]);

    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'actor_type' => ActorType::User->value,
        'action' => HistoryAction::PriorityChanged->value,
    ]);
});

// ── End-to-end AI workflow ────────────────────────────────────────────────────

test('end-to-end: ai agent creates epic, feature, task, and progresses task to done', function () {
    // Create epic with SSH repository URL
    $epicId = $this->withToken($this->aiToken->plainTextToken)
        ->postJson('/api/v1/epics', [
            'name' => 'AI Managed Epic',
            'repository_url' => 'git@github.com:johshej/managed.git',
        ])
        ->assertCreated()
        ->json('data.id');

    // Create feature under the epic
    $featureId = $this->withToken($this->aiToken->plainTextToken)
        ->postJson('/api/v1/features', [
            'epic_id' => $epicId,
            'name' => 'Managed Feature',
        ])
        ->assertCreated()
        ->json('data.id');

    // Create task under the feature
    $taskId = $this->withToken($this->aiToken->plainTextToken)
        ->postJson('/api/v1/tasks', [
            'feature_id' => $featureId,
            'title' => 'Managed Task',
            'priority' => 7,
        ])
        ->assertCreated()
        ->json('data.id');

    // Progress through statuses
    foreach ([TaskStatus::Doing, TaskStatus::Done] as $status) {
        $this->withToken($this->aiToken->plainTextToken)
            ->patchJson("/api/v1/tasks/{$taskId}/status", ['status' => $status->value])
            ->assertSuccessful();
    }

    // All history entries are attributed to AI
    $history = Task::findOrFail($taskId)->history()->get();

    expect($history->count())->toBe(3); // created + 2 status changes
    expect($history->every(fn ($h) => $h->actor_type === ActorType::Ai))->toBeTrue();

    $this->assertDatabaseHas('tasks', [
        'id' => $taskId,
        'status' => TaskStatus::Done->value,
    ]);
});

test('ai task history is readable via the history api endpoint', function () {
    $task = Task::factory()->todo()->create();

    $this->withToken($this->aiToken->plainTextToken)
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::Doing->value]);

    $response = $this->withToken($this->aiToken->plainTextToken)
        ->getJson("/api/v1/tasks/{$task->id}/history")
        ->assertSuccessful();

    $statusEntry = collect($response->json('data'))
        ->firstWhere('action', HistoryAction::StatusChanged->value);

    expect($statusEntry['actor_type'])->toBe(ActorType::Ai->value);
    expect($statusEntry['actor_label'])->toBe('AI');
});
