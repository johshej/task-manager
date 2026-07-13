<?php

use App\Enums\ActorType;
use App\Enums\EpicStatus;
use App\Enums\FeatureStatus;
use App\Enums\HistoryAction;
use App\Enums\TaskStatus;
use App\Models\Epic;
use App\Models\Feature;
use App\Models\Task;
use App\Models\TaskHistory;
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
        ->set('filterStatuses', [])
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
        'status' => EpicStatus::New->value,
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

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openEditEpic')
        ->set('editEpicName', 'New Name')
        ->set('editEpicStatus', EpicStatus::Paused->value)
        ->call('updateEpic')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('epics', [
        'id' => $epic->id,
        'name' => 'New Name',
        'status' => EpicStatus::Paused->value,
    ]);
});

test('epic index renders edit button linking to edit route', function () {
    $epic = Epic::factory()->create(['name' => 'Test Epic', 'status' => EpicStatus::Active]);

    Livewire::test('pages::epics.index')
        ->assertSeeHtml(route('epics.board.edit', $epic));
});

test('epic index renders delete button with quoted uuid', function () {
    $epic = Epic::factory()->create(['name' => 'Test Epic', 'status' => EpicStatus::Active]);

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

test('board renders feature and task navigation links', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->assertSeeHtml(route('epics.board.feature', [$epic, $feature]))
        ->assertSeeHtml("openAddTask('{$feature->id}')")
        ->assertSeeHtml(route('epics.board.task', [$epic, $task]));
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
    $feature = Feature::factory()->for($epic)->create(['name' => 'Old Name', 'status' => FeatureStatus::Todo]);

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
        ->set('editTaskTitle', 'Updated Title')
        ->set('editTaskStatus', TaskStatus::InProgress->value)
        ->set('editTaskPriority', 7)
        ->call('saveTask')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => 'Updated Title',
        'status' => TaskStatus::InProgress->value,
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

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openEditEpic')
        ->set('editEpicRepositoryUrl', 'not-a-valid-url')
        ->call('updateEpic')
        ->assertHasErrors(['editEpicRepositoryUrl']);
});

test('repository URL is displayed on the epics list', function () {
    Epic::factory()->create([
        'name' => 'Linked Epic',
        'status' => EpicStatus::Active,
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
        ->set('editTaskStatus', TaskStatus::InProgress->value)
        ->call('saveTask')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'status' => TaskStatus::InProgress->value,
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

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openEditEpic')
        ->set('editEpicTdd', '0')
        ->set('editEpicAiMode', 'Ask before each step')
        ->set('editEpicEnvironment', 'Production')
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

// ── Kanban view ───────────────────────────────────────────────────────────────

test('kanban view renders status columns', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    Task::factory()->for($feature)->create(['title' => 'Kanban Task', 'status' => TaskStatus::InProgress]);

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

test('sortKanban reorders tasks within the same feature and status', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $taskA = Task::factory()->for($feature)->create(['title' => 'A', 'status' => TaskStatus::Todo, 'order_index' => 0]);
    $taskB = Task::factory()->for($feature)->create(['title' => 'B', 'status' => TaskStatus::Todo, 'order_index' => 1]);
    $taskC = Task::factory()->for($feature)->create(['title' => 'C', 'status' => TaskStatus::Todo, 'order_index' => 2]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('sortKanban', $taskC->id, 0, TaskStatus::Todo->value);

    expect($taskC->fresh()->order_index)->toBe(0);
    expect($taskA->fresh()->order_index)->toBe(1);
    expect($taskB->fresh()->order_index)->toBe(2);
});

test('sortKanban keeps other-status tasks in the feature ordered correctly', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $todoA = Task::factory()->for($feature)->create(['status' => TaskStatus::Todo, 'order_index' => 0]);
    Task::factory()->for($feature)->create(['status' => TaskStatus::Done, 'order_index' => 1]);
    $todoB = Task::factory()->for($feature)->create(['status' => TaskStatus::Todo, 'order_index' => 2]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('sortKanban', $todoB->id, 0, TaskStatus::Todo->value);

    expect($todoB->fresh()->order_index)->toBeLessThan($todoA->fresh()->order_index);
});

test('reordering a task in kanban is reflected on the board view', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    Task::factory()->for($feature)->create(['title' => 'First', 'status' => TaskStatus::Todo, 'order_index' => 0]);
    $second = Task::factory()->for($feature)->create(['title' => 'Second', 'status' => TaskStatus::Todo, 'order_index' => 1]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('sortKanban', $second->id, 0, TaskStatus::Todo->value);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->assertSeeInOrder(['Second', 'First']);
});

test('kanban groups tasks under their feature, ordered like the board', function () {
    $epic = Epic::factory()->create();
    $featureB = Feature::factory()->for($epic)->create(['name' => 'Feature B', 'order_index' => 1]);
    $featureA = Feature::factory()->for($epic)->create(['name' => 'Feature A', 'order_index' => 0]);
    Task::factory()->for($featureA)->create(['title' => 'A2', 'status' => TaskStatus::Todo, 'order_index' => 1]);
    Task::factory()->for($featureA)->create(['title' => 'A1', 'status' => TaskStatus::Todo, 'order_index' => 0]);
    Task::factory()->for($featureB)->create(['title' => 'B1', 'status' => TaskStatus::Todo, 'order_index' => 0]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('viewMode', 'kanban')
        ->assertSeeInOrder(['Feature A', 'A1', 'A2', 'Feature B', 'B1']);
});

test('kanban only shows a feature group in columns matching its tasks statuses', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create(['name' => 'Mixed Feature']);
    Task::factory()->for($feature)->create(['title' => 'Todo Task', 'status' => TaskStatus::Todo]);
    Task::factory()->for($feature)->create(['title' => 'Done Task', 'status' => TaskStatus::Done]);

    $component = Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('viewMode', 'kanban');

    $columns = collect($component->get('kanbanColumns'));
    $todoColumn = $columns->firstWhere('status', TaskStatus::Todo);
    $doneColumn = $columns->firstWhere('status', TaskStatus::Done);

    expect($todoColumn['groups'])->toHaveCount(1);
    expect($todoColumn['groups'][0]['tasks'])->toHaveCount(1);
    expect($doneColumn['groups'])->toHaveCount(1);
    expect($doneColumn['groups'][0]['tasks'])->toHaveCount(1);
});

test('a feature with no tasks in a status does not appear in that column', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    Task::factory()->for($feature)->create(['status' => TaskStatus::Todo]);

    $component = Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('viewMode', 'kanban');

    $columns = collect($component->get('kanbanColumns'));
    $doneColumn = $columns->firstWhere('status', TaskStatus::Done);

    expect($doneColumn['groups'])->toHaveCount(0);
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

// ── Board sorting ─────────────────────────────────────────────────────────────

test('sortBoard reorders tasks within a feature', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $taskA = Task::factory()->for($feature)->create(['order_index' => 0]);
    $taskB = Task::factory()->for($feature)->create(['order_index' => 1]);
    $taskC = Task::factory()->for($feature)->create(['order_index' => 2]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('sortBoard', $taskC->id, 0);

    expect($taskC->fresh()->order_index)->toBe(0);
    expect($taskA->fresh()->order_index)->toBe(1);
    expect($taskB->fresh()->order_index)->toBe(2);
});

test('sortBoardFeature reorders features', function () {
    $epic = Epic::factory()->create();
    $featureA = Feature::factory()->for($epic)->create(['order_index' => 0]);
    $featureB = Feature::factory()->for($epic)->create(['order_index' => 1]);
    $featureC = Feature::factory()->for($epic)->create(['order_index' => 2]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('sortBoardFeature', $featureC->id, 0);

    expect($featureC->fresh()->order_index)->toBe(0);
    expect($featureA->fresh()->order_index)->toBe(1);
    expect($featureB->fresh()->order_index)->toBe(2);
});

test('moveFeatureToTop moves a feature to position 0', function () {
    $epic = Epic::factory()->create();
    $featureA = Feature::factory()->for($epic)->create(['order_index' => 0]);
    $featureB = Feature::factory()->for($epic)->create(['order_index' => 1]);
    $featureC = Feature::factory()->for($epic)->create(['order_index' => 2]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('moveFeatureToTop', $featureC->id);

    expect($featureC->fresh()->order_index)->toBe(0);
    expect($featureA->fresh()->order_index)->toBe(1);
    expect($featureB->fresh()->order_index)->toBe(2);
});

test('moveFeatureToBottom moves a feature to the last position', function () {
    $epic = Epic::factory()->create();
    $featureA = Feature::factory()->for($epic)->create(['order_index' => 0]);
    $featureB = Feature::factory()->for($epic)->create(['order_index' => 1]);
    $featureC = Feature::factory()->for($epic)->create(['order_index' => 2]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('moveFeatureToBottom', $featureA->id);

    expect($featureB->fresh()->order_index)->toBe(0);
    expect($featureC->fresh()->order_index)->toBe(1);
    expect($featureA->fresh()->order_index)->toBe(2);
});

test('sortBoard does not affect tasks in other features', function () {
    $epic = Epic::factory()->create();
    $featureA = Feature::factory()->for($epic)->create();
    $featureB = Feature::factory()->for($epic)->create();
    $taskA = Task::factory()->for($featureA)->create(['order_index' => 0]);
    $taskB = Task::factory()->for($featureB)->create(['order_index' => 0]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('sortBoard', $taskA->id, 0);

    expect($taskB->fresh()->order_index)->toBe(0);
});

// ── Filter preferences ────────────────────────────────────────────────────────

test('filter status preference is saved when filter changes', function () {
    $epic = Epic::factory()->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('filterStatuses', [TaskStatus::Done->value]);

    expect($this->user->fresh()->preferences['filter_statuses']['board'])->toBe([TaskStatus::Done->value]);
});

test('filter preferences are loaded from user on mount', function () {
    $this->user->update(['preferences' => ['filter_statuses' => ['board' => [TaskStatus::Done->value]]]]);
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    Task::factory()->for($feature)->create(['title' => 'Todo Task', 'status' => TaskStatus::Todo]);
    Task::factory()->for($feature)->create(['title' => 'Done Task', 'status' => TaskStatus::Done]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->assertSee('Done Task')
        ->assertDontSee('Todo Task');
});

test('clearing filter saves empty preference', function () {
    $this->user->update(['preferences' => ['filter_statuses' => ['board' => [TaskStatus::Done->value]]]]);
    $epic = Epic::factory()->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('filterStatuses', []);

    expect($this->user->fresh()->preferences['filter_statuses']['board'])->toBe([]);
});

test('board and kanban views have independent filter preferences', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    Task::factory()->for($feature)->create(['title' => 'Todo Task', 'status' => TaskStatus::Todo]);
    Task::factory()->for($feature)->create(['title' => 'Done Task', 'status' => TaskStatus::Done]);

    $this->user->update(['preferences' => [
        'filter_statuses' => ['board' => [TaskStatus::Done->value]],
    ]]);

    $this->get(route('epics.board', $epic))
        ->assertSee('Done Task')
        ->assertDontSee('Todo Task');

    $this->get(route('epics.board.kanban', $epic))
        ->assertSee('Todo Task')
        ->assertSee('Done Task');
});

test('setting a filter on kanban does not affect the board filter preference', function () {
    $epic = Epic::factory()->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('viewMode', 'kanban')
        ->set('filterStatuses', [TaskStatus::Done->value]);

    expect($this->user->fresh()->preferences['filter_statuses'])
        ->toBe(['kanban' => [TaskStatus::Done->value]]);
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
        'new_values' => ['status' => TaskStatus::InProgress->value],
        'created_at' => now(),
    ]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->assertSee('AI Touched Task')
        ->assertSee('AI');
});

// ── Task modal: always-edit, save/cancel/delete navigation ────────────────────

test('openTask immediately enters edit mode', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create(['title' => 'My Task']);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $task->id)
        ->assertSet('editingTask', true)
        ->assertSet('editTaskTitle', 'My Task');
});

test('saveTask redirects back to board with task highlighted', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $task->id)
        ->call('saveTask')
        ->assertHasNoErrors()
        ->assertRedirect(route('epics.board', $epic));
});

test('closeTask redirects back to board with task highlighted', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $task->id)
        ->call('closeTask')
        ->assertRedirect(route('epics.board', $epic));
});

test('deleteTask selects next sibling task after deletion', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $taskA = Task::factory()->for($feature)->create(['order_index' => 0]);
    $taskB = Task::factory()->for($feature)->create(['order_index' => 1]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $taskA->id)
        ->call('confirmDeleteTask', $taskA->id)
        ->call('deleteTask');

    expect(session('highlighted_id'))->toBe($taskB->id);
});

test('deleteTask selects prev sibling when no next exists', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $taskA = Task::factory()->for($feature)->create(['order_index' => 0]);
    $taskB = Task::factory()->for($feature)->create(['order_index' => 1]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $taskB->id)
        ->call('confirmDeleteTask', $taskB->id)
        ->call('deleteTask');

    expect(session('highlighted_id'))->toBe($taskA->id);
});

test('deleteTask selects feature when no sibling tasks remain', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create(['order_index' => 0]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $task->id)
        ->call('confirmDeleteTask', $task->id)
        ->call('deleteTask');

    expect(session('highlighted_id'))->toBe($feature->id);
});

test('createFeature selects the newly created feature', function () {
    $epic = Epic::factory()->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('newFeatureName', 'Fresh Feature')
        ->call('createFeature')
        ->assertRedirect(route('epics.board', $epic));

    $feature = Feature::where('name', 'Fresh Feature')->firstOrFail();
    expect(session('highlighted_id'))->toBe($feature->id);
});

test('createTask selects the newly created task', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openAddTask', $feature->id)
        ->set('newTaskTitle', 'Fresh Task')
        ->call('createTask')
        ->assertRedirect(route('epics.board', $epic));

    $task = Task::where('title', 'Fresh Task')->firstOrFail();
    expect(session('highlighted_id'))->toBe($task->id);
});

