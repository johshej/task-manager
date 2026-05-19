<?php

use App\Enums\ActorType;
use App\Enums\HistoryAction;
use App\Models\Epic;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->epic = Epic::factory()->create();
});

// ── Auto-logged history ───────────────────────────────────────────────────────

test('epic creation is auto-logged in epic history', function () {
    $this->assertDatabaseHas('epic_histories', [
        'epic_id' => $this->epic->id,
        'action' => HistoryAction::Created->value,
    ]);
});

test('epic update is auto-logged in epic history', function () {
    $this->withToken($this->token)
        ->putJson("/api/v1/epics/{$this->epic->id}", ['name' => 'Updated Name'])
        ->assertSuccessful();

    $this->assertDatabaseHas('epic_histories', [
        'epic_id' => $this->epic->id,
        'action' => HistoryAction::Updated->value,
    ]);
});

test('epic deletion is auto-logged in epic history', function () {
    $epicId = $this->epic->id;

    $this->withToken($this->token)
        ->deleteJson("/api/v1/epics/{$epicId}")
        ->assertNoContent();

    $this->assertDatabaseHas('epic_histories', [
        'epic_id' => $epicId,
        'action' => HistoryAction::Deleted->value,
    ]);
});

// ── History read ──────────────────────────────────────────────────────────────

test('can get epic history', function () {
    $response = $this->withToken($this->token)
        ->getJson("/api/v1/epics/{$this->epic->id}/history")
        ->assertSuccessful();

    expect($response->json('data'))->not->toBeEmpty();
});

test('epic history response has correct structure', function () {
    $this->withToken($this->token)
        ->getJson("/api/v1/epics/{$this->epic->id}/history")
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'epic_id', 'actor_type', 'actor_label', 'actor_name', 'action', 'old_values', 'new_values', 'metadata', 'created_at'],
            ],
        ]);
});

test('epic history creation entry has correct epic id', function () {
    $response = $this->withToken($this->token)
        ->getJson("/api/v1/epics/{$this->epic->id}/history")
        ->assertSuccessful();

    $created = collect($response->json('data'))->firstWhere('action', 'created');
    expect($created['epic_id'])->toBe($this->epic->id);
});

// ── Manual history write ──────────────────────────────────────────────────────

test('ai can post a note to epic history', function () {
    $aiToken = $this->user->createAiToken('claude-agent');

    $this->withToken($aiToken->plainTextToken)
        ->postJson("/api/v1/epics/{$this->epic->id}/history", [
            'action' => 'note',
            'metadata' => ['message' => 'Started working on this epic'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.actor_type', 'ai')
        ->assertJsonPath('data.action', 'note')
        ->assertJsonPath('data.actor_name', 'claude-agent');
});

test('user can post a note to epic history', function () {
    $this->withToken($this->token)
        ->postJson("/api/v1/epics/{$this->epic->id}/history", [
            'action' => 'note',
            'metadata' => ['message' => 'Scoped the epic'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.actor_type', 'user');
});

test('epic history note metadata is persisted', function () {
    $this->withToken($this->token)
        ->postJson("/api/v1/epics/{$this->epic->id}/history", [
            'action' => 'note',
            'metadata' => ['message' => 'Epic reviewed', 'reviewer' => 'johshej'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.metadata.message', 'Epic reviewed');
});

test('epic history post requires action', function () {
    $this->withToken($this->token)
        ->postJson("/api/v1/epics/{$this->epic->id}/history", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('action');
});

test('ai epic history entry includes actor name', function () {
    $aiToken = $this->user->createAiToken('epic-scout');

    $this->withToken($aiToken->plainTextToken)
        ->postJson("/api/v1/epics/{$this->epic->id}/history", [
            'action' => 'note',
            'metadata' => ['message' => 'Scouted repositories'],
        ]);

    app('auth')->forgetGuards();

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/epics/{$this->epic->id}/history")
        ->assertSuccessful();

    $entry = collect($response->json('data'))->firstWhere('action', 'note');
    expect($entry['actor_name'])->toBe('epic-scout');
    expect($entry['actor_type'])->toBe(ActorType::Ai->value);
});
