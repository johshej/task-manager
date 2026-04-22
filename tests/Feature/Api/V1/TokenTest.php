<?php

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createApiToken('default')->plainTextToken;
});

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/tokens')->assertUnauthorized();
});

test('can list own tokens', function () {
    $this->user->createApiToken('second');

    $this->withToken($this->token)
        ->getJson('/api/v1/tokens')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('cannot see tokens belonging to other users', function () {
    $other = User::factory()->create();
    $other->createApiToken('their token');

    $this->withToken($this->token)
        ->getJson('/api/v1/tokens')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('can create a user token', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/tokens', ['name' => 'CI token'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'CI token')
        ->assertJsonPath('data.is_ai', false);

    expect($response->json('data.plain_text_token'))->not->toBeNull();
    expect($response->json('data.plain_text_token'))->toContain('|');
});

test('can create an ai token', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/tokens', ['name' => 'AI agent', 'is_ai' => true, 'version' => 'claude-sonnet-4-6'])
        ->assertCreated()
        ->assertJsonPath('data.is_ai', true)
        ->assertJsonPath('data.version', 'claude-sonnet-4-6');

    expect($response->json('data.plain_text_token'))->not->toBeNull();

    $this->assertDatabaseHas('personal_access_tokens', [
        'name' => 'AI agent',
        'is_ai' => 1,
        'version' => 'claude-sonnet-4-6',
        'created_by_user_id' => $this->user->id,
    ]);
});

test('version is nullable for user tokens', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/tokens', ['name' => 'No version'])
        ->assertCreated()
        ->assertJsonPath('data.version', null);
});

test('can create a token with custom abilities', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/tokens', [
            'name' => 'Read-only',
            'abilities' => ['tasks:read', 'epics:read'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.abilities', ['tasks:read', 'epics:read']);
});

test('token creation requires a name', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/tokens', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('plain_text_token is not returned on index', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/tokens')
        ->assertSuccessful();

    expect($response->json('data.0'))->not->toHaveKey('plain_text_token');
});

test('can revoke own token', function () {
    $newToken = $this->user->createApiToken('to revoke');
    $tokenId = $newToken->accessToken->id;

    $this->withToken($this->token)
        ->deleteJson("/api/v1/tokens/{$tokenId}")
        ->assertNoContent();

    $this->assertModelMissing(ApiToken::find($tokenId) ?? new ApiToken(['id' => $tokenId]));
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
});

test('cannot revoke another users token', function () {
    $other = User::factory()->create();
    $otherToken = $other->createApiToken('theirs');
    $tokenId = $otherToken->accessToken->id;

    $this->withToken($this->token)
        ->deleteJson("/api/v1/tokens/{$tokenId}")
        ->assertForbidden();

    $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);
});

test('created_by_user_id is always set to the authenticated user', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/tokens', ['name' => 'Any token', 'is_ai' => false]);

    $this->assertDatabaseHas('personal_access_tokens', [
        'name' => 'Any token',
        'created_by_user_id' => $this->user->id,
    ]);
});
