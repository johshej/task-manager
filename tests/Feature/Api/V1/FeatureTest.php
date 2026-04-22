<?php

use App\Enums\FeatureStatus;
use App\Models\Epic;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->epic = Epic::factory()->create();
});

test('can list features for an epic', function () {
    Feature::factory()->count(2)->for($this->epic)->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/epics/{$this->epic->id}/features")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('can create a feature', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/features', [
            'epic_id' => $this->epic->id,
            'name' => 'My Feature',
            'status' => FeatureStatus::Planned->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'My Feature')
        ->assertJsonPath('data.epic_id', $this->epic->id);

    $this->assertDatabaseHas('features', ['name' => 'My Feature', 'epic_id' => $this->epic->id]);
});

test('feature creation requires name and valid epic_id', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/features', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'epic_id']);
});

test('feature epic_id must exist', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/features', ['name' => 'Feature', 'epic_id' => 9999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('epic_id');
});

test('can update a feature', function () {
    $feature = Feature::factory()->for($this->epic)->create();

    $this->withToken($this->token)
        ->putJson("/api/v1/features/{$feature->id}", ['name' => 'Renamed'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Renamed');
});

test('can delete a feature', function () {
    $feature = Feature::factory()->for($this->epic)->create();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/features/{$feature->id}")
        ->assertNoContent();

    $this->assertModelMissing($feature);
});
