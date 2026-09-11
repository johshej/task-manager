<?php

use App\Models\FeatureTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('feature template page renders', function () {
    $template = FeatureTemplate::factory()->create();

    $this->get(route('templates.feature', $template))->assertOk();
});

test('can update a feature template', function () {
    $template = FeatureTemplate::factory()->create(['name' => 'Old Name']);

    Livewire::test('pages::templates.feature', ['featureTemplate' => $template])
        ->set('editName', 'New Name')
        ->set('editTdd', '1')
        ->call('updateFeatureTemplate')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('feature_templates', ['id' => $template->id, 'name' => 'New Name', 'tdd' => true]);
});

test('can add a task to a feature template', function () {
    $template = FeatureTemplate::factory()->create();

    Livewire::test('pages::templates.feature', ['featureTemplate' => $template])
        ->set('newTaskTitle', 'Do the thing')
        ->set('newTaskPriority', 8)
        ->call('createTask')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('feature_template_tasks', [
        'feature_template_id' => $template->id,
        'title' => 'Do the thing',
        'priority' => 8,
    ]);
});

test('can edit a feature template task', function () {
    $template = FeatureTemplate::factory()->create();
    $task = $template->tasks()->create(['title' => 'Old title', 'priority' => 5]);

    Livewire::test('pages::templates.feature', ['featureTemplate' => $template])
        ->call('openEditTask', $task->id)
        ->set('editTaskTitle', 'New title')
        ->call('updateTask')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('feature_template_tasks', ['id' => $task->id, 'title' => 'New title']);
});

test('can delete a feature template task', function () {
    $template = FeatureTemplate::factory()->create();
    $task = $template->tasks()->create(['title' => 'Delete me']);

    Livewire::test('pages::templates.feature', ['featureTemplate' => $template])
        ->call('confirmDeleteTask', $task->id)
        ->call('deleteTask');

    $this->assertDatabaseMissing('feature_template_tasks', ['id' => $task->id]);
});

test('sortTasks reorders feature template tasks', function () {
    $template = FeatureTemplate::factory()->create();
    $taskA = $template->tasks()->create(['title' => 'A', 'order_index' => 0]);
    $taskB = $template->tasks()->create(['title' => 'B', 'order_index' => 1]);
    $taskC = $template->tasks()->create(['title' => 'C', 'order_index' => 2]);

    Livewire::test('pages::templates.feature', ['featureTemplate' => $template])
        ->call('sortTasks', $taskC->id, 0);

    expect($taskC->fresh()->order_index)->toBe(0);
    expect($taskA->fresh()->order_index)->toBe(1);
    expect($taskB->fresh()->order_index)->toBe(2);
});