// ── Feature collapse persistence ──────────────────────────────────────────────

test('saveFeatureCollapse stores collapsed state in user preferences', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('saveFeatureCollapse', $feature->id, true);

    expect($this->user->fresh()->preferences['collapsed_feature_ids'][$epic->id])
        ->toContain($feature->id);
});

test('saveFeatureCollapse removes feature id when expanded', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $this->user->update(['preferences' => ['collapsed_feature_ids' => [$epic->id => [$feature->id]]]]);

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('saveFeatureCollapse', $feature->id, false);

    expect($this->user->fresh()->preferences['collapsed_feature_ids'][$epic->id])
        ->not->toContain($feature->id);
});

test('board feature cards have wire sort item attribute for drag and drop', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->assertSeeHtml('wire:sort:item="'.$feature->id.'"');
});

// ── Board navigation: empty-state and collapsed summary selectability ──────────

test('board renders empty-state section as selectable for features with no tasks', function () {
    $epic = Epic::factory()->create();
    Feature::factory()->for($epic)->create();

    $html = Livewire::test('pages::epics.show', ['epic' => $epic])->html();

    expect($html)->toContain('No tasks yet.')
        ->and($html)->toContain('data-selectable');
});

test('board empty-state add task button has data-open-btn for keyboard activation', function () {
    $epic = Epic::factory()->create();
    Feature::factory()->for($epic)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->assertSeeHtml('data-open-btn');
});

