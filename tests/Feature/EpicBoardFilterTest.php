<?php

use App\Enums\FeatureStatus;
use App\Enums\TaskStatus;
use App\Models\Epic;
use App\Models\Feature;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->epic = Epic::factory()->create();
});

// ── No filter ─────────────────────────────────────────────────────────────────

test('board shows all features and tasks when no filter is applied', function () {
    $f1 = Feature::factory()->for($this->epic)->create(['name' => 'Feature Alpha', 'status' => FeatureStatus::Active]);
    $f2 = Feature::factory()->for($this->epic)->create(['name' => 'Feature Beta', 'status' => FeatureStatus::Done]);
    Task::factory()->for($f1)->create(['title' => 'Todo task', 'status' => TaskStatus::Todo]);
    Task::factory()->for($f2)->create(['title' => 'Done task', 'status' => TaskStatus::Done]);

    Livewire::test('pages::epics.⚡show', ['epic' => $this->epic])
        ->assertSee('Feature Alpha')
        ->assertSee('Feature Beta')
        ->assertSee('Todo task')
        ->assertSee('Done task');
});

// ── Task status filter ────────────────────────────────────────────────────────

test('board shows only done tasks when filtering by done', function () {
    $feature = Feature::factory()->for($this->epic)->create(['name' => 'Mixed Feature', 'status' => FeatureStatus::Active]);
    Task::factory()->for($feature)->create(['title' => 'Todo task', 'status' => TaskStatus::Todo]);
    Task::factory()->for($feature)->create(['title' => 'Done task', 'status' => TaskStatus::Done]);

    Livewire::test('pages::epics.⚡show', ['epic' => $this->epic])
        ->set('filterStatuses', [TaskStatus::Done->value])
        ->assertSee('Done task')
        ->assertDontSee('Todo task');
});

test('board hides features with no matching tasks and non-matching feature status', function () {
    $active = Feature::factory()->for($this->epic)->create(['name' => 'Active Feature', 'status' => FeatureStatus::Active]);
    Task::factory()->for($active)->create(['title' => 'Todo task', 'status' => TaskStatus::Todo]);

    Livewire::test('pages::epics.⚡show', ['epic' => $this->epic])
        ->set('filterStatuses', [TaskStatus::Done->value])
        ->assertDontSee('Active Feature')
        ->assertDontSee('Todo task');
});

// ── Feature status filter ─────────────────────────────────────────────────────

test('board shows a done feature even when it has no done tasks', function () {
    $done = Feature::factory()->for($this->epic)->create(['name' => 'Done Feature', 'status' => FeatureStatus::Done]);
    Task::factory()->for($done)->create(['title' => 'Todo task inside done feature', 'status' => TaskStatus::Todo]);

    Livewire::test('pages::epics.⚡show', ['epic' => $this->epic])
        ->set('filterStatuses', [TaskStatus::Done->value])
        ->assertSee('Done Feature');
});

test('board shows a done feature with no tasks at all when filtering by done', function () {
    Feature::factory()->for($this->epic)->create(['name' => 'Empty Done Feature', 'status' => FeatureStatus::Done]);

    Livewire::test('pages::epics.⚡show', ['epic' => $this->epic])
        ->set('filterStatuses', [TaskStatus::Done->value])
        ->assertSee('Empty Done Feature');
});

// ── Multi-status filter ───────────────────────────────────────────────────────

test('board applies multi-status filter correctly', function () {
    $f1 = Feature::factory()->for($this->epic)->create(['name' => 'Feature One', 'status' => FeatureStatus::Active]);
    $f2 = Feature::factory()->for($this->epic)->create(['name' => 'Feature Two', 'status' => FeatureStatus::Active]);
    Task::factory()->for($f1)->create(['title' => 'Doing task', 'status' => TaskStatus::Doing]);
    Task::factory()->for($f2)->create(['title' => 'Blocked task', 'status' => TaskStatus::Blocked]);
    Task::factory()->for($f2)->create(['title' => 'Todo task', 'status' => TaskStatus::Todo]);

    Livewire::test('pages::epics.⚡show', ['epic' => $this->epic])
        ->set('filterStatuses', [TaskStatus::Doing->value, TaskStatus::Blocked->value])
        ->assertSee('Feature One')
        ->assertSee('Doing task')
        ->assertSee('Feature Two')
        ->assertSee('Blocked task')
        ->assertDontSee('Todo task');
});

// ── Clear filter ──────────────────────────────────────────────────────────────

test('clearing filter restores all features and tasks', function () {
    $feature = Feature::factory()->for($this->epic)->create(['name' => 'Restored Feature', 'status' => FeatureStatus::Active]);
    Task::factory()->for($feature)->create(['title' => 'Restored task', 'status' => TaskStatus::Todo]);

    Livewire::test('pages::epics.⚡show', ['epic' => $this->epic])
        ->set('filterStatuses', [TaskStatus::Done->value])
        ->assertDontSee('Restored Feature')
        ->set('filterStatuses', [])
        ->assertSee('Restored Feature')
        ->assertSee('Restored task');
});
