<?php

use App\Enums\ActorType;
use App\Enums\EpicStatus;
use App\Enums\FeatureStatus;
use App\Enums\HistoryAction;
use App\Enums\TaskStatus;
use App\Models\Epic;
use App\Models\Feature;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// ── Epics index ───────────────────────────────────────────────────────────────

test('guests are redirected from epics index', function () {
    auth()->logout();

    $this->get(route('epics'))->assertRedirect(route('login'));
});

test('epics index page renders', function () {
    $this->get(route('epics'))->assertOk();
});

test('epics index lists epics', function () {
    Epic::factory()->create(['name' => 'Alpha Epic', 'status' => EpicStatus::Active]);
    Epic::factory()->create(['name' => 'Beta Epic', 'status' => EpicStatus::Paused]);

    Livewire::test('pages::epics.index')
        ->assertSee('Alpha Epic')
        ->assertSee('Beta Epic');
});

test('epics index shows empty state when no epics', function () {
    Livewire::test('pages::epics.index')
        ->assertSee('No epics yet');
});

test('can create an epic', function () {
    Livewire::test('pages::epics.index')
        ->set('name', 'My New Epic')
        ->set('description', 'A description')
        ->call('createEpic')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('epics', [
        'name' => 'My New Epic',
        'description' => 'A description',
        'status' => EpicStatus::Active->value,
    ]);
});

test('epic creation requires a name', function () {
    Livewire::test('pages::epics.index')
        ->set('name', '')
        ->call('createEpic')
        ->assertHasErrors(['name' => 'required']);
});

test('can edit an epic', function () {
    $epic = Epic::factory()->create(['name' => 'Old Name', 'status' => EpicStatus::Active]);

    Livewire::test('pages::epics.index')
        ->call('editEpic', $epic->id)
        ->set('editName', 'New Name')
        ->set('editStatus', EpicStatus::Paused->value)
        ->call('updateEpic')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('epics', [
        'id' => $epic->id,
        'name' => 'New Name',
        'status' => EpicStatus::Paused->value,
    ]);
});

test('epic index renders edit button with quoted uuid', function () {
    $epic = Epic::factory()->create(['name' => 'Test Epic']);

    Livewire::test('pages::epics.index')
        ->assertSeeHtml("editEpic('{$epic->id}')");
});

test('epic index renders delete button with quoted uuid', function () {
    $epic = Epic::factory()->create(['name' => 'Test Epic']);

    Livewire::test('pages::epics.index')
        ->assertSeeHtml("confirmDeleteEpic('{$epic->id}')");
});

// ── Epic board ────────────────────────────────────────────────────────────────

test('epic board page renders', function () {
    $epic = Epic::factory()->create();

    $this->get(route('epics.board', $epic))->assertOk();
});

test('epic board shows features and tasks', function () {
    $epic = Epic::factory()->create(['name' => 'My Epic']);
    $feature = Feature::factory()->for($epic)->create(['name' => 'Feature One']);
    Task::factory()->for($feature)->create(['title' => 'Task One']);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->assertSee('Feature One')
        ->assertSee('Task One');
});

test('board renders feature buttons with quoted uuids', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->assertSeeHtml("openEditFeature('{$feature->id}')")
        ->assertSeeHtml("openAddTask('{$feature->id}')")
        ->assertSeeHtml("openTask('{$task->id}')");
});

test('can create a feature on the board', function () {
    $epic = Epic::factory()->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('newFeatureName', 'New Feature')
        ->call('createFeature')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('features', ['epic_id' => $epic->id, 'name' => 'New Feature']);
});

test('can create a task on the board', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openAddTask', $feature->id)
        ->set('newTaskTitle', 'New Task')
        ->set('newTaskPriority', 3)
        ->call('createTask')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tasks', [
        'feature_id' => $feature->id,
        'title' => 'New Task',
        'priority' => 3,
    ]);
});

test('can edit a feature on the board', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create(['name' => 'Old Name', 'status' => FeatureStatus::Planned]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openEditFeature', $feature->id)
        ->set('editFeatureName', 'New Name')
        ->set('editFeatureStatus', FeatureStatus::Active->value)
        ->call('updateFeature')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('features', [
        'id' => $feature->id,
        'name' => 'New Name',
        'status' => FeatureStatus::Active->value,
    ]);
});

