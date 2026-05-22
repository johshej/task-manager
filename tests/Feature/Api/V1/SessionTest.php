<?php

use App\Models\ClaudeSession;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createApiToken('default')->plainTextToken;
});

test('unauthenticated requests are rejected', function () {
    $this->postJson('/api/v1/sessions', [])->assertUnauthorized();
});

test('can register a session', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/sessions', [
            'daemon_url' => 'http://100.64.0.1:7373',
            'project_path' => '/home/user/projects/myapp',
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id']]);

    $this->assertDatabaseHas('claude_sessions', [
        'daemon_url' => 'http://100.64.0.1:7373',
        'project_path' => '/home/user/projects/myapp',
    ]);

    expect($response->json('data.id'))->toBeString()->not->toBeEmpty();
});

test('can register a session with task context', function () {
    $task = Task::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/sessions', [
            'daemon_url' => 'http://100.64.0.1:7373',
            'project_path' => '/home/user/projects/myapp',
            'task_id' => $task->id,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('claude_sessions', ['task_id' => $task->id]);
});

test('register requires daemon_url and project_path', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/sessions', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['daemon_url', 'project_path']);
});

test('daemon_url must be a valid url', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/sessions', [
            'daemon_url' => 'not-a-url',
            'project_path' => '/home/user/projects/myapp',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('daemon_url');
});

test('can send heartbeat', function () {
    $session = ClaudeSession::factory()->create(['last_seen_at' => now()->subMinutes(10)]);

    $this->withToken($this->token)
        ->patchJson("/api/v1/sessions/{$session->id}/heartbeat")
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($session->fresh()->last_seen_at->isAfter(now()->subSeconds(5)))->toBeTrue();
});

test('can destroy a session', function () {
    $session = ClaudeSession::factory()->create();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/sessions/{$session->id}")
        ->assertNoContent();

    $this->assertModelMissing($session);
});