test('board collapsed summary row does not have data-selectable', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    Task::factory()->for($feature)->create();

    $html = Livewire::test('pages::epics.show', ['epic' => $epic])->html();

    // The collapsed summary row (x-show="collapsed") must not be selectable
    expect($html)->not->toContain('x-show="collapsed" '.PHP_EOL.'                            x-cloak'.PHP_EOL.'                            data-selectable');
});

test('board empty-state section opens add task modal on call', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openAddTask', $feature->id)
        ->assertSet('addingTaskForFeatureId', $feature->id);
});

// ── Conversation notes ─────────────────────────────────────────────────────────

test('can add a note to a task', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $task->id)
        ->set('taskReplyBody', 'A note about this task')
        ->call('addTaskReply')
        ->assertHasNoErrors()
        ->assertSet('taskReplyBody', '');

    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'action' => HistoryAction::Note->value,
        'body' => 'A note about this task',
        'changed_by_user_id' => $this->user->id,
    ]);
});

test('adding a task note with sendToClaude flags it for Claude', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $task->id)
        ->set('taskReplyBody', 'Please look into this')
        ->call('addTaskReply', true)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('task_histories', [
        'task_id' => $task->id,
        'body' => 'Please look into this',
    ]);

    expect(TaskHistory::where('task_id', $task->id)->latest()->first()->metadata)
        ->toBe(['claude_request' => true]);
});