test('can update a task from the board', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create(['title' => 'Old Title', 'status' => TaskStatus::Todo]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $task->id)
        ->call('startEditingTask')
        ->set('editTaskTitle', 'Updated Title')
        ->set('editTaskStatus', TaskStatus::Doing->value)
        ->set('editTaskPriority', 7)
        ->call('saveTask')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => 'Updated Title',
        'status' => TaskStatus::Doing->value,
    ]);
});

// ── Repository URL ────────────────────────────────────────────────────────────

test('can create an epic with an SSH repository URL', function () {
    Livewire::test('pages::epics.index')
        ->set('name', 'SSH Epic')
        ->set('repositoryUrl', 'git@github.com:johshej/laravel-vhost-manager.git')
        ->call('createEpic')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('epics', [
        'name' => 'SSH Epic',
        'repository_url' => 'git@github.com:johshej/laravel-vhost-manager.git',
    ]);
});

test('can create an epic with an HTTPS repository URL', function () {
    Livewire::test('pages::epics.index')
        ->set('name', 'HTTPS Epic')
        ->set('repositoryUrl', 'https://github.com/johshej/laravel-vhost-manager.git')
        ->call('createEpic')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('epics', [
        'repository_url' => 'https://github.com/johshej/laravel-vhost-manager.git',
    ]);
});

test('invalid repository URL is rejected on create', function () {
    Livewire::test('pages::epics.index')
        ->set('name', 'Bad Epic')
        ->set('repositoryUrl', 'not-a-valid-url')
        ->call('createEpic')
        ->assertHasErrors(['repositoryUrl']);
});

test('invalid repository URL is rejected on edit', function () {
    $epic = Epic::factory()->create();

    Livewire::test('pages::epics.index')
        ->call('editEpic', $epic->id)
        ->set('editRepositoryUrl', 'not-a-valid-url')
        ->call('updateEpic')
        ->assertHasErrors(['editRepositoryUrl']);
});

test('repository URL is displayed on the epics list', function () {
    Epic::factory()->create([
        'name' => 'Linked Epic',
        'repository_url' => 'git@github.com:johshej/my-repo.git',
    ]);

    Livewire::test('pages::epics.index')
        ->assertSee('git@github.com:johshej/my-repo.git');
});

test('repository URL is displayed on the epic board', function () {
    $epic = Epic::factory()->create([
        'repository_url' => 'git@github.com:johshej/board-repo.git',
    ]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->assertSee('git@github.com:johshej/board-repo.git');
});

// ── UI flow ───────────────────────────────────────────────────────────────────

test('full UI flow: epic with SSH URL, feature, task, status change, and user history', function () {
    // Create epic with SSH repository URL
    Livewire::test('pages::epics.index')
        ->set('name', 'Flow Epic')
        ->set('repositoryUrl', 'git@github.com:johshej/flow-repo.git')
        ->call('createEpic')
        ->assertHasNoErrors();

    $epic = Epic::where('name', 'Flow Epic')->firstOrFail();
    expect($epic->repository_url)->toBe('git@github.com:johshej/flow-repo.git');

    // Create a feature on the board
    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('newFeatureName', 'Flow Feature')
        ->call('createFeature')
        ->assertHasNoErrors();

    $feature = $epic->features()->where('name', 'Flow Feature')->firstOrFail();

    // Create a task
    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openAddTask', $feature->id)
        ->set('newTaskTitle', 'Flow Task')
        ->set('newTaskPriority', 5)
        ->call('createTask')
        ->assertHasNoErrors();

    $task = $feature->tasks()->where('title', 'Flow Task')->firstOrFail();

    // Edit the task status
    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $task->id)
        ->call('startEditingTask')
        ->set('editTaskStatus', TaskStatus::Doing->value)
        ->call('saveTask')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'status' => TaskStatus::Doing->value,
    ]);

    // History is attributed to the logged-in user
    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'action' => HistoryAction::StatusChanged->value,
        'actor_type' => ActorType::User->value,
        'changed_by_user_id' => $this->user->id,
    ]);
});

test('board shows AI badge for AI-changed tasks', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create(['title' => 'AI Touched Task']);

    // Simulate AI history entry on the task
    $aiToken = $this->user->createAiToken('test-ai');
    $task->history()->create([
        'changed_by_token_id' => $aiToken->accessToken->id,
        'actor_type' => ActorType::Ai,
        'action' => HistoryAction::StatusChanged,
        'old_values' => ['status' => TaskStatus::Todo->value],
        'new_values' => ['status' => TaskStatus::Doing->value],
        'created_at' => now(),
    ]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->assertSee('AI Touched Task')
        ->assertSee('AI');
});
