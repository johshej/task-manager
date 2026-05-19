<?php

use App\Enums\ActorType;
use App\Enums\HistoryAction;
use App\Models\Epic;
use App\Models\Feature;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->epic = Epic::factory()->create();
    $this->feature = Feature::factory()->for($this->epic)->create();
});

// ── Auto-logged history ───────────────────────────────────────────────────────

test('feature creation is auto-logged in feature history', function () {
    $this->assertDatabaseHas('feature_histories', [
        'feature_id' => $this->feature->id,
        'action' => HistoryAction::Created->value,
    ]);
});

test('feature update is auto-logged in feature history', function () {
    $this->withToken($this->token)
        ->putJson("/api/v1/features/{$this->feature->id}", ['name' => 'Updated Name'])
        ->assertSuccessful();

    $this->assertDatabaseHas('feature_histories', [
        'feature_id' => $this->feature->id,
        'action' => HistoryAction::Updated->value,
    ]);
});

test('feature deletion is auto-logged in feature history', function () {
    $featureId = $this->feature->id;

    $this->withToken($this->token)
        ->deleteJson("/api/v1/features/{$featureId}")
        ->assertNoContent();

    $this->assertDatabaseHas('feature_histories', [
        'feature_id' => $featureId,
        'action' => HistoryAction::Deleted->value,
    ]);
});

// ── History read ──────────────────────────────────────────────────────────────

test('can get feature history', function () {
    $response = $this->withToken($this->token)
        ->getJson("/api/v1/features/{$this->feature->id}/history")
        ->assertSuccessful();

    expect($response->json('data'))->not->toBeEmpty();
});

test('feature history response has correct structure', function () {
    $this->withToken($this->token)
        ->getJson("/api/v1/features/{$this->feature->id}/history")
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'feature_id', 'actor_type', 'actor_label', 'actor_name', 'action', 'old_values', 'new_values', 'metadata', 'created_at'],
            ],
        ]);
});

test('feature history creation entry has correct feature id', function () {
    $response = $this->withToken($this->token)
        ->getJson("/api/v1/features/{$this->feature->id}/history")
        ->assertSuccessful();

    $created = collect($response->json('data'))->firstWhere('action', 'created');
    expect($created['feature_id'])->toBe($this->feature->id);
});

// ── Manual history write ──────────────────────────────────────────────────────

test('ai can post a note to feature history', function () {
    $aiToken = $this->user->createAiToken('claude-agent');

    $this->withToken($aiToken->plainTextToken)
        ->postJson("/api/v1/features/{$this->feature->id}/history", [
            'action' => 'note',
            'metadata' => ['message' => 'Reviewed feature scope'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.actor_type', 'ai')
        ->assertJsonPath('data.action', 'note')
        ->assertJsonPath('data.actor_name', 'claude-agent');
});

test('user can post a note to feature history', function () {
    $this->withToken($this->token)
        ->postJson("/api/v1/features/{$this->feature->id}/history", [
            'action' => 'note',
            'metadata' => ['message' => 'Added acceptance criteria'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.actor_type', 'user');
});

test('feature history note metadata is persisted', function () {
    $this->withToken($this->token)
        ->postJson("/api/v1/features/{$this->feature->id}/history", [
            'action' => 'note',
            'metadata' => ['message' => 'Some note', 'tags' => ['review', 'planning']],
        ])
        ->assertCreated()
        ->assertJsonPath('data.metadata.message', 'Some note');
});

test('feature history post requires action', function () {
    $this->withToken($this->token)
        ->postJson("/api/v1/features/{$this->feature->id}/history", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('action');
});

test('ai feature history includes actor name', function () {
    $aiToken = $this->user->createAiToken('feature-agent');

    $this->withToken($aiToken->plainTextToken)
        ->postJson("/api/v1/features/{$this->feature->id}/history", [
            'action' => 'note',
            'metadata' => ['message' => 'Analysis done'],
        ]);

    app('auth')->forgetGuards();

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/features/{$this->feature->id}/history")
        ->assertSuccessful();

    $entry = collect($response->json('data'))->firstWhere('action', 'note');
    expect($entry['actor_name'])->toBe('feature-agent');
    expect($entry['actor_type'])->toBe(ActorType::Ai->value);
});
