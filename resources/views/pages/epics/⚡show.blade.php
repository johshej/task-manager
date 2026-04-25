<?php

use App\Enums\ActorType;
use App\Enums\EpicStatus;
use App\Enums\FeatureStatus;
use App\Enums\TaskStatus;
use App\Models\Epic;
use App\Models\Feature;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Epic Board')] class extends Component {
    public Epic $epic;

    public string $viewMode = 'board';
    public bool $showFilters = false;
    public array $filterFeatureIds = [];
    public array $filterStatuses = [];

    // Feature creation
    public string $newFeatureName = '';
    public string $newFeatureTdd = '';
    public string $newFeatureAiMode = '';
    public string $newFeatureEnvironment = '';

    // Feature editing
    public ?string $editingFeatureId = null;
    public string $editFeatureName = '';
    public string $editFeatureStatus = '';
    public string $editFeatureTdd = '';
    public string $editFeatureAiMode = '';
    public string $editFeatureEnvironment = '';

    // Task creation
    public ?string $addingTaskForFeatureId = null;
    public string $newTaskTitle = '';
    public string $newTaskDescription = '';
    public int $newTaskPriority = 5;
    public string $newTaskTdd = '';
    public string $newTaskAiMode = '';
    public string $newTaskEnvironment = '';

    // Task detail / editing
    public ?string $selectedTaskId = null;
    public bool $editingTask = false;
    public string $editTaskTitle = '';
    public string $editTaskDescription = '';
    public string $editTaskStatus = '';
    public int $editTaskPriority = 5;
    public string $editTaskTdd = '';
    public string $editTaskAiMode = '';
    public string $editTaskEnvironment = '';

    // Epic editing
    public string $editEpicName = '';
    public string $editEpicDescription = '';
    public string $editEpicRepositoryUrl = '';
    public string $editEpicStatus = '';
    public string $editEpicTdd = '';
    public string $editEpicAiMode = '';
    public string $editEpicEnvironment = '';

    public function mount(Epic $epic): void
    {
        $this->epic = $epic;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function tddNullable(string $value): ?bool
    {
        return $value === '' ? null : (bool) $value;
    }

    private function boolToTddString(?bool $value): string
    {
        return $value === null ? '' : ($value ? '1' : '0');
    }

    // ── Epic ──────────────────────────────────────────────────────────────────

    public function openEditEpic(): void
    {
        $this->editEpicName = $this->epic->name;
        $this->editEpicDescription = $this->epic->description ?? '';
        $this->editEpicRepositoryUrl = $this->epic->repository_url ?? '';
        $this->editEpicStatus = $this->epic->status->value;
        $this->editEpicTdd = $this->boolToTddString($this->epic->tdd);
        $this->editEpicAiMode = $this->epic->ai_mode ?? '';
        $this->editEpicEnvironment = $this->epic->environment ?? '';
        $this->modal('edit-epic')->show();
    }

    public function updateEpic(): void
    {
        $this->validate([
            'editEpicName' => ['required', 'string', 'max:255'],
            'editEpicDescription' => ['nullable', 'string'],
            'editEpicRepositoryUrl' => ['nullable', 'regex:/^(https?:\/\/\S+|git@[^:]+:\S+)$/', 'max:500'],
            'editEpicStatus' => ['required', 'in:' . implode(',', array_column(EpicStatus::cases(), 'value'))],
            'editEpicTdd' => ['nullable', 'in:0,1'],
            'editEpicAiMode' => ['nullable', 'string'],
            'editEpicEnvironment' => ['nullable', 'string', 'max:100'],
        ]);

        $this->epic->update([
            'name' => $this->editEpicName,
            'description' => $this->editEpicDescription ?: null,
            'repository_url' => $this->editEpicRepositoryUrl ?: null,
            'status' => $this->editEpicStatus,
            'tdd' => $this->tddNullable($this->editEpicTdd),
            'ai_mode' => $this->editEpicAiMode ?: null,
            'environment' => $this->editEpicEnvironment ?: null,
        ]);

        $this->epic->refresh();
        $this->modal('edit-epic')->close();
        Flux::toast(variant: 'success', text: 'Epic updated.');
    }

    // ── Features ──────────────────────────────────────────────────────────────

    public function openAddFeature(): void
    {
        $this->reset('newFeatureName', 'newFeatureTdd', 'newFeatureAiMode', 'newFeatureEnvironment');
        $this->modal('create-feature')->show();
    }

    public function createFeature(): void
    {
        $this->validate([
            'newFeatureName' => ['required', 'string', 'max:255'],
            'newFeatureTdd' => ['nullable', 'in:0,1'],
            'newFeatureAiMode' => ['nullable', 'string'],
            'newFeatureEnvironment' => ['nullable', 'string', 'max:100'],
        ]);

        $this->epic->features()->create([
            'name' => $this->newFeatureName,
            'status' => FeatureStatus::Planned,
            'order_index' => $this->epic->features()->count(),
            'tdd' => $this->tddNullable($this->newFeatureTdd),
            'ai_mode' => $this->newFeatureAiMode ?: null,
            'environment' => $this->newFeatureEnvironment ?: null,
        ]);

        $this->reset('newFeatureName', 'newFeatureTdd', 'newFeatureAiMode', 'newFeatureEnvironment');
        $this->modal('create-feature')->close();
        unset($this->features, $this->allFeatures);
        Flux::toast(variant: 'success', text: 'Feature created.');
    }

    public function openEditFeature(string $featureId): void
    {
        $feature = Feature::findOrFail($featureId);
        $this->editingFeatureId = $featureId;
        $this->editFeatureName = $feature->name;
        $this->editFeatureStatus = $feature->status->value;
        $this->editFeatureTdd = $this->boolToTddString($feature->tdd);
        $this->editFeatureAiMode = $feature->ai_mode ?? '';
        $this->editFeatureEnvironment = $feature->environment ?? '';
        $this->modal('edit-feature')->show();
    }

    public function updateFeature(): void
    {
        $this->validate([
            'editFeatureName' => ['required', 'string', 'max:255'],
            'editFeatureStatus' => ['required', 'in:' . implode(',', array_column(FeatureStatus::cases(), 'value'))],
            'editFeatureTdd' => ['nullable', 'in:0,1'],
            'editFeatureAiMode' => ['nullable', 'string'],
            'editFeatureEnvironment' => ['nullable', 'string', 'max:100'],
        ]);

        Feature::findOrFail($this->editingFeatureId)->update([
            'name' => $this->editFeatureName,
            'status' => $this->editFeatureStatus,
            'tdd' => $this->tddNullable($this->editFeatureTdd),
            'ai_mode' => $this->editFeatureAiMode ?: null,
            'environment' => $this->editFeatureEnvironment ?: null,
        ]);

        $this->modal('edit-feature')->close();
        unset($this->features);
        Flux::toast(variant: 'success', text: 'Feature updated.');
    }

    // ── Tasks ─────────────────────────────────────────────────────────────────

    public function openAddTask(string $featureId): void
    {
        $this->addingTaskForFeatureId = $featureId;
        $this->reset('newTaskTitle', 'newTaskDescription', 'newTaskTdd', 'newTaskAiMode', 'newTaskEnvironment');
        $this->newTaskPriority = 5;
        unset($this->addingTaskForFeature);
        $this->modal('create-task')->show();
    }

    public function createTask(): void
    {
        $this->validate([
            'newTaskTitle' => ['required', 'string', 'max:255'],
            'newTaskDescription' => ['nullable', 'string'],
            'newTaskPriority' => ['required', 'integer', 'min:0', 'max:10'],
            'newTaskTdd' => ['nullable', 'in:0,1'],
            'newTaskAiMode' => ['nullable', 'string'],
            'newTaskEnvironment' => ['nullable', 'string', 'max:100'],
        ]);

        $feature = Feature::findOrFail($this->addingTaskForFeatureId);

        $maxOrder = Task::whereHas('feature', fn ($q) => $q->where('epic_id', $this->epic->id))
            ->max('execution_order') ?? -1;

        $feature->tasks()->create([
            'title' => $this->newTaskTitle,
            'description' => $this->newTaskDescription ?: null,
            'status' => TaskStatus::Todo,
            'priority' => $this->newTaskPriority,
            'order_index' => $feature->tasks()->count(),
            'execution_order' => $maxOrder + 1,
            'tdd' => $this->tddNullable($this->newTaskTdd),
            'ai_mode' => $this->newTaskAiMode ?: null,
            'environment' => $this->newTaskEnvironment ?: null,
        ]);

        $this->reset('newTaskTitle', 'newTaskDescription', 'addingTaskForFeatureId', 'newTaskTdd', 'newTaskAiMode', 'newTaskEnvironment');
        $this->newTaskPriority = 5;
        $this->modal('create-task')->close();
        unset($this->features, $this->kanbanColumns, $this->sortedQueue);
        Flux::toast(variant: 'success', text: 'Task created.');
    }

    public function openTask(string $taskId): void
    {
        $task = Task::findOrFail($taskId);
        $this->selectedTaskId = $taskId;
        $this->editingTask = false;
        $this->editTaskTitle = $task->title;
        $this->editTaskDescription = $task->description ?? '';
        $this->editTaskStatus = $task->status->value;
        $this->editTaskPriority = $task->priority;
        $this->editTaskTdd = $this->boolToTddString($task->tdd);
        $this->editTaskAiMode = $task->ai_mode ?? '';
        $this->editTaskEnvironment = $task->environment ?? '';
        unset($this->selectedTask);
        $this->modal('task-detail')->show();
    }

    public function startEditingTask(): void
    {
        $this->editingTask = true;
    }

    public function cancelEditingTask(): void
    {
        $this->editingTask = false;
    }

    public function saveTask(): void
    {
        $this->validate([
            'editTaskTitle' => ['required', 'string', 'max:255'],
            'editTaskDescription' => ['nullable', 'string'],
            'editTaskStatus' => ['required', 'in:' . implode(',', array_column(TaskStatus::cases(), 'value'))],
            'editTaskPriority' => ['required', 'integer', 'min:0', 'max:10'],
            'editTaskTdd' => ['nullable', 'in:0,1'],
            'editTaskAiMode' => ['nullable', 'string'],
            'editTaskEnvironment' => ['nullable', 'string', 'max:100'],
        ]);

        Task::findOrFail($this->selectedTaskId)->update([
            'title' => $this->editTaskTitle,
            'description' => $this->editTaskDescription ?: null,
            'status' => $this->editTaskStatus,
            'priority' => $this->editTaskPriority,
            'tdd' => $this->tddNullable($this->editTaskTdd),
            'ai_mode' => $this->editTaskAiMode ?: null,
            'environment' => $this->editTaskEnvironment ?: null,
        ]);

        $this->editingTask = false;
        unset($this->selectedTask, $this->features, $this->kanbanColumns, $this->sortedQueue);
        Flux::toast(variant: 'success', text: 'Task saved.');
    }

    // ── Sort handlers ─────────────────────────────────────────────────────────

    public function sortBoard(string $taskId, int $position): void
    {
        $task = Task::findOrFail($taskId);

        $ids = Task::where('feature_id', $task->feature_id)
            ->where('id', '!=', $taskId)
            ->orderBy('order_index')
            ->pluck('id')
            ->toArray();

        array_splice($ids, $position, 0, [$taskId]);

        foreach ($ids as $idx => $id) {
            Task::where('id', $id)->update(['order_index' => $idx]);
        }

        unset($this->features);
    }

    public function sortKanban(string $taskId, int $position, string $statusValue): void
    {
        $task = Task::findOrFail($taskId);
        $newStatus = TaskStatus::from($statusValue);

        if ($task->status !== $newStatus) {
            $task->update(['status' => $newStatus]);
        }

        $ids = Task::whereHas('feature', fn ($q) => $q->where('epic_id', $this->epic->id))
            ->where('status', $statusValue)
            ->where('id', '!=', $taskId)
            ->orderBy('order_index')
            ->pluck('id')
            ->toArray();

        array_splice($ids, $position, 0, [$taskId]);

        foreach ($ids as $idx => $id) {
            Task::where('id', $id)->update(['order_index' => $idx]);
        }

        unset($this->kanbanColumns, $this->features);
    }

    public function sortQueue(string $taskId, int $position): void
    {
        $ids = Task::whereHas('feature', fn ($q) => $q->where('epic_id', $this->epic->id))
            ->orderByRaw('COALESCE(execution_order, 999999)')
            ->orderBy('created_at')
            ->pluck('id')
            ->toArray();

        $ids = array_values(array_filter($ids, fn ($id) => $id !== $taskId));
        array_splice($ids, $position, 0, [$taskId]);

        foreach ($ids as $idx => $id) {
            Task::where('id', $id)->update(['execution_order' => $idx]);
        }

        unset($this->sortedQueue);
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    /** @return Collection<int, Feature> */
    #[Computed]
    public function features(): Collection
    {
        return $this->epic->features()
            ->when(count($this->filterFeatureIds), fn ($q) => $q->whereIn('id', $this->filterFeatureIds))
            ->with(['tasks' => fn ($q) => $q
                ->when(count($this->filterStatuses), fn ($q) => $q->whereIn('status', $this->filterStatuses))
                ->orderBy('order_index')
                ->with('latestHistory')
            ])
            ->orderBy('order_index')
            ->get();
    }

    /** @return Collection<int, Feature> */
    #[Computed]
    public function allFeatures(): Collection
    {
        return $this->epic->features()->orderBy('order_index')->get(['id', 'name']);
    }

    /** @return array<int, array{status: TaskStatus, tasks: Collection<int, Task>}> */
    #[Computed]
    public function kanbanColumns(): array
    {
        $statuses = count($this->filterStatuses)
            ? array_values(array_filter(TaskStatus::cases(), fn ($s) => in_array($s->value, $this->filterStatuses)))
            : TaskStatus::cases();

        $tasks = Task::with(['feature', 'latestHistory'])
            ->whereHas('feature', fn ($q) => $q->where('epic_id', $this->epic->id))
            ->when(count($this->filterFeatureIds), fn ($q) => $q->whereIn('feature_id', $this->filterFeatureIds))
            ->orderBy('order_index')
            ->get();

        $columns = [];
        foreach ($statuses as $status) {
            $columns[] = [
                'status' => $status,
                'tasks' => $tasks->filter(fn ($t) => $t->status === $status)->values(),
            ];
        }

        return $columns;
    }

    /** @return Collection<int, Task> */
    #[Computed]
    public function sortedQueue(): Collection
    {
        return Task::with(['feature', 'latestHistory'])
            ->whereHas('feature', fn ($q) => $q->where('epic_id', $this->epic->id))
            ->when(count($this->filterFeatureIds), fn ($q) => $q->whereIn('feature_id', $this->filterFeatureIds))
            ->when(count($this->filterStatuses), fn ($q) => $q->whereIn('status', $this->filterStatuses))
            ->orderByRaw('COALESCE(execution_order, 999999)')
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function selectedTask(): ?Task
    {
        if (! $this->selectedTaskId) {
            return null;
        }

        return Task::with([
            'history' => fn ($q) => $q->with('changedByUser', 'changedByToken')->oldest('created_at'),
            'assignee',
            'feature.epic',
        ])->find($this->selectedTaskId);
    }

    #[Computed]
    public function addingTaskForFeature(): ?Feature
    {
        if (! $this->addingTaskForFeatureId) {
            return null;
        }

        return Feature::with('epic')->find($this->addingTaskForFeatureId);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">

    {{-- Page header --}}
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <flux:button
                variant="ghost"
                size="sm"
                icon="arrow-left"
                :href="route('epics')"
                wire:navigate
                class="mt-0.5"
            />
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="xl">{{ $epic->name }}</flux:heading>
                    <flux:badge color="{{ $epic->status->color() }}">{{ $epic->status->label() }}</flux:badge>
                    @if ($epic->tdd !== null)
                        <flux:badge color="{{ $epic->tdd ? 'green' : 'zinc' }}" size="sm">TDD: {{ $epic->tdd ? 'On' : 'Off' }}</flux:badge>
                    @endif
                    @if ($epic->environment)
                        <flux:badge color="sky" size="sm">{{ $epic->environment }}</flux:badge>
                    @endif
                </div>
                @if ($epic->description)
                    <flux:text class="mt-1 max-w-2xl text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $epic->description }}
                    </flux:text>
                @endif
                @if ($epic->repository_url)
                    <a
                        href="{{ $epic->repository_url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-1 inline-flex items-center gap-1 text-xs text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400"
                    >
                        <flux:icon.folder-git-2 class="size-3.5" />
                        {{ $epic->repository_url }}
                    </a>
                @endif
                @if ($epic->ai_mode)
                    <flux:text class="mt-1 text-xs text-zinc-400">AI: {{ $epic->ai_mode }}</flux:text>
                @endif
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditEpic">
                {{ __('Edit epic') }}
            </flux:button>
            <flux:button variant="primary" size="sm" icon="plus" wire:click="openAddFeature">
                {{ __('Add feature') }}
            </flux:button>
        </div>
    </div>

    {{-- View controls --}}
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-1 rounded-lg border border-zinc-200 p-1 dark:border-zinc-700">
            <flux:button
                variant="{{ $viewMode === 'board' ? 'filled' : 'ghost' }}"
                size="sm"
                wire:click="$set('viewMode', 'board')"
            >{{ __('Board') }}</flux:button>
            <flux:button
                variant="{{ $viewMode === 'kanban' ? 'filled' : 'ghost' }}"
                size="sm"
                wire:click="$set('viewMode', 'kanban')"
            >{{ __('Kanban') }}</flux:button>
            <flux:button
                variant="{{ $viewMode === 'sort' ? 'filled' : 'ghost' }}"
                size="sm"
                wire:click="$set('viewMode', 'sort')"
            >{{ __('AI Queue') }}</flux:button>
        </div>
        <flux:button
            variant="{{ $showFilters ? 'filled' : 'ghost' }}"
            size="sm"
            icon="funnel"
            wire:click="$toggle('showFilters')"
        >
            {{ __('Filter') }}
            @if (count($filterFeatureIds) + count($filterStatuses) > 0)
                <flux:badge color="blue" size="sm" class="ml-1">{{ count($filterFeatureIds) + count($filterStatuses) }}</flux:badge>
            @endif
        </flux:button>
    </div>

    {{-- Filter panel --}}
    @if ($showFilters)
        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:heading size="sm" class="mb-2 font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Features') }}</flux:heading>
                    <div class="space-y-1.5">
                        @foreach ($this->allFeatures as $f)
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    wire:model.live="filterFeatureIds"
                                    value="{{ $f->id }}"
                                    class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600"
                                >
                                {{ $f->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <flux:heading size="sm" class="mb-2 font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</flux:heading>
                    <div class="space-y-1.5">
                        @foreach (TaskStatus::cases() as $s)
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    wire:model.live="filterStatuses"
                                    value="{{ $s->value }}"
                                    class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600"
                                >
                                <flux:badge color="{{ $s->color() }}" size="sm">{{ $s->label() }}</flux:badge>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            @if (count($filterFeatureIds) + count($filterStatuses) > 0)
                <div class="mt-3 flex justify-end">
                    <flux:button variant="ghost" size="sm" wire:click="$set('filterFeatureIds', []); $set('filterStatuses', [])">
                        {{ __('Clear filters') }}
                    </flux:button>
                </div>
            @endif
        </div>
    @endif

    {{-- ── Board view ──────────────────────────────────────────────────────── --}}
    @if ($viewMode === 'board')
        <div class="space-y-6">
            @forelse ($this->features as $feature)
                <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    {{-- Feature header --}}
                    <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $feature->name }}</span>
                            <flux:badge color="{{ $feature->status->color() }}" size="sm">{{ $feature->status->label() }}</flux:badge>
                            <flux:text class="text-xs text-zinc-400">
                                {{ $feature->tasks->count() }} {{ Str::plural('task', $feature->tasks->count()) }}
                            </flux:text>
                            @if ($feature->tdd !== null)
                                <flux:badge color="{{ $feature->tdd ? 'green' : 'zinc' }}" size="sm">TDD: {{ $feature->tdd ? 'On' : 'Off' }}</flux:badge>
                            @endif
                            @if ($feature->environment)
                                <flux:badge color="sky" size="sm">{{ $feature->environment }}</flux:badge>
                            @endif
                        </div>
                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Add task')">
                                <flux:button variant="ghost" size="sm" icon="plus" wire:click="openAddTask('{{ $feature->id }}')" />
                            </flux:tooltip>
                            <flux:tooltip :content="__('Edit feature')">
                                <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditFeature('{{ $feature->id }}')" />
                            </flux:tooltip>
                        </div>
                    </div>

                    {{-- Tasks --}}
                    @if ($feature->tasks->isNotEmpty())
                        <ul wire:sort="sortBoard" class="divide-y divide-zinc-100 list-none dark:divide-zinc-800">
                            @foreach ($feature->tasks as $task)
                                <li wire:key="board-{{ $task->id }}" wire:sort:item="{{ $task->id }}" class="flex items-center">
                                    <div wire:sort:handle class="cursor-grab px-3 text-zinc-300 hover:text-zinc-500 dark:hover:text-zinc-400">
                                        <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
                                            <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                                            <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                                            <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                                        </svg>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="openTask('{{ $task->id }}')"
                                        class="flex flex-1 items-center gap-4 py-3 pr-5 text-left transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                    >
                                        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $task->title }}</span>
                                        <flux:badge color="{{ $task->status->color() }}" size="sm">{{ $task->status->label() }}</flux:badge>
                                        <flux:badge color="zinc" size="sm" class="tabular-nums">P{{ $task->priority }}</flux:badge>
                                        @if ($task->resolvedEnvironment())
                                            <flux:badge color="sky" size="sm">{{ $task->resolvedEnvironment() }}</flux:badge>
                                        @endif
                                        @if ($task->latestHistory)
                                            @if ($task->latestHistory->actor_type === ActorType::Ai)
                                                <flux:badge color="purple" size="sm" icon="cpu-chip">AI</flux:badge>
                                            @else
                                                <flux:badge color="zinc" size="sm" icon="user">User</flux:badge>
                                            @endif
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="px-5 py-6 text-center">
                            <flux:text class="text-sm text-zinc-400">{{ __('No tasks yet.') }}</flux:text>
                            <div class="mt-2">
                                <flux:button variant="ghost" size="sm" icon="plus" wire:click="openAddTask('{{ $feature->id }}')">
                                    {{ __('Add task') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 bg-zinc-50 py-20 dark:border-zinc-700 dark:bg-zinc-900/50">
                    <flux:text class="text-base font-medium text-zinc-500 dark:text-zinc-400">{{ __('No features yet') }}</flux:text>
                    <flux:text class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">
                        {{ __('Add your first feature to start breaking down this epic.') }}
                    </flux:text>
                    <div class="mt-5">
                        <flux:button variant="primary" icon="plus" size="sm" wire:click="openAddFeature">
                            {{ __('Add feature') }}
                        </flux:button>
                    </div>
                </div>
            @endforelse
        </div>

    {{-- ── Kanban view ──────────────────────────────────────────────────────── --}}
    @elseif ($viewMode === 'kanban')
        <div class="overflow-x-auto pb-4">
            <div class="flex gap-4" style="min-width: max-content">
                @foreach ($this->kanbanColumns as $column)
                    <div class="flex w-64 flex-col gap-2">
                        <div class="flex items-center gap-2 px-1">
                            <flux:badge color="{{ $column['status']->color() }}" size="sm">{{ $column['status']->label() }}</flux:badge>
                            <span class="text-xs text-zinc-400">{{ $column['tasks']->count() }}</span>
                        </div>
                        <ul
                            wire:sort="sortKanban"
                            wire:sort:group="kanban-tasks"
                            wire:sort:group-id="{{ $column['status']->value }}"
                            class="min-h-16 list-none space-y-2 rounded-xl border border-dashed border-zinc-200 p-2 dark:border-zinc-700"
                        >
                            @foreach ($column['tasks'] as $task)
                                <li
                                    wire:key="kanban-{{ $task->id }}"
                                    wire:sort:item="{{ $task->id }}"
                                    class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
                                >
                                    <div class="flex items-start gap-2 p-3">
                                        <div wire:sort:handle class="mt-0.5 shrink-0 cursor-grab text-zinc-300 hover:text-zinc-500">
                                            <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
                                                <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                                                <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                                                <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <button
                                                type="button"
                                                wire:click="openTask('{{ $task->id }}')"
                                                class="block w-full text-left text-sm font-medium leading-snug hover:underline"
                                                style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"
                                            >{{ $task->title }}</button>
                                            <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                                <flux:badge color="zinc" size="sm" class="tabular-nums">P{{ $task->priority }}</flux:badge>
                                                @if ($task->resolvedEnvironment())
                                                    <flux:badge color="sky" size="sm">{{ $task->resolvedEnvironment() }}</flux:badge>
                                                @endif
                                                @if ($task->feature)
                                                    <span class="text-xs text-zinc-400 truncate">{{ $task->feature->name }}</span>
                                                @endif
                                                @if ($task->latestHistory?->actor_type === ActorType::Ai)
                                                    <flux:badge color="purple" size="sm" icon="cpu-chip">AI</flux:badge>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>

    {{-- ── Sort / AI Queue view ─────────────────────────────────────────────── --}}
    @elseif ($viewMode === 'sort')
        <div>
            <div class="mb-3 flex items-center justify-between">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Drag tasks to set the order in which the AI should execute them.') }}
                </flux:text>
                <flux:text class="text-xs text-zinc-400">{{ $this->sortedQueue->count() }} {{ Str::plural('task', $this->sortedQueue->count()) }}</flux:text>
            </div>

            @if ($this->sortedQueue->isNotEmpty())
                <ul wire:sort="sortQueue" class="list-none space-y-2">
                    @foreach ($this->sortedQueue as $index => $task)
                        <li
                            wire:key="queue-{{ $task->id }}"
                            wire:sort:item="{{ $task->id }}"
                            class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <span class="w-7 shrink-0 text-right text-sm font-mono text-zinc-400">{{ $index + 1 }}</span>
                            <div wire:sort:handle class="shrink-0 cursor-grab text-zinc-300 hover:text-zinc-500">
                                <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
                                    <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                                    <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                                    <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <button
                                    type="button"
                                    wire:click="openTask('{{ $task->id }}')"
                                    class="block truncate text-left text-sm font-medium hover:underline"
                                >{{ $task->title }}</button>
                                @if ($task->feature)
                                    <span class="text-xs text-zinc-400">{{ $task->feature->name }}</span>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <flux:badge color="{{ $task->status->color() }}" size="sm">{{ $task->status->label() }}</flux:badge>
                                <flux:badge color="zinc" size="sm" class="tabular-nums">P{{ $task->priority }}</flux:badge>
                                @if ($task->resolvedTdd() !== null)
                                    <flux:badge color="{{ $task->resolvedTdd() ? 'green' : 'zinc' }}" size="sm">TDD</flux:badge>
                                @endif
                                @if ($task->resolvedEnvironment())
                                    <flux:badge color="sky" size="sm">{{ $task->resolvedEnvironment() }}</flux:badge>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 bg-zinc-50 py-16 dark:border-zinc-700 dark:bg-zinc-900/50">
                    <flux:text class="text-base font-medium text-zinc-500 dark:text-zinc-400">{{ __('No tasks in queue') }}</flux:text>
                    <flux:text class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Create tasks or adjust your filters.') }}</flux:text>
                </div>
            @endif
        </div>
    @endif

    {{-- ── Modals ───────────────────────────────────────────────────────────── --}}

    {{-- Edit Epic Modal --}}
    <flux:modal name="edit-epic" focusable class="md:w-[520px]">
        <form wire:submit="updateEpic" class="space-y-5">
            <flux:heading size="lg">{{ __('Edit epic') }}</flux:heading>

            <flux:input wire:model="editEpicName" :label="__('Name')" autofocus required />
            <flux:textarea wire:model="editEpicDescription" :label="__('Description (optional)')" rows="3" />
            <flux:input wire:model="editEpicRepositoryUrl" :label="__('Repository URL (optional)')" type="text" placeholder="https://github.com/org/repo or git@github.com:org/repo.git" />

            <flux:select wire:model="editEpicStatus" :label="__('Status')">
                @foreach (EpicStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="editEpicTdd" :label="__('TDD')">
                    <flux:select.option value="">{{ __('Inherit') }}</flux:select.option>
                    <flux:select.option value="1">{{ __('Enabled') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Disabled') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model="editEpicEnvironment" :label="__('Environment')">
                    <flux:select.option value="">{{ __('Inherit') }}</flux:select.option>
                    <flux:select.option value="Development">{{ __('Development') }}</flux:select.option>
                    <flux:select.option value="Production">{{ __('Production') }}</flux:select.option>
                    <flux:select.option value="Staging">{{ __('Staging') }}</flux:select.option>
                    <flux:select.option value="Other">{{ __('Other') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:textarea wire:model="editEpicAiMode" :label="__('AI mode (optional)')" rows="2" placeholder="{{ __('Describe how AI should behave for this epic...') }}" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Create Feature Modal --}}
    <flux:modal name="create-feature" focusable class="md:w-[520px]">
        <form wire:submit="createFeature" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('New feature') }}</flux:heading>
                <flux:subheading>{{ __('Features are groups of related tasks within an epic.') }}</flux:subheading>
            </div>

            <flux:input wire:model="newFeatureName" :label="__('Name')" autofocus required />

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="newFeatureTdd" :label="__('TDD')">
                    <flux:select.option value="">
                        {{ __('Inherit') }}@if($epic->tdd !== null) ({{ $epic->tdd ? 'Enabled' : 'Disabled' }})@endif
                    </flux:select.option>
                    <flux:select.option value="1">{{ __('Enabled') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Disabled') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model="newFeatureEnvironment" :label="__('Environment')">
                    <flux:select.option value="">{{ __('Inherit') }}@if($epic->environment) ({{ $epic->environment }})@endif</flux:select.option>
                    <flux:select.option value="Development">{{ __('Development') }}</flux:select.option>
                    <flux:select.option value="Production">{{ __('Production') }}</flux:select.option>
                    <flux:select.option value="Staging">{{ __('Staging') }}</flux:select.option>
                    <flux:select.option value="Other">{{ __('Other') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:textarea wire:model="newFeatureAiMode" :label="__('AI mode (optional)')" rows="2" :placeholder="$epic->ai_mode ? __('Inherits: ').$epic->ai_mode : __('Describe how AI should behave...')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create feature') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Feature Modal --}}
    <flux:modal name="edit-feature" focusable class="md:w-[520px]">
        <form wire:submit="updateFeature" class="space-y-5">
            <flux:heading size="lg">{{ __('Edit feature') }}</flux:heading>

            <flux:input wire:model="editFeatureName" :label="__('Name')" autofocus required />

            <flux:select wire:model="editFeatureStatus" :label="__('Status')">
                @foreach (FeatureStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="editFeatureTdd" :label="__('TDD')">
                    <flux:select.option value="">
                        {{ __('Inherit') }}@if($epic->tdd !== null) ({{ $epic->tdd ? 'Enabled' : 'Disabled' }})@endif
                    </flux:select.option>
                    <flux:select.option value="1">{{ __('Enabled') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Disabled') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model="editFeatureEnvironment" :label="__('Environment')">
                    <flux:select.option value="">{{ __('Inherit') }}@if($epic->environment) ({{ $epic->environment }})@endif</flux:select.option>
                    <flux:select.option value="Development">{{ __('Development') }}</flux:select.option>
                    <flux:select.option value="Production">{{ __('Production') }}</flux:select.option>
                    <flux:select.option value="Staging">{{ __('Staging') }}</flux:select.option>
                    <flux:select.option value="Other">{{ __('Other') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:textarea wire:model="editFeatureAiMode" :label="__('AI mode (optional)')" rows="2" :placeholder="$epic->ai_mode ? __('Inherits: ').$epic->ai_mode : __('Describe how AI should behave...')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Create Task Modal --}}
    <flux:modal name="create-task" focusable class="md:w-[520px]">
        <form wire:submit="createTask" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('New task') }}</flux:heading>
            </div>

            <flux:input wire:model="newTaskTitle" :label="__('Title')" autofocus required />
            <flux:textarea wire:model="newTaskDescription" :label="__('Description (optional)')" rows="3" />
            <flux:input
                wire:model="newTaskPriority"
                :label="__('Priority (0–10)')"
                type="number"
                min="0"
                max="10"
            />

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="newTaskTdd" :label="__('TDD')">
                    <flux:select.option value="">
                        {{ __('Inherit') }}@php $rt = $this->addingTaskForFeature?->resolvedTdd(); @endphp@if($rt !== null) ({{ $rt ? 'Enabled' : 'Disabled' }})@endif
                    </flux:select.option>
                    <flux:select.option value="1">{{ __('Enabled') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Disabled') }}</flux:select.option>
                </flux:select>
                @php $re = $this->addingTaskForFeature?->resolvedEnvironment(); @endphp
                <flux:select wire:model="newTaskEnvironment" :label="__('Environment')">
                    <flux:select.option value="">{{ __('Inherit') }}@if($re) ({{ $re }})@endif</flux:select.option>
                    <flux:select.option value="Development">{{ __('Development') }}</flux:select.option>
                    <flux:select.option value="Production">{{ __('Production') }}</flux:select.option>
                    <flux:select.option value="Staging">{{ __('Staging') }}</flux:select.option>
                    <flux:select.option value="Other">{{ __('Other') }}</flux:select.option>
                </flux:select>
            </div>

            @php $ram = $this->addingTaskForFeature?->resolvedAiMode(); @endphp
            <flux:textarea wire:model="newTaskAiMode" :label="__('AI mode (optional)')" rows="2" :placeholder="$ram ? __('Inherits: ').$ram : __('Describe how AI should behave...')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create task') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Task Detail Flyout --}}
    <flux:modal name="task-detail" flyout class="w-full max-w-lg">
        @if ($this->selectedTask)
            <div class="flex h-full flex-col gap-0">

                {{-- Task info / edit form --}}
                <div class="border-b border-zinc-200 pb-5 dark:border-zinc-700">
                    @if (! $editingTask)
                        <div class="flex items-start justify-between gap-3">
                            <flux:heading size="lg" class="leading-snug">{{ $this->selectedTask->title }}</flux:heading>
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="pencil"
                                wire:click="startEditingTask"
                            >{{ __('Edit') }}</flux:button>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <flux:badge color="{{ $this->selectedTask->status->color() }}">
                                {{ $this->selectedTask->status->label() }}
                            </flux:badge>
                            <flux:badge color="zinc">Priority: {{ $this->selectedTask->priority }}</flux:badge>
                            @if ($this->selectedTask->assignee)
                                <flux:badge color="sky" icon="user">{{ $this->selectedTask->assignee->name }}</flux:badge>
                            @endif
                            @php $resolvedTdd = $this->selectedTask->resolvedTdd(); @endphp
                            @if ($resolvedTdd !== null)
                                <flux:badge color="{{ $resolvedTdd ? 'green' : 'zinc' }}" size="sm">
                                    TDD: {{ $resolvedTdd ? 'On' : 'Off' }}
                                    @if ($this->selectedTask->tdd === null)<span class="opacity-60"> ↑</span>@endif
                                </flux:badge>
                            @endif
                            @php $resolvedEnv = $this->selectedTask->resolvedEnvironment(); @endphp
                            @if ($resolvedEnv)
                                <flux:badge color="sky" size="sm">
                                    {{ $resolvedEnv }}
                                    @if ($this->selectedTask->environment === null)<span class="opacity-60"> ↑</span>@endif
                                </flux:badge>
                            @endif
                        </div>

                        @if ($this->selectedTask->description)
                            <flux:text class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $this->selectedTask->description }}
                            </flux:text>
                        @endif

                        @php $resolvedAi = $this->selectedTask->resolvedAiMode(); @endphp
                        @if ($resolvedAi)
                            <div class="mt-3 rounded-lg bg-purple-50 px-3 py-2 dark:bg-purple-950/30">
                                <flux:text class="text-xs font-medium text-purple-700 dark:text-purple-400">
                                    {{ __('AI mode') }}@if($this->selectedTask->ai_mode === null) <span class="font-normal opacity-70">(inherited)</span>@endif
                                </flux:text>
                                <flux:text class="mt-0.5 text-xs text-purple-600 dark:text-purple-300">{{ $resolvedAi }}</flux:text>
                            </div>
                        @endif
                    @else
                        <form wire:submit="saveTask" class="space-y-4">
                            <flux:input wire:model="editTaskTitle" :label="__('Title')" autofocus required />
                            <flux:textarea wire:model="editTaskDescription" :label="__('Description')" rows="3" />

                            <div class="grid grid-cols-2 gap-4">
                                <flux:select wire:model="editTaskStatus" :label="__('Status')">
                                    @foreach (TaskStatus::cases() as $status)
                                        <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:input
                                    wire:model="editTaskPriority"
                                    :label="__('Priority (0–10)')"
                                    type="number"
                                    min="0"
                                    max="10"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <flux:select wire:model="editTaskTdd" :label="__('TDD')">
                                    <flux:select.option value="">
                                        {{ __('Inherit') }}@php $pTdd = $this->selectedTask->feature?->resolvedTdd(); @endphp@if($pTdd !== null) ({{ $pTdd ? 'Enabled' : 'Disabled' }})@endif
                                    </flux:select.option>
                                    <flux:select.option value="1">{{ __('Enabled') }}</flux:select.option>
                                    <flux:select.option value="0">{{ __('Disabled') }}</flux:select.option>
                                </flux:select>
                                @php $pEnv = $this->selectedTask->feature?->resolvedEnvironment(); @endphp
                                <flux:select wire:model="editTaskEnvironment" :label="__('Environment')">
                                    <flux:select.option value="">{{ __('Inherit') }}@if($pEnv) ({{ $pEnv }})@endif</flux:select.option>
                                    <flux:select.option value="Development">{{ __('Development') }}</flux:select.option>
                                    <flux:select.option value="Production">{{ __('Production') }}</flux:select.option>
                                    <flux:select.option value="Staging">{{ __('Staging') }}</flux:select.option>
                                    <flux:select.option value="Other">{{ __('Other') }}</flux:select.option>
                                </flux:select>
                            </div>

                            @php $pAi = $this->selectedTask->feature?->resolvedAiMode(); @endphp
                            <flux:textarea wire:model="editTaskAiMode" :label="__('AI mode (optional)')" rows="2" :placeholder="$pAi ? __('Inherits: ').$pAi : __('Describe how AI should behave...')" />

                            <div class="flex justify-end gap-2">
                                <flux:button variant="ghost" wire:click="cancelEditingTask" type="button">
                                    {{ __('Cancel') }}
                                </flux:button>
                                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                            </div>
                        </form>
                    @endif
                </div>

                {{-- History --}}
                <div class="flex-1 overflow-y-auto pt-5">
                    <flux:heading size="sm" class="mb-4 font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                        {{ __('History') }}
                    </flux:heading>

                    <div class="space-y-3">
                        @forelse ($this->selectedTask->history as $entry)
                            <div @class([
                                'flex gap-3 rounded-lg p-3',
                                'bg-purple-50 dark:bg-purple-950/30' => $entry->actor_type === ActorType::Ai,
                                'bg-zinc-50 dark:bg-zinc-800/40' => $entry->actor_type !== ActorType::Ai,
                            ])>
                                <div class="mt-0.5 shrink-0">
                                    @if ($entry->actor_type === ActorType::Ai)
                                        <flux:badge color="purple" size="sm" icon="cpu-chip">AI</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm" icon="user">User</flux:badge>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="text-sm font-medium">{{ $entry->summary() }}</span>
                                        <span class="shrink-0 text-xs text-zinc-400">
                                            {{ $entry->created_at->diffForHumans() }}
                                        </span>
                                    </div>
                                    @if ($entry->actor_type === ActorType::Ai && $entry->changedByToken)
                                        <flux:text class="mt-0.5 text-xs text-purple-600 dark:text-purple-400">
                                            {{ $entry->changedByToken->name }}@if ($entry->changedByToken->version) · {{ $entry->changedByToken->version }}@endif
                                        </flux:text>
                                    @elseif ($entry->changedByUser)
                                        <flux:text class="mt-0.5 text-xs text-zinc-500">
                                            {{ $entry->changedByUser->name }}
                                        </flux:text>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <flux:text class="text-sm text-zinc-400">{{ __('No history yet.') }}</flux:text>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>

</div>