test('an empty task note is rejected', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $task->id)
        ->set('taskReplyBody', '')
        ->call('addTaskReply')
        ->assertHasErrors('taskReplyBody');

    expect(TaskHistory::where('action', HistoryAction::Note->value)->exists())->toBeFalse();
});

test('can add a note to a feature', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openEditFeature', $feature->id)
        ->set('featureReplyBody', 'A note about this feature')
        ->call('addFeatureReply')
        ->assertHasNoErrors()
        ->assertSet('featureReplyBody', '');

    $this->assertDatabaseHas('feature_histories', [
        'feature_id' => $feature->id,
        'action' => HistoryAction::Note->value,
        'body' => 'A note about this feature',
        'changed_by_user_id' => $this->user->id,
    ]);
});

test('can add a note to an epic', function () {
    $epic = Epic::factory()->create();

    Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('epicReplyBody', 'A note about this epic')
        ->call('addEpicReply')
        ->assertHasNoErrors()
        ->assertSet('epicReplyBody', '');

    $this->assertDatabaseHas('epic_histories', [
        'epic_id' => $epic->id,
        'action' => HistoryAction::Note->value,
        'body' => 'A note about this epic',
        'changed_by_user_id' => $this->user->id,
    ]);
});

test('the task, feature, and epic Send buttons reactively enable via Alpine instead of a stale server-rendered disabled attribute', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $task = Task::factory()->for($feature)->create();

    $html = Livewire::test('pages::epics.show', ['epic' => $epic])
        ->call('openTask', $task->id)
        ->call('openEditFeature', $feature->id)
        ->html();

    // wire:model (deferred) never syncs taskReplyBody/featureReplyBody/epicReplyBody to the
    // server as the user types, so a PHP-computed `:disabled="! trim($x)"` bakes a permanently
    // disabled button into the initial render — clicking Send then does nothing, with no error
    // and no network request. Disabling must be reactive on the client via Alpine's $wire instead.
    expect($html)->not->toContain(':disabled="! trim($taskReplyBody)"')
        ->not->toContain(':disabled="! trim($featureReplyBody)"')
        ->not->toContain(':disabled="! trim($epicReplyBody)"')
        ->toContain('x-bind:disabled="! $wire.taskReplyBody.trim()"')
        ->toContain('x-bind:disabled="! $wire.featureReplyBody.trim()"')
        ->toContain('x-bind:disabled="! $wire.epicReplyBody.trim()"');
});

