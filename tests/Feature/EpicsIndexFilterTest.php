<?php

use App\Enums\EpicStatus;
use App\Models\Epic;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// ── Default filter ────────────────────────────────────────────────────────────

test('epics index shows only active epics by default', function () {
    Epic::factory()->create(['name' => 'Active Epic', 'status' => EpicStatus::Active]);
    Epic::factory()->create(['name' => 'Paused Epic', 'status' => EpicStatus::Paused]);
    Epic::factory()->create(['name' => 'Archived Epic', 'status' => EpicStatus::Archived]);

    Livewire::test('pages::epics.index')
        ->assertSee('Active Epic')
        ->assertDontSee('Paused Epic')
        ->assertDontSee('Archived Epic');
});

// ── Clear filter ──────────────────────────────────────────────────────────────

test('clearing the filter shows epics of every status', function () {
    Epic::factory()->create(['name' => 'Active Epic', 'status' => EpicStatus::Active]);
    Epic::factory()->create(['name' => 'Paused Epic', 'status' => EpicStatus::Paused]);
    Epic::factory()->create(['name' => 'Archived Epic', 'status' => EpicStatus::Archived]);

    Livewire::test('pages::epics.index')
        ->set('filterStatuses', [])
        ->assertSee('Active Epic')
        ->assertSee('Paused Epic')
        ->assertSee('Archived Epic');
});

// ── Multi-status filter ───────────────────────────────────────────────────────

test('epics index applies multi-status filter correctly', function () {
    Epic::factory()->create(['name' => 'Active Epic', 'status' => EpicStatus::Active]);
    Epic::factory()->create(['name' => 'Paused Epic', 'status' => EpicStatus::Paused]);
    Epic::factory()->create(['name' => 'Archived Epic', 'status' => EpicStatus::Archived]);

    Livewire::test('pages::epics.index')
        ->set('filterStatuses', [EpicStatus::Active->value, EpicStatus::Paused->value])
        ->assertSee('Active Epic')
        ->assertSee('Paused Epic')
        ->assertDontSee('Archived Epic');
});

// ── Preference persistence ────────────────────────────────────────────────────

test('changing the filter persists it to user preferences', function () {
    Livewire::test('pages::epics.index')
        ->set('filterStatuses', [EpicStatus::Archived->value]);

    expect($this->user->fresh()->preferences['epics_filter_statuses'])
        ->toBe([EpicStatus::Archived->value]);
});

test('a returning user sees epics filtered by their last saved preference', function () {
    Epic::factory()->create(['name' => 'Active Epic', 'status' => EpicStatus::Active]);
    Epic::factory()->create(['name' => 'Archived Epic', 'status' => EpicStatus::Archived]);

    $this->user->update([
        'preferences' => ['epics_filter_statuses' => [EpicStatus::Archived->value]],
    ]);

    Livewire::test('pages::epics.index')
        ->assertDontSee('Active Epic')
        ->assertSee('Archived Epic');
});

test('a returning user who last cleared the filter sees all epics again', function () {
    Epic::factory()->create(['name' => 'Active Epic', 'status' => EpicStatus::Active]);
    Epic::factory()->create(['name' => 'Paused Epic', 'status' => EpicStatus::Paused]);

    $this->user->update([
        'preferences' => ['epics_filter_statuses' => []],
    ]);

    Livewire::test('pages::epics.index')
        ->assertSee('Active Epic')
        ->assertSee('Paused Epic');
});

test('a user with no saved preference yet gets the active-only default', function () {
    Epic::factory()->create(['name' => 'Active Epic', 'status' => EpicStatus::Active]);
    Epic::factory()->create(['name' => 'Paused Epic', 'status' => EpicStatus::Paused]);

    expect($this->user->fresh()->preferences)->toBeNull();

    Livewire::test('pages::epics.index')
        ->assertSee('Active Epic')
        ->assertDontSee('Paused Epic');
});
