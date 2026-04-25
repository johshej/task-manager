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

// ── New fields: TDD / AI mode / Environment ───────────────────────────────────

test('new fields save on epic create', function () {
    Livewire::test('pages::epics.index')
        ->set('name', 'TDD Epic')
        ->set('tdd', '1')
        ->set('aiMode', 'Do everything autonomously')
        ->set('environment', 'Development')
        ->call('createEpic')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('epics', [
        'name' => 'TDD Epic',
        'tdd' => true,
        'ai_mode' => 'Do everything autonomously',
        'environment' => 'Development',
    ]);
});

test('new fields save on epic edit', function () {
    $epic = Epic::factory()->create(['name' => 'Plain Epic']);

    Livewire::test('pages::epics.index')
        ->call('editEpic', $epic->id)
        ->set('editTdd', '0')
        ->set('editAiMode', 'Ask before each step')
        ->set('editEnvironment', 'Production')
        ->call('updateEpic')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('epics', [
        'id' => $epic->id,
        'tdd' => false,
        'ai_mode' => 'Ask before each step',
        'environment' => 'Production',
    ]);
});

test('new fields save on feature create', function () {
    $epic = Epic::factory()->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('newFeatureName', 'TDD Feature')
        ->set('newFeatureTdd', '1')
        ->set('newFeatureAiMode', 'Run tests first')
        ->set('newFeatureEnvironment', 'Staging')
        ->call('createFeature')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('features', [
        'epic_id' => $epic->id,
        'name' => 'TDD Feature',
        'tdd' => true,
        'ai_mode' => 'Run tests first',
        'environment' => 'Staging',
    ]);
});

test('new fields save on feature edit', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openEditFeature', $feature->id)
        ->set('editFeatureTdd', '0')
        ->set('editFeatureAiMode', 'Silent mode')
        ->set('editFeatureEnvironment', 'Production')
        ->call('updateFeature')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('features', [
        'id' => $feature->id,
        'tdd' => false,
        'ai_mode' => 'Silent mode',
        'environment' => 'Production',
    ]);
});

test('new fields save on task create', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openAddTask', $feature->id)
        ->set('newTaskTitle', 'TDD Task')
        ->set('newTaskTdd', '1')
        ->set('newTaskAiMode', 'Write test first')
        ->set('newTaskEnvironment', 'Development')
        ->call('createTask')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tasks', [
        'feature_id' => $feature->id,
        'title' => 'TDD Task',
        'tdd' => true,
        'ai_mode' => 'Write test first',
        'environment' => 'Development',
    ]);
});

test('new fields save on task edit', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $task->id)
        ->call('startEditingTask')
        ->set('editTaskTdd', '1')
        ->set('editTaskAiMode', 'Autonomous')
        ->set('editTaskEnvironment', 'Staging')
        ->call('saveTask')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'tdd' => true,
        'ai_mode' => 'Autonomous',
        'environment' => 'Staging',
    ]);
});

test('task inherits environment from feature when own is null', function () {
    $epic = Epic::factory()->create(['environment' => 'prod']);
    $feature = Feature::factory()->for($epic)->create(['environment' => 'staging']);
    $task = Task::factory()->for($feature)->create(['environment' => null]);

    $task->load('feature.epic');

    expect($task->resolvedEnvironment())->toBe('staging');
});

test('feature inherits environment from epic when own is null', function () {
    $epic = Epic::factory()->create(['environment' => 'prod']);
    $feature = Feature::factory()->for($epic)->create(['environment' => null]);

    $feature->load('epic');

    expect($feature->resolvedEnvironment())->toBe('prod');
});

test('task own environment overrides feature and epic', function () {
    $epic = Epic::factory()->create(['environment' => 'prod']);
    $feature = Feature::factory()->for($epic)->create(['environment' => 'staging']);
    $task = Task::factory()->for($feature)->create(['environment' => 'dev']);

    $task->load('feature.epic');

    expect($task->resolvedEnvironment())->toBe('dev');
});

test('task tdd inherits from feature when own is null', function () {
    $epic = Epic::factory()->create(['tdd' => true]);
    $feature = Feature::factory()->for($epic)->create(['tdd' => false]);
    $task = Task::factory()->for($feature)->create(['tdd' => null]);

    $task->load('feature.epic');

    expect($task->resolvedTdd())->toBeFalse();
});

test('null tdd on all ancestors resolves to null', function () {
    $epic = Epic::factory()->create(['tdd' => null]);
    $feature = Feature::factory()->for($epic)->create(['tdd' => null]);
    $task = Task::factory()->for($feature)->create(['tdd' => null]);

    $task->load('feature.epic');

    expect($task->resolvedTdd())->toBeNull();
});

// ── New task gets last execution order ────────────────────────────────────────

test('new task is appended to end of execution queue', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $existing = Task::factory()->for($feature)->create(['execution_order' => 5]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openAddTask', $feature->id)
        ->set('newTaskTitle', 'Last Task')
        ->call('createTask')
        ->assertHasNoErrors();

    $newTask = $feature->tasks()->where('title', 'Last Task')->firstOrFail();
    expect($newTask->execution_order)->toBe(6);
});

// ── Kanban view ───────────────────────────────────────────────────────────────

test('kanban view renders status columns', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    Task::factory()->for($feature)->create(['title' => 'Kanban Task', 'status' => TaskStatus::Doing]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('viewMode', 'kanban')
        ->assertSee('Kanban Task')
        ->assertSee('In Progress');
});

test('sortKanban updates task status', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create(['status' => TaskStatus::Todo]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('sortKanban', $task->id, 0, TaskStatus::Done->value);

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'status' => TaskStatus::Done->value,
    ]);
});

// ── Execution queue (sort view) ───────────────────────────────────────────────

test('sort queue view renders tasks in execution order', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    Task::factory()->for($feature)->create(['title' => 'First Task', 'execution_order' => 0]);
    Task::factory()->for($feature)->create(['title' => 'Second Task', 'execution_order' => 1]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('viewMode', 'sort')
        ->assertSeeInOrder(['First Task', 'Second Task']);
});

test('sortQueue reorders execution_order', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $taskA = Task::factory()->for($feature)->create(['execution_order' => 0]);
    $taskB = Task::factory()->for($feature)->create(['execution_order' => 1]);
    $taskC = Task::factory()->for($feature)->create(['execution_order' => 2]);

    // Move taskC to position 0 (first)
    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('sortQueue', $taskC->id, 0);

    expect($taskC->fresh()->execution_order)->toBe(0);
    expect($taskA->fresh()->execution_order)->toBe(1);
    expect($taskB->fresh()->execution_order)->toBe(2);
});

// ── Filters ───────────────────────────────────────────────────────────────────

test('filter by feature hides other features tasks in board view', function () {
    $epic = Epic::factory()->create();
    $featureA = Feature::factory()->for($epic)->create(['name' => 'Feature A']);
    $featureB = Feature::factory()->for($epic)->create(['name' => 'Feature B']);
    Task::factory()->for($featureA)->create(['title' => 'Task A']);
    Task::factory()->for($featureB)->create(['title' => 'Task B']);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('filterFeatureIds', [$featureA->id])
        ->assertSee('Task A')
        ->assertDontSee('Task B');
});

test('filter by status hides non-matching tasks', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    Task::factory()->for($feature)->create(['title' => 'Todo Task', 'status' => TaskStatus::Todo]);
    Task::factory()->for($feature)->create(['title' => 'Done Task', 'status' => TaskStatus::Done]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('filterStatuses', [TaskStatus::Todo->value])
        ->assertSee('Todo Task')
        ->assertDontSee('Done Task');
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
