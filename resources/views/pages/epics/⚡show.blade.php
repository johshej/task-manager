<?php

use App\Enums\ActorType;
use App\Enums\EpicStatus;
use App\Enums\FeatureStatus;
use App\Enums\TaskStatus;
use App\Models\Epic;
use App\Models\Feature;
use App\Models\Task;
use App\Models\TaskHistory;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Epic Board')] class extends Component {
    public Epic $epic;

    // Feature creation
    public string $newFeatureName = '';

    // Feature editing
    public ?string $editingFeatureId = null;
    public string $editFeatureName = '';
    public string $editFeatureStatus = '';

    // Task creation
    public ?string $addingTaskForFeatureId = null;
    public string $newTaskTitle = '';
    public string $newTaskDescription = '';
    public int $newTaskPriority = 5;

    // Task detail / editing
    public ?string $selectedTaskId = null;
    public bool $editingTask = false;
    public string $editTaskTitle = '';
    public string $editTaskDescription = '';
    public string $editTaskStatus = '';
    public int $editTaskPriority = 5;

    // Epic editing
    public bool $editingEpic = false;
    public string $editEpicName = '';
    public string $editEpicDescription = '';
    public string $editEpicRepositoryUrl = '';
    public string $editEpicStatus = '';

    public function mount(Epic $epic): void
    {
        $this->epic = $epic;
    }

    // ── Epic ──────────────────────────────────────────────────────────────────

    public function openEditEpic(): void
    {
        $this->editEpicName = $this->epic->name;
        $this->editEpicDescription = $this->epic->description ?? '';
        $this->editEpicRepositoryUrl = $this->epic->repository_url ?? '';
        $this->editEpicStatus = $this->epic->status->value;
        $this->modal('edit-epic')->show();
    }

    public function updateEpic(): void
    {
        $this->validate([
            'editEpicName' => ['required', 'string', 'max:255'],
            'editEpicDescription' => ['nullable', 'string'],
            'editEpicRepositoryUrl' => ['nullable', 'regex:/^(https?:\/\/\S+|git@[^:]+:\S+)$/', 'max:500'],
            'editEpicStatus' => ['required', 'in:' . implode(',', array_column(EpicStatus::cases(), 'value'))],
        ]);

        $this->epic->update([
            'name' => $this->editEpicName,
            'description' => $this->editEpicDescription ?: null,
            'repository_url' => $this->editEpicRepositoryUrl ?: null,
            'status' => $this->editEpicStatus,
        ]);

        $this->epic->refresh();
        $this->modal('edit-epic')->close();
        Flux::toast(variant: 'success', text: 'Epic updated.');
    }

    // ── Features ──────────────────────────────────────────────────────────────

    public function openAddFeature(): void
    {
        $this->reset('newFeatureName');
        $this->modal('create-feature')->show();
    }

    public function createFeature(): void
    {
        $this->validate(['newFeatureName' => ['required', 'string', 'max:255']]);

        $this->epic->features()->create([
            'name' => $this->newFeatureName,
            'status' => FeatureStatus::Planned,
            'order_index' => $this->epic->features()->count(),
        ]);

        $this->reset('newFeatureName');
        $this->modal('create-feature')->close();
        unset($this->features);
        Flux::toast(variant: 'success', text: 'Feature created.');
    }

    public function openEditFeature(string $featureId): void
    {
        $feature = Feature::findOrFail($featureId);
        $this->editingFeatureId = $featureId;
        $this->editFeatureName = $feature->name;
        $this->editFeatureStatus = $feature->status->value;
        $this->modal('edit-feature')->show();
    }

    public function updateFeature(): void
    {
        $this->validate([
            'editFeatureName' => ['required', 'string', 'max:255'],
            'editFeatureStatus' => ['required', 'in:' . implode(',', array_column(FeatureStatus::cases(), 'value'))],
        ]);

        Feature::findOrFail($this->editingFeatureId)->update([
            'name' => $this->editFeatureName,
            'status' => $this->editFeatureStatus,
        ]);

        $this->modal('edit-feature')->close();
        unset($this->features);
        Flux::toast(variant: 'success', text: 'Feature updated.');
    }

    // ── Tasks ─────────────────────────────────────────────────────────────────

    public function openAddTask(string $featureId): void
    {
        $this->addingTaskForFeatureId = $featureId;
        $this->reset('newTaskTitle', 'newTaskDescription');
        $this->newTaskPriority = 5;
        $this->modal('create-task')->show();
    }

    public function createTask(): void
    {
        $this->validate([
            'newTaskTitle' => ['required', 'string', 'max:255'],
            'newTaskDescription' => ['nullable', 'string'],
            'newTaskPriority' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $feature = Feature::findOrFail($this->addingTaskForFeatureId);

        $feature->tasks()->create([
            'title' => $this->newTaskTitle,
            'description' => $this->newTaskDescription ?: null,
            'status' => TaskStatus::Todo,
            'priority' => $this->newTaskPriority,
            'order_index' => $feature->tasks()->count(),
        ]);

        $this->reset('newTaskTitle', 'newTaskDescription', 'addingTaskForFeatureId');
        $this->newTaskPriority = 5;
        $this->modal('create-task')->close();
        unset($this->features);
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
        ]);

        Task::findOrFail($this->selectedTaskId)->update([
            'title' => $this->editTaskTitle,
            'description' => $this->editTaskDescription ?: null,
            'status' => $this->editTaskStatus,
            'priority' => $this->editTaskPriority,
        ]);

        $this->editingTask = false;
        unset($this->selectedTask);
        unset($this->features);
        Flux::toast(variant: 'success', text: 'Task saved.');
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    /** @return Collection<int, Feature> */
    #[Computed]
    public function features(): Collection
    {
        return $this->epic->features()
            ->with(['tasks' => fn ($q) => $q->orderBy('order_index')->with('latestHistory')])
            ->orderBy('order_index')
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
        ])->find($this->selectedTaskId);
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

        {{-- Features --}}
        <div class="space-y-6">
            @forelse ($this->features as $feature)
                <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    {{-- Feature header --}}
                    <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{ $feature->name }}</span>
                            <flux:badge color="{{ $feature->status->color() }}" size="sm">{{ $feature->status->label() }}</flux:badge>
                            <flux:text class="text-xs text-zinc-400">
                                {{ $feature->tasks->count() }} {{ Str::plural('task', $feature->tasks->count()) }}
                            </flux:text>
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
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($feature->tasks as $task)
                                <button
                                    type="button"
                                    wire:click="openTask('{{ $task->id }}')"
                                    class="flex w-full items-center gap-4 px-5 py-3 text-left transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                >
                                    {{-- Title --}}
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $task->title }}</span>

                                    {{-- Status --}}
                                    <flux:badge color="{{ $task->status->color() }}" size="sm">
                                        {{ $task->status->label() }}
                                    </flux:badge>

                                    {{-- Priority --}}
                                    <flux:badge color="zinc" size="sm" class="tabular-nums">
                                        P{{ $task->priority }}
                                    </flux:badge>

                                    {{-- Last change actor --}}
                                    @if ($task->latestHistory)
                                        @if ($task->latestHistory->actor_type === ActorType::Ai)
                                            <flux:badge color="purple" size="sm" icon="cpu-chip">AI</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm" icon="user">User</flux:badge>
                                        @endif
                                    @endif
                                </button>
                            @endforeach
                        </div>
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

    {{-- Edit Epic Modal --}}
    <flux:modal name="edit-epic" focusable class="md:w-[480px]">
        <form wire:submit="updateEpic" class="space-y-6">
            <flux:heading size="lg">{{ __('Edit epic') }}</flux:heading>

            <flux:input wire:model="editEpicName" :label="__('Name')" autofocus required />
            <flux:textarea wire:model="editEpicDescription" :label="__('Description (optional)')" rows="3" />
            <flux:input wire:model="editEpicRepositoryUrl" :label="__('Repository URL (optional)')" type="text" placeholder="https://github.com/org/repo or git@github.com:org/repo.git" />

            <flux:select wire:model="editEpicStatus" :label="__('Status')">
                @foreach (EpicStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Create Feature Modal --}}
    <flux:modal name="create-feature" focusable class="md:w-[480px]">
        <form wire:submit="createFeature" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New feature') }}</flux:heading>
                <flux:subheading>{{ __('Features are groups of related tasks within an epic.') }}</flux:subheading>
            </div>

            <flux:input wire:model="newFeatureName" :label="__('Name')" autofocus required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create feature') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Feature Modal --}}
    <flux:modal name="edit-feature" focusable class="md:w-[480px]">
        <form wire:submit="updateFeature" class="space-y-6">
            <flux:heading size="lg">{{ __('Edit feature') }}</flux:heading>

            <flux:input wire:model="editFeatureName" :label="__('Name')" autofocus required />

            <flux:select wire:model="editFeatureStatus" :label="__('Status')">
                @foreach (FeatureStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Create Task Modal --}}
    <flux:modal name="create-task" focusable class="md:w-[480px]">
        <form wire:submit="createTask" class="space-y-6">
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
                        </div>

                        @if ($this->selectedTask->description)
                            <flux:text class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $this->selectedTask->description }}
                            </flux:text>
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
