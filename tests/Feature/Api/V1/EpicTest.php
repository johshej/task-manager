<?php

use App\Enums\EpicStatus;
use App\Models\Epic;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/epics')->assertUnauthorized();
});

test('can list epics', function () {
    Epic::factory()->count(3)->create();

    $this->withToken($this->token)
        ->getJson('/api/v1/epics')
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('can create an epic', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/epics', [
            'name' => 'My Epic',
            'description' => 'A description',
            'status' => EpicStatus::Active->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'My Epic')
        ->assertJsonPath('data.status', EpicStatus::Active->value);

    $this->assertDatabaseHas('epics', ['name' => 'My Epic']);
});

test('epic creation requires a name', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/epics', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('can show an epic', function () {
    $epic = Epic::factory()->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/epics/{$epic->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $epic->id);
});

test('can update an epic', function () {
    $epic = Epic::factory()->create();

    $this->withToken($this->token)
        ->putJson("/api/v1/epics/{$epic->id}", ['name' => 'Updated'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated');

    $this->assertDatabaseHas('epics', ['id' => $epic->id, 'name' => 'Updated']);
});

test('can update an epic ai_mode, tdd, and environment', function () {
    $epic = Epic::factory()->create();

    $this->withToken($this->token)
        ->putJson("/api/v1/epics/{$epic->id}", [
            'ai_mode' => 'Updated AI instructions',
            'tdd' => true,
            'environment' => 'Production',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.ai_mode', 'Updated AI instructions')
        ->assertJsonPath('data.tdd', true)
        ->assertJsonPath('data.environment', 'Production');

    $this->assertDatabaseHas('epics', [
        'id' => $epic->id,
        'ai_mode' => 'Updated AI instructions',
        'tdd' => true,
        'environment' => 'Production',
    ]);
});

test('can delete an epic', function () {
    $epic = Epic::factory()->create();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/epics/{$epic->id}")
        ->assertNoContent();

    $this->assertModelMissing($epic);
});

test('shows 404 for unknown epic', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/epics/999')
        ->assertNotFound();
});

test('epic status must be valid enum value', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/epics', ['name' => 'Epic', 'status' => 'invalid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

// ── Repository URL filter ─────────────────────────────────────────────────────

test('can filter epics by repository url', function () {
    Epic::factory()->create(['repository_url' => 'git@github.com:user/target.git']);
    Epic::factory()->create(['repository_url' => 'git@github.com:user/other.git']);
    Epic::factory()->create(['repository_url' => null]);

    $this->withToken($this->token)
        ->getJson('/api/v1/epics?repository_url='.urlencode('git@github.com:user/target.git'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.repository_url', 'git@github.com:user/target.git');
});

test('returns empty when no epic matches repository url', function () {
    Epic::factory()->create(['repository_url' => 'git@github.com:user/other.git']);

    $this->withToken($this->token)
        ->getJson('/api/v1/epics?repository_url='.urlencode('git@github.com:user/missing.git'))
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

test('listing without filter returns all epics', function () {
    Epic::factory()->count(3)->create();

    $this->withToken($this->token)
        ->getJson('/api/v1/epics')
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});