// ── Contextual keyboard shortcuts (+ / Shift+ + / Enter / Delete) ──────────────

test('board and kanban wire up delete-feature and delete-task shortcut events', function () {
    $epic = Epic::factory()->create();
    Feature::factory()->for($epic)->create();

    $board = Livewire::test('pages::epics.show', ['epic' => $epic])->html();
    expect($board)
        ->toContain('x-on:board-delete-feature.window="$wire.confirmDeleteFeature($event.detail.featureId)"')
        ->toContain('x-on:board-delete-task.window="$wire.confirmDeleteTask($event.detail.taskId)"');

    $kanban = Livewire::test('pages::epics.show', ['epic' => $epic])
        ->set('viewMode', 'kanban')
        ->html();
    expect($kanban)
        ->toContain('x-on:board-delete-feature.window="$wire.confirmDeleteFeature($event.detail.featureId)"')
        ->toContain('x-on:board-delete-task.window="$wire.confirmDeleteTask($event.detail.taskId)"');
});

test('board feature add-task button is markable for the contextual + shortcut', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();

    $html = Livewire::test('pages::epics.show', ['epic' => $epic])->html();

    expect($html)->toContain('data-add-task-btn="data-add-task-btn" wire:click="openAddTask(\''.$feature->id.'\')"');
});
