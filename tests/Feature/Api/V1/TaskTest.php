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
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->feature = Feature::factory()->create();
});

test('can list tasks for a feature', function () {
    Task::factory()->count(3)->for($this->feature)->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/features/{$this->feature->id}/tasks")
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('task response includes last_change fields', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertSuccessful()
        ->assertJsonStructure(['data' => ['id', 'status', 'last_change', 'last_change_at']]);
});

test('creating a task generates a history entry', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/tasks', [
            'feature_id' => $this->feature->id,
            'title' => 'My Task',
        ])
        ->assertCreated();

    $task = Task::where('title', 'My Task')->first();
    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'action' => HistoryAction::Created->value,
        'actor_type' => ActorType::User->value,
    ]);
});

test('creating task via ai token logs actor_type as ai', function () {
    $aiToken = $this->user->createAiToken('ai-agent');

    $this->withToken($aiToken->plainTextToken)
        ->postJson('/api/v1/tasks', [
            'feature_id' => $this->feature->id,
            'title' => 'AI Task',
        ])
        ->assertCreated();

    $task = Task::where('title', 'AI Task')->first();
    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'actor_type' => ActorType::Ai->value,
    ]);
});

test('can show a task', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $task->id);
});

test('can update a task', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Updated title'])
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Updated title');

    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'action' => HistoryAction::Updated->value,
    ]);
});

test('can delete a task', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/tasks/{$task->id}")
        ->assertNoContent();

    $this->assertModelMissing($task);

    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'action' => HistoryAction::Deleted->value,
    ]);
});

test('can patch task status', function () {
    $task = Task::factory()->todo()->for($this->feature)->create();

    $this->withToken($this->token)
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::Doing->value])
        ->assertSuccessful()
        ->assertJsonPath('data.status', TaskStatus::Doing->value);

    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'action' => HistoryAction::StatusChanged->value,
    ]);
});

test('can patch task assign', function () {
    $assignee = User::factory()->create();
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->patchJson("/api/v1/tasks/{$task->id}/assign", ['assigned_to' => $assignee->id])
        ->assertSuccessful()
        ->assertJsonPath('data.assigned_to', $assignee->id);

    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'action' => HistoryAction::Assigned->value,
    ]);
});

test('can patch task priority', function () {
    $task = Task::factory()->for($this->feature)->create(['priority' => 0]);

    $this->withToken($this->token)
        ->patchJson("/api/v1/tasks/{$task->id}/priority", ['priority' => 5])
        ->assertSuccessful()
        ->assertJsonPath('data.priority', 5);

    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'action' => HistoryAction::PriorityChanged->value,
    ]);
});

test('can patch task order', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->patchJson("/api/v1/tasks/{$task->id}/order", ['order_index' => 3])
        ->assertSuccessful()
        ->assertJsonPath('data.order_index', 3);
});

test('task status must be valid enum value', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'flying'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('last_change reflects actor type in task response', function () {
    $task = Task::factory()->for($this->feature)->create();

    $this->withToken($this->token)
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => TaskStatus::Done->value]);

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertSuccessful();

    expect($response->json('data.last_change.actor_type'))->toBe(ActorType::User->value);
    expect($response->json('data.last_change.label'))->toBe('User');
});
