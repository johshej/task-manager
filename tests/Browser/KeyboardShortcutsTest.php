<?php

use App\Enums\EpicStatus;
use App\Enums\TaskStatus;
use App\Models\Epic;
use App\Models\Feature;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('g then e navigates to epics index from the epics index itself, not the edit route', function () {
    Epic::factory()->create(['name' => 'Selectable Epic', 'status' => EpicStatus::Active]);

    $page = visit(route('epics'));

    $page->assertNoJavaScriptErrors()
        ->keys('body', 'ArrowDown')
        ->keys('body', 'g')
        ->keys('body', 'e')
        ->assertUrlIs(route('epics'));
});

test('g then e navigates to epics index from the epic board, not the edit epic shortcut', function () {
    $epic = Epic::factory()->create(['name' => 'Board Epic', 'status' => EpicStatus::Active]);

    $page = visit(route('epics.board', $epic));

    $page->assertNoJavaScriptErrors()
        ->keys('body', 'g')
        ->keys('body', 'e')
        ->assertUrlIs(route('epics'));
});

test('kanban feature headers are selectable and reorderable with the keyboard', function () {
    $epic = Epic::factory()->create(['status' => EpicStatus::Active]);
    $featureOne = Feature::factory()->for($epic)->create(['name' => 'Feature One', 'order_index' => 0]);
    Task::factory()->for($featureOne)->create(['status' => TaskStatus::Todo, 'order_index' => 0]);
    $featureTwo = Feature::factory()->for($epic)->create(['name' => 'Feature Two', 'order_index' => 1]);
    Task::factory()->for($featureTwo)->create(['status' => TaskStatus::Todo, 'order_index' => 0]);

    $page = visit(route('epics.board.kanban', $epic));

    $page->assertNoJavaScriptErrors()
        ->click('Feature Two')
        ->keys('body', ['{Shift}', 'ArrowUp'])
        ->wait(0.5)
        ->assertSeeInOrder(['Feature Two', 'Feature One']);
});

test('kanban tasks within a feature are reorderable with the keyboard', function () {
    $epic = Epic::factory()->create(['status' => EpicStatus::Active]);
    $feature = Feature::factory()->for($epic)->create();
    Task::factory()->for($feature)->create(['title' => 'First Task', 'status' => TaskStatus::Todo, 'order_index' => 0]);
    Task::factory()->for($feature)->create(['title' => 'Second Task', 'status' => TaskStatus::Todo, 'order_index' => 1]);

    $page = visit(route('epics.board.kanban', $epic));

    $page->assertNoJavaScriptErrors()
        ->click('Second Task')
        ->keys('body', ['{Shift}', 'ArrowUp'])
        ->wait(0.5)
        ->assertSeeInOrder(['Second Task', 'First Task']);
});
