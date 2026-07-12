<?php

use App\Enums\ActorType;
use App\Enums\EpicStatus;
use App\Enums\FeatureStatus;
use App\Enums\HistoryAction;
use App\Enums\TaskStatus;
use App\Models\Epic;
use App\Models\EpicHistory;
use App\Models\Feature;
use App\Models\FeatureHistory;
use App\Models\Task;
use App\Models\TaskHistory;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Epic Board')] class extends Component {
    public Epic $epic;

    public string $viewMode = 'board';

    public bool $showFilters = false;
    public array $filterFeatureIds = [];
    public array $filterStatuses = [];
    public array $collapsedFeatureIds = [];

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

    // Deletion confirmation
    public ?string $deletingFeatureId = null;
    public ?string $deletingTaskId = null;

    // Highlight after returning from an edit
    public ?string $highlightedId = null;
    public ?string $lastEditedId = null;

    // Conversation thread replies
    public string $taskReplyBody = '';
    public string $featureReplyBody = '';
    public string $epicReplyBody = '';

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

        $route = request()->route()?->getName();

        $this->viewMode = match ($route) {
            'epics.board.kanban' => 'kanban',
            'epics.board.queue' => 'sort',
            default => 'board',
        };

        match ($route) {
            'epics.board.edit' => $this->openEditEpic(),
            'epics.board.feature' => $this->openEditFeature(request()->route('feature')),
            'epics.board.task' => $this->openTask(request()->route('task')),
            default => null,
        };

        $this->highlightedId = session('highlighted_id');

        $prefs = auth()->user()?->preferences ?? [];
        $this->filterStatuses = $prefs['filter_statuses'][$this->viewMode] ?? [];
        $this->collapsedFeatureIds = $prefs['collapsed_feature_ids'][$this->epic->id] ?? [];
    }

    public function updatedFilterStatuses(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $prefs = $user->preferences ?? [];
        $prefs['filter_statuses'][$this->viewMode] = $this->filterStatuses;
        $user->update(['preferences' => $prefs]);
    }

    #[Renderless]
    public function saveFeatureCollapse(string $featureId, bool $collapsed): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        if ($collapsed) {
            $this->collapsedFeatureIds = array_values(array_unique([...$this->collapsedFeatureIds, $featureId]));
        } else {
            $this->collapsedFeatureIds = array_values(array_filter($this->collapsedFeatureIds, fn ($id) => $id !== $featureId));
        }

        $prefs = $user->preferences ?? [];
        $prefs['collapsed_feature_ids'][$this->epic->id] = $this->collapsedFeatureIds;
        $user->update(['preferences' => $prefs]);
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

    public function closeEditEpic(): void
    {
        $this->redirect(route('epics.board', $this->epic), navigate: true);
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
        Flux::toast(variant: 'success', text: 'Epic updated.');
        $this->redirect(route('epics.board', $this->epic), navigate: true);
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
            'status' => FeatureStatus::Todo,
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

        session()->flash('highlighted_id', $this->editingFeatureId);
        unset($this->features);
        Flux::toast(variant: 'success', text: 'Feature updated.');
        $this->redirect(route('epics.board', $this->epic), navigate: true);
    }

    public function closeEditFeature(): void
    {
        $this->redirect(route('epics.board', $this->epic), navigate: true);
    }

    public function confirmDeleteFeature(string $featureId): void
    {
        $this->deletingFeatureId = $featureId;
        $this->modal('delete-feature')->show();
    }

    public function deleteFeature(): void
    {
        Feature::findOrFail($this->deletingFeatureId)->delete();
        $this->deletingFeatureId = null;
        $this->modal('delete-feature')->close();
        unset($this->features, $this->kanbanColumns, $this->sortedQueue, $this->allFeatures);
        Flux::toast(variant: 'success', text: 'Feature deleted.');
        $this->redirect(route('epics.board', $this->epic), navigate: true);
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
        $this->editingTask = true;
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

        $this->lastEditedId = $this->selectedTaskId;
        unset($this->selectedTask, $this->features, $this->kanbanColumns, $this->sortedQueue);
        Flux::toast(variant: 'success', text: 'Task saved.');
        $this->closeTask();
    }

    public function closeTask(): void
    {
        $highlightId = $this->selectedTaskId ?? $this->lastEditedId;
        if ($highlightId) {
            session()->flash('highlighted_id', $highlightId);
        }
        $this->redirect(route('epics.board', $this->epic), navigate: true);
    }

    public function confirmDeleteTask(string $taskId): void
    {
        $this->deletingTaskId = $taskId;
        $this->modal('delete-task')->show();
    }

    public function deleteTask(): void
    {
        $task = Task::findOrFail($this->deletingTaskId);
        $featureId = $task->feature_id;

        $siblings = Task::where('feature_id', $featureId)
            ->orderBy('order_index')
            ->pluck('id')
            ->toArray();
        $pos = array_search($this->deletingTaskId, $siblings, strict: true);
        $nextId = ($pos !== false) ? ($siblings[$pos + 1] ?? $siblings[$pos - 1] ?? null) : null;
        $highlightId = $nextId ?? $featureId;

        $task->delete();
        $this->deletingTaskId = null;
        $this->modal('delete-task')->close();
        unset($this->features, $this->kanbanColumns, $this->sortedQueue);
        Flux::toast(variant: 'success', text: 'Task deleted.');
        session()->flash('highlighted_id', $highlightId);
        $this->redirect(route('epics.board', $this->epic), navigate: true);
    }

    // ── Conversation threads ──────────────────────────────────────────────────

    public function addTaskReply(bool $sendToClaude = false): void
    {
        $this->validate(['taskReplyBody' => ['required', 'string', 'max:10000']]);

        TaskHistory::create([
            'task_id' => $this->selectedTaskId,
            'changed_by_user_id' => auth()->id(),
            'actor_type' => ActorType::User,
            'actor_name' => auth()->user()?->name,
            'action' => HistoryAction::Note,
            'body' => $this->taskReplyBody,
            'metadata' => $sendToClaude ? ['claude_request' => true] : null,
        ]);

        $this->taskReplyBody = '';
        unset($this->selectedTask);
    }

    public function addFeatureReply(bool $sendToClaude = false): void
    {
        $this->validate(['featureReplyBody' => ['required', 'string', 'max:10000']]);

        FeatureHistory::create([
            'feature_id' => $this->editingFeatureId,
            'changed_by_user_id' => auth()->id(),
            'actor_type' => ActorType::User,
            'actor_name' => auth()->user()?->name,
            'action' => HistoryAction::Note,
            'body' => $this->featureReplyBody,
            'metadata' => $sendToClaude ? ['claude_request' => true] : null,
        ]);

        $this->featureReplyBody = '';
        unset($this->editingFeature);
    }

    public function addEpicReply(): void
    {
        $this->validate(['epicReplyBody' => ['required', 'string', 'max:10000']]);

        EpicHistory::create([
            'epic_id' => $this->epic->id,
            'changed_by_user_id' => auth()->id(),
            'actor_type' => ActorType::User,
            'actor_name' => auth()->user()?->name,
            'action' => HistoryAction::Note,
            'body' => $this->epicReplyBody,
        ]);

        $this->epicReplyBody = '';
        unset($this->epicHistory);
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
        $this->dispatch('board-sorted', id: $taskId, type: 'task');
    }

    public function sortBoardFeature(string $featureId, int $position): void
    {
        $ids = Feature::where('epic_id', $this->epic->id)
            ->where('id', '!=', $featureId)
            ->orderBy('order_index')
            ->pluck('id')
            ->toArray();

        array_splice($ids, $position, 0, [$featureId]);

        foreach ($ids as $idx => $id) {
            Feature::where('id', $id)->update(['order_index' => $idx]);
        }

        unset($this->features);
        $this->dispatch('board-sorted', id: $featureId, type: 'feature');
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

    public function sortQueue(string $itemId, int $position): void
    {
        // itemId format: "task:{uuid}" or "feature:{uuid}"
        [$type, $id] = str_contains($itemId, ':') ? explode(':', $itemId, 2) : ['task', $itemId];

        $tasks = Task::whereHas('feature', fn ($q) => $q->where('epic_id', $this->epic->id))
            ->orderByRaw('COALESCE(execution_order, 999999)')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($t) => ['type' => 'task', 'id' => $t->id, 'order' => $t->execution_order ?? PHP_INT_MAX]);

        $features = Feature::where('epic_id', $this->epic->id)
            ->doesntHave('tasks')
            ->orderByRaw('COALESCE(execution_order, 999999)')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($f) => ['type' => 'feature', 'id' => $f->id, 'order' => $f->execution_order ?? PHP_INT_MAX]);

        $allItems = $tasks->merge($features)
            ->sortBy('order')
            ->values()
            ->map(fn ($item) => ['type' => $item['type'], 'id' => $item['id']])
            ->toArray();

        $allItems = array_values(array_filter($allItems, fn ($item) => !($item['type'] === $type && $item['id'] === $id)));
        array_splice($allItems, $position, 0, [['type' => $type, 'id' => $id]]);

        foreach ($allItems as $idx => $item) {
            if ($item['type'] === 'task') {
                Task::where('id', $item['id'])->update(['execution_order' => $idx]);
            } else {
                Feature::where('id', $item['id'])->update(['execution_order' => $idx]);
            }
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
            ->when(count($this->filterStatuses), fn ($q) => $q->where(fn ($q) => $q
                ->whereIn('status', $this->filterStatuses)
                ->orWhereHas('tasks', fn ($q) => $q->whereIn('status', $this->filterStatuses))
            ))
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

    /** @return Collection<int, Task|Feature> */
    #[Computed]
    public function sortedQueue(): Collection
    {
        $tasks = Task::with(['feature', 'latestHistory'])
            ->whereHas('feature', fn ($q) => $q->where('epic_id', $this->epic->id))
            ->when(count($this->filterFeatureIds), fn ($q) => $q->whereIn('feature_id', $this->filterFeatureIds))
            ->when(count($this->filterStatuses), fn ($q) => $q->whereIn('status', $this->filterStatuses))
            ->get();

        $features = Feature::where('epic_id', $this->epic->id)
            ->doesntHave('tasks')
            ->when(count($this->filterFeatureIds), fn ($q) => $q->whereIn('id', $this->filterFeatureIds))
            ->when(count($this->filterStatuses), fn ($q) => $q->whereIn('status', $this->filterStatuses))
            ->get();

        return $tasks->merge($features)
            ->sortBy(fn ($item) => [$item->execution_order ?? PHP_INT_MAX, $item->created_at->timestamp])
            ->values();
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

    #[Computed]
    public function editingFeature(): ?Feature
    {
        if (! $this->editingFeatureId) {
            return null;
        }

        return Feature::with([
            'history' => fn ($q) => $q->with('changedByUser', 'changedByToken')->oldest('created_at'),
        ])->find($this->editingFeatureId);
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\EpicHistory> */
    #[Computed]
    public function epicHistory(): \Illuminate\Support\Collection
    {
        return $this->epic->history()
            ->with('changedByUser', 'changedByToken')
            ->oldest('created_at')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6" data-view="epic-board">

    {{-- Page header --}}
    <div class="flex flex-col gap-1">
        <div class="flex items-center justify-between gap-2">
            <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('epics')" wire:navigate />
            <div class="flex items-center gap-2">
                <flux:tooltip content="E">
                    <flux:button variant="ghost" size="sm" icon="pencil" data-shortcut="edit-epic" :href="route('epics.board.edit', $epic)" wire:navigate>
                        {{ __('Edit epic') }}
                    </flux:button>
                </flux:tooltip>
                <flux:tooltip content="+ / N">
                    <flux:button variant="primary" size="sm" icon="plus" data-shortcut="add-feature" wire:click="openAddFeature">
                        {{ __('Add feature') }}
                    </flux:button>
                </flux:tooltip>
            </div>
        </div>
        <flux:heading size="xl">{{ $epic->name }}</flux:heading>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
            <span>Status: {{ $epic->status->label() }}</span>
            @if ($epic->tdd !== null)
                <span>TDD: {{ $epic->tdd ? 'On' : 'Off' }}</span>
            @endif
            @if ($epic->environment)
                <span>Environment: {{ $epic->environment }}</span>
            @endif
        </div>
        @if ($epic->repository_url)
            <a
                href="{{ $epic->repository_url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 text-xs text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 break-all"
            >
                <flux:icon.folder-git-2 class="size-3.5 shrink-0" />
                {{ $epic->repository_url }}
            </a>
        @endif
        @if ($epic->description)
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 break-words">
                {{ $epic->description }}
            </flux:text>
        @endif
    </div>

    {{-- View controls --}}
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-1 rounded-lg border border-zinc-200 p-1 dark:border-zinc-700">
            <flux:tooltip content="1">
                <flux:button
                    variant="{{ $viewMode === 'board' ? 'filled' : 'ghost' }}"
                    size="sm"
                    data-shortcut="view-board"
                    :href="route('epics.board', $epic)"
                    wire:navigate
                >{{ __('Board') }}</flux:button>
            </flux:tooltip>
            <flux:tooltip content="2">
                <flux:button
                    variant="{{ $viewMode === 'kanban' ? 'filled' : 'ghost' }}"
                    size="sm"
                    data-shortcut="view-kanban"
                    :href="route('epics.board.kanban', $epic)"
                    wire:navigate
                >{{ __('Kanban') }}</flux:button>
            </flux:tooltip>
            <flux:tooltip content="3">
                <flux:button
                    variant="{{ $viewMode === 'sort' ? 'filled' : 'ghost' }}"
                    size="sm"
                    data-shortcut="view-sort"
                    :href="route('epics.board.queue', $epic)"
                    wire:navigate
                >{{ __('AI Queue') }}</flux:button>
            </flux:tooltip>
        </div>
        <div class="flex items-center gap-1">
            @if ($viewMode === 'board')
                <div x-data="{ boardAllCollapsed: false }">
                    <flux:tooltip content="Shift+T">
                        <flux:button
                            variant="ghost"
                            size="sm"
                            x-on:click="boardAllCollapsed = !boardAllCollapsed; window.dispatchEvent(new CustomEvent('board-collapse-all', { detail: { collapsed: boardAllCollapsed } }))"
                        ><span x-text="boardAllCollapsed ? 'Expand all' : 'Collapse all'">Collapse all</span></flux:button>
                    </flux:tooltip>
                </div>
            @endif
            <flux:tooltip content="F">
                <flux:button
                    variant="{{ $showFilters ? 'filled' : 'ghost' }}"
                    size="sm"
                    icon="funnel"
                    data-shortcut="toggle-filters"
                    wire:click="$toggle('showFilters')"
                >
                {{ __('Filter') }}
                @if (count($filterStatuses) > 0)
                    <flux:badge color="blue" size="sm" class="ml-1">{{ count($filterStatuses) }}</flux:badge>
                @endif
            </flux:button>
        </flux:tooltip>
        </div>
    </div>

    {{-- Filter panel --}}
    @if ($showFilters)
        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50" data-filter-panel>
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
            <div class="mt-3 flex justify-end">
                <flux:button variant="ghost" size="sm" wire:click="$set('filterStatuses', [])" :disabled="count($filterStatuses) === 0">
                    {{ __('Clear filters') }}
                </flux:button>
            </div>
        </div>
    @endif

    {{-- ── Board view ──────────────────────────────────────────────────────── --}}
    @if ($viewMode === 'board')
        <div class="space-y-6" data-board-mode="board"
             wire:sort="sortBoardFeature"
             x-on:board-task-reorder.window="$wire.sortBoard($event.detail.taskId, $event.detail.position)"
             x-on:board-feature-reorder.window="$wire.sortBoardFeature($event.detail.featureId, $event.detail.position)">
            @forelse ($this->features as $feature)
                <div
                    wire:key="board-feature-{{ $feature->id }}"
                    wire:sort:item="{{ $feature->id }}"
                    data-board-feature-card
                    x-data="{ collapsed: {{ in_array($feature->id, $collapsedFeatureIds) ? 'true' : 'false' }} }"
                    x-on:board-collapse-all.window="collapsed = $event.detail.collapsed"
                    x-on:board-toggle-collapse.window="if ($event.detail.featureId === '{{ $feature->id }}') collapsed = !collapsed"
                    x-init="
                        $watch('collapsed', value => $wire.saveFeatureCollapse('{{ $feature->id }}', value));
                        if ($el.hasAttribute('data-highlight-scroll')) $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
                    "
                    @if ($highlightedId === $feature->id) data-highlight-scroll @endif
                    @class([
                        'rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900',
                        'ring-2 ring-blue-400 dark:ring-blue-500' => $highlightedId === $feature->id,
                    ])
                >
                    {{-- Feature header --}}
                    <div data-selectable data-feature-id="{{ $feature->id }}" @if ($highlightedId === $feature->id) data-highlighted @endif class="flex flex-col border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <div wire:sort:handle class="shrink-0 cursor-grab text-zinc-300 hover:text-zinc-500 dark:hover:text-zinc-400">
                                    <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
                                        <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                                        <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                                        <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                                    </svg>
                                </div>
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
                                @if ($feature->tasks->isNotEmpty())
                                    <flux:tooltip content="T">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="chevron-down"
                                            @click.stop="collapsed = !collapsed"
                                            class="transition-transform duration-200"
                                            x-bind:class="{ '-rotate-90': collapsed }"
                                        />
                                    </flux:tooltip>
                                @endif
                                <flux:tooltip :content="__('Add task')">
                                    <flux:button variant="ghost" size="sm" icon="plus" wire:click="openAddTask('{{ $feature->id }}')" />
                                </flux:tooltip>
                                <flux:tooltip :content="__('Edit feature')">
                                    <flux:button variant="ghost" size="sm" icon="pencil" data-open-btn :href="route('epics.board.feature', [$epic, $feature])" wire:navigate />
                                </flux:tooltip>
                            </div>
                        </div>
                        <span class="font-semibold">{{ $feature->name }}</span>
                    </div>

                    {{-- Tasks --}}
                    @if ($feature->tasks->isNotEmpty())
                        {{-- Collapsed summary row --}}
                        @php $statusCounts = $feature->tasks->groupBy(fn ($t) => $t->status->value)->map->count(); @endphp
                        <div
                            x-show="collapsed"
                            x-cloak
                            @click="collapsed = false"
                            class="flex cursor-pointer items-center gap-1.5 px-5 py-3 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                        >
                            <div class="flex flex-1 flex-wrap items-center gap-1.5">
                                @foreach (TaskStatus::cases() as $status)
                                    @if ($statusCounts->has($status->value))
                                        <flux:badge color="{{ $status->color() }}" size="sm">{{ $status->label() }} · {{ $statusCounts[$status->value] }}</flux:badge>
                                    @endif
                                @endforeach
                            </div>
                            <flux:tooltip content="T">
                                <flux:button variant="ghost" size="sm" icon="chevron-up" @click.stop="collapsed = false" class="shrink-0" />
                            </flux:tooltip>
                        </div>

                        <ul x-show="!collapsed" wire:sort="sortBoard" class="divide-y divide-zinc-100 list-none dark:divide-zinc-800">
                            @foreach ($feature->tasks as $task)
                                <li
                                    wire:key="board-{{ $task->id }}"
                                    wire:sort:item="{{ $task->id }}"
                                    data-selectable
                                    @if ($highlightedId === $task->id) data-highlighted @endif
                                    @class(['flex items-start', 'bg-blue-50 dark:bg-blue-950/20' => $highlightedId === $task->id])
                                    @if ($highlightedId === $task->id) x-data x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))" @endif
                                >
                                    <div wire:sort:handle class="cursor-grab px-3 pt-3 text-zinc-300 hover:text-zinc-500 dark:hover:text-zinc-400">
                                        <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
                                            <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                                            <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                                            <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                                        </svg>
                                    </div>
                                    <a
                                        data-open-btn
                                        wire:navigate
                                        href="{{ route('epics.board.task', [$epic, $task]) }}"
                                        class="flex flex-1 flex-col py-3 text-left transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                    >
                                        <div class="flex flex-wrap items-center gap-1.5">
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
                                        </div>
                                        <span class="mt-1 text-sm font-medium">{{ $task->title }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div data-selectable class="px-5 py-6 text-center transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <flux:text class="text-sm text-zinc-400">{{ __('No tasks yet.') }}</flux:text>
                            <div class="mt-2">
                                <flux:button data-open-btn variant="ghost" size="sm" icon="plus" wire:click="openAddTask('{{ $feature->id }}')">
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
        <div class="overflow-x-auto pb-4" data-board-mode="kanban">
            <div class="flex gap-4" style="min-width: max-content">
                @foreach ($this->kanbanColumns as $column)
                    <div class="flex w-64 flex-col gap-2" data-kanban-col="{{ $loop->index }}">
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
                                    data-selectable
                                    @if ($highlightedId === $task->id) data-highlighted @endif
                                    @class([
                                        'rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900',
                                        'ring-2 ring-blue-400 dark:ring-blue-500' => $highlightedId === $task->id,
                                    ])
                                    @if ($highlightedId === $task->id) x-data x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))" @endif
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
                                            <a
                                                data-open-btn
                                                wire:navigate
                                                href="{{ route('epics.board.task', [$epic, $task]) }}"
                                                class="block w-full text-left text-sm font-medium leading-snug hover:underline"
                                                style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"
                                            >{{ $task->title }}</a>
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
        <div data-board-mode="queue">
            <div class="mb-3 flex items-center justify-between">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Drag tasks to set the order in which the AI should execute them.') }}
                </flux:text>
                <flux:text class="text-xs text-zinc-400">{{ $this->sortedQueue->count() }} {{ Str::plural('item', $this->sortedQueue->count()) }}</flux:text>
            </div>

            @if ($this->sortedQueue->isNotEmpty())
                <ul wire:sort="sortQueue"
                    x-on:queue-reorder.window="$wire.sortQueue($event.detail.itemId, $event.detail.position)"
                    class="list-none space-y-2">
                    @foreach ($this->sortedQueue as $index => $item)
                        @php $isTask = $item instanceof \App\Models\Task; @endphp
                        <li
                            wire:key="queue-{{ $item->id }}"
                            wire:sort:item="{{ $isTask ? 'task' : 'feature' }}:{{ $item->id }}"
                            data-selectable
                            @if ($highlightedId === $item->id) data-highlighted @endif
                            @class([
                                'flex items-start gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900',
                                'ring-2 ring-blue-400 dark:ring-blue-500' => $highlightedId === $item->id,
                            ])
                            @if ($highlightedId === $item->id) x-data x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))" @endif
                        >
                            <span class="w-7 shrink-0 pt-0.5 text-right font-mono text-sm text-zinc-400">{{ $index + 1 }}</span>
                            <div wire:sort:handle class="shrink-0 cursor-grab pt-0.5 text-zinc-300 hover:text-zinc-500">
                                <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
                                    <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                                    <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                                    <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                {{-- Badges --}}
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <flux:badge color="{{ $item->status->color() }}" size="sm">{{ $item->status->label() }}</flux:badge>
                                    @if ($isTask)
                                        <flux:badge color="zinc" size="sm" class="tabular-nums">P{{ $item->priority }}</flux:badge>
                                    @endif
                                    @if ($item->resolvedTdd() !== null)
                                        <flux:badge color="{{ $item->resolvedTdd() ? 'green' : 'zinc' }}" size="sm">TDD</flux:badge>
                                    @endif
                                    @if ($item->resolvedEnvironment())
                                        <flux:badge color="sky" size="sm">{{ $item->resolvedEnvironment() }}</flux:badge>
                                    @endif
                                </div>

                                {{-- Feature name (always above, prominent) --}}
                                @if ($isTask && $item->feature)
                                    <p class="mt-1.5 truncate text-sm font-semibold text-zinc-800 dark:text-zinc-100">Feature: {{ $item->feature->name }}</p>
                                    <a
                                        data-open-btn
                                        wire:navigate
                                        href="{{ route('epics.board.task', [$epic, $item]) }}"
                                        class="mt-0.5 block truncate text-xs text-zinc-500 hover:underline dark:text-zinc-400"
                                    >{{ $item->title }}</a>
                                @elseif (! $isTask)
                                    <a
                                        data-open-btn
                                        wire:navigate
                                        href="{{ route('epics.board.feature', [$epic, $item]) }}"
                                        class="mt-1.5 block truncate text-sm font-semibold text-zinc-800 hover:underline dark:text-zinc-100"
                                    >Feature: {{ $item->name }}</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 bg-zinc-50 py-16 dark:border-zinc-700 dark:bg-zinc-900/50">
                    <flux:text class="text-base font-medium text-zinc-500 dark:text-zinc-400">{{ __('No items in queue') }}</flux:text>
                    <flux:text class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Create features or tasks, or adjust your filters.') }}</flux:text>
                </div>
            @endif
        </div>
    @endif

    {{-- ── Modals ───────────────────────────────────────────────────────────── --}}

    {{-- Edit Epic Modal --}}
    <flux:modal name="edit-epic" focusable class="modal-fullscreen" x-on:close="$wire.closeEditEpic()">
        <div class="flex h-full flex-col">
            <flux:heading size="lg" class="mb-5 shrink-0">{{ __('Edit epic') }}</flux:heading>

            <div class="flex min-h-0 flex-1 gap-8">
                {{-- Form --}}
                <div class="flex w-full flex-col overflow-y-auto md:w-96 md:shrink-0">
                    <form wire:submit="updateEpic" id="edit-epic-form" class="flex-1 space-y-5"
                        @keydown.ctrl.enter.prevent="$wire.updateEpic()"
                        @keydown.meta.enter.prevent="$wire.updateEpic()"
                    >
                        <flux:input wire:model="editEpicName" :label="__('Name')" autofocus required />
                        <flux:textarea wire:model="editEpicDescription" :label="__('Description (optional)')" rows="3" />
                        <flux:input wire:model="editEpicRepositoryUrl" :label="__('Repository URL (optional)')" type="text" placeholder="https://github.com/org/repo or git@github.com:org/repo.git" />

                        <flux:select wire:model="editEpicStatus" :label="__('Status')">
                            @foreach (EpicStatus::cases() as $status)
                                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                    </form>

                    <div class="mt-5 flex shrink-0 justify-end gap-2">
                        <flux:button variant="filled" wire:click="closeEditEpic">{{ __('Cancel') }}</flux:button>
                        <flux:tooltip content="Ctrl+Enter">
                            <flux:button variant="primary" form="edit-epic-form" type="submit">{{ __('Save changes') }}</flux:button>
                        </flux:tooltip>
                    </div>
                </div>

                {{-- Conversation thread --}}
                <div class="hidden min-h-0 flex-1 flex-col border-l border-zinc-200 pl-8 dark:border-zinc-700 md:flex">
                    <flux:heading size="sm" class="mb-3 shrink-0 font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                        {{ __('Thread') }}
                    </flux:heading>

                    <div class="flex-1 space-y-2 overflow-y-auto pb-2">
                        @forelse ($this->epicHistory as $entry)
                            @if ($entry->action === HistoryAction::Note)
                                <div @class([
                                    'rounded-xl p-4',
                                    'bg-purple-50 dark:bg-purple-950/30' => $entry->actor_type === ActorType::Ai,
                                    'bg-zinc-50 dark:bg-zinc-800/40' => $entry->actor_type !== ActorType::Ai,
                                ])>
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            @if ($entry->actor_type === ActorType::Ai)
                                                <flux:badge color="purple" size="sm" icon="cpu-chip">
                                                    {{ $entry->changedByToken?->name ?? 'AI' }}
                                                </flux:badge>
                                            @else
                                                <flux:badge color="zinc" size="sm" icon="user">
                                                    {{ $entry->changedByUser?->name ?? $entry->actor_name ?? 'User' }}
                                                </flux:badge>
                                            @endif
                                        </div>
                                        <span class="shrink-0 text-xs text-zinc-400">{{ $entry->created_at->diffForHumans() }}</span>
                                    </div>
                                    @if ($entry->body)
                                        <p class="whitespace-pre-wrap text-sm text-zinc-800 dark:text-zinc-200">{{ $entry->body }}</p>
                                    @elseif (isset($entry->metadata['message']))
                                        <p class="text-sm text-zinc-800 dark:text-zinc-200">{{ $entry->metadata['message'] }}</p>
                                    @endif
                                    @php $eMeta = $entry->metadata ?? []; @endphp
                                    @if (isset($eMeta['model']) || isset($eMeta['duration_ms']))
                                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-zinc-400">
                                            @if (isset($eMeta['model']))<span>{{ $eMeta['model'] }}</span>@endif
                                            @if (isset($eMeta['duration_ms']))<span>{{ number_format($eMeta['duration_ms'] / 1000, 1) }}s</span>@endif
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="flex items-center gap-2 px-1 py-1 text-xs text-zinc-400 dark:text-zinc-500">
                                    <span class="size-1.5 shrink-0 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                                    <span class="flex-1">{{ $entry->summary() }}</span>
                                    <span class="shrink-0">{{ $entry->created_at->diffForHumans() }}</span>
                                </div>
                            @endif
                        @empty
                            <flux:text class="text-sm text-zinc-400">{{ __('No history yet.') }}</flux:text>
                        @endforelse
                    </div>

                    <div class="mt-3 shrink-0 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:textarea
                            wire:model="epicReplyBody"
                            :placeholder="__('Add a note...')"
                            rows="3"
                        />
                        <div class="mt-2 flex justify-end">
                            <flux:button
                                variant="primary"
                                size="sm"
                                wire:click="addEpicReply"
                                :disabled="! trim($epicReplyBody)"
                            >{{ __('Send') }}</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- Create Feature Modal --}}
    <flux:modal name="create-feature" focusable class="md:w-[520px]">
        <form wire:submit="createFeature" class="space-y-5"
            @keydown.ctrl.enter.prevent="$wire.createFeature()"
            @keydown.meta.enter.prevent="$wire.createFeature()"
        >
            <div>
                <flux:heading size="lg">{{ __('New feature') }}</flux:heading>
                <flux:subheading>{{ __('Features are groups of related tasks within an epic.') }}</flux:subheading>
            </div>

            <flux:input wire:model="newFeatureName" :label="__('Name')" autofocus required />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                <flux:tooltip content="Ctrl+Enter">
                    <flux:button variant="primary" type="submit">{{ __('Create feature') }}</flux:button>
                </flux:tooltip>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Feature Modal --}}
    <flux:modal name="edit-feature" focusable class="modal-fullscreen" x-on:close="$wire.closeEditFeature()">
        <div class="flex h-full flex-col">
            <flux:heading size="lg" class="mb-5 shrink-0">{{ __('Edit feature') }}</flux:heading>

            <div class="flex min-h-0 flex-1 gap-8">
                {{-- Form --}}
                <div class="flex w-full flex-col overflow-y-auto md:w-96 md:shrink-0">
                    <form wire:submit="updateFeature" id="edit-feature-form" class="flex-1 space-y-5"
                        @keydown.ctrl.enter.prevent="$wire.updateFeature()"
                        @keydown.meta.enter.prevent="$wire.updateFeature()"
                    >
                        <flux:input wire:model="editFeatureName" :label="__('Name')" autofocus required />

                        <flux:select wire:model="editFeatureStatus" :label="__('Status')">
                            @foreach (FeatureStatus::cases() as $status)
                                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                    </form>

                    <div class="mt-5 flex shrink-0 items-center justify-between gap-2">
                        <flux:button variant="danger" icon="trash" size="sm" wire:click="confirmDeleteFeature('{{ $editingFeatureId }}')">{{ __('Delete') }}</flux:button>
                        <div class="flex gap-2">
                            <flux:button variant="filled" wire:click="closeEditFeature">{{ __('Cancel') }}</flux:button>
                            <flux:tooltip content="Ctrl+Enter">
                                <flux:button variant="primary" form="edit-feature-form" type="submit">{{ __('Save changes') }}</flux:button>
                            </flux:tooltip>
                        </div>
                    </div>
                </div>

                {{-- Conversation thread --}}
                <div class="hidden min-h-0 flex-1 flex-col border-l border-zinc-200 pl-8 dark:border-zinc-700 md:flex">
                    <flux:heading size="sm" class="mb-3 shrink-0 font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                        {{ __('Thread') }}
                    </flux:heading>

                    <div class="flex-1 space-y-2 overflow-y-auto pb-2">
                        @if ($this->editingFeature)
                            @forelse ($this->editingFeature->history as $entry)
                                @if ($entry->action === HistoryAction::Note)
                                    <div @class([
                                        'rounded-xl p-4',
                                        'bg-purple-50 dark:bg-purple-950/30' => $entry->actor_type === ActorType::Ai,
                                        'bg-zinc-50 dark:bg-zinc-800/40' => $entry->actor_type !== ActorType::Ai,
                                    ])>
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                @if ($entry->actor_type === ActorType::Ai)
                                                    <flux:badge color="purple" size="sm" icon="cpu-chip">
                                                        {{ $entry->changedByToken?->name ?? 'AI' }}
                                                    </flux:badge>
                                                @else
                                                    <flux:badge color="zinc" size="sm" icon="user">
                                                        {{ $entry->changedByUser?->name ?? $entry->actor_name ?? 'User' }}
                                                    </flux:badge>
                                                @endif
                                            </div>
                                            <span class="shrink-0 text-xs text-zinc-400">{{ $entry->created_at->diffForHumans() }}</span>
                                        </div>
                                        @if ($entry->body)
                                            <p class="whitespace-pre-wrap text-sm text-zinc-800 dark:text-zinc-200">{{ $entry->body }}</p>
                                        @elseif (isset($entry->metadata['message']))
                                            <p class="text-sm text-zinc-800 dark:text-zinc-200">{{ $entry->metadata['message'] }}</p>
                                        @endif
                                        @php $fMeta = $entry->metadata ?? []; @endphp
                                        @if (isset($fMeta['model']) || isset($fMeta['duration_ms']))
                                            <div class="mt-2 flex flex-wrap gap-2 text-xs text-zinc-400">
                                                @if (isset($fMeta['model']))<span>{{ $fMeta['model'] }}</span>@endif
                                                @if (isset($fMeta['duration_ms']))<span>{{ number_format($fMeta['duration_ms'] / 1000, 1) }}s</span>@endif
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 px-1 py-1 text-xs text-zinc-400 dark:text-zinc-500">
                                        <span class="size-1.5 shrink-0 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                                        <span class="flex-1">{{ $entry->summary() }}</span>
                                        <span class="shrink-0">{{ $entry->created_at->diffForHumans() }}</span>
                                    </div>
                                @endif
                            @empty
                                <flux:text class="text-sm text-zinc-400">{{ __('No history yet.') }}</flux:text>
                            @endforelse
                        @endif
                    </div>

                    <div class="mt-3 shrink-0 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:textarea
                            wire:model="featureReplyBody"
                            :placeholder="__('Add a note...')"
                            rows="3"
                        />
                        <div class="mt-2 flex justify-between">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="cpu-chip"
                                wire:click="addFeatureReply(true)"
                                :disabled="! trim($featureReplyBody)"
                            >{{ __('Send to Claude') }}</flux:button>
                            <flux:button
                                variant="primary"
                                size="sm"
                                wire:click="addFeatureReply"
                                :disabled="! trim($featureReplyBody)"
                            >{{ __('Send') }}</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- Create Task Modal --}}
    <flux:modal name="create-task" focusable class="md:w-[520px]">
        <form wire:submit="createTask" class="space-y-5"
            @keydown.ctrl.enter.prevent="$wire.createTask()"
            @keydown.meta.enter.prevent="$wire.createTask()"
        >
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

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                <flux:tooltip content="Ctrl+Enter">
                    <flux:button variant="primary" type="submit">{{ __('Create task') }}</flux:button>
                </flux:tooltip>
            </div>
        </form>
    </flux:modal>

    {{-- Task Detail Flyout --}}
    <flux:modal name="task-detail" flyout class="modal-fullscreen" x-on:close="$wire.closeTask()">
        @if ($this->selectedTask)
            <div class="flex h-full flex-col gap-0">

                {{-- Task edit form (always open) --}}
                <div class="border-b border-zinc-200 pb-5 dark:border-zinc-700">
                    <form wire:submit="saveTask" class="space-y-4"
                        @keydown.ctrl.enter.prevent="$wire.saveTask()"
                        @keydown.meta.enter.prevent="$wire.saveTask()"
                    >
                        {{-- Title + delete --}}
                        <div class="flex items-end gap-2">
                            <div class="min-w-0 flex-1">
                                <flux:input wire:model="editTaskTitle" :label="__('Title')" autofocus required />
                            </div>
                            <flux:button variant="ghost" size="sm" icon="trash" type="button" wire:click="confirmDeleteTask('{{ $this->selectedTask->id }}')" />
                        </div>

                        {{-- Description: auto-grow + maximize --}}
                        <div x-data="{
                            descExpanded: false,
                            draft: '',
                            openMax() {
                                this.draft = document.querySelector('[wire\\:model=\'editTaskDescription\']')?.value ?? '';
                                this.descExpanded = true;
                            }
                        }">
                            <div class="mb-1 flex items-center justify-between">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Description') }}</label>
                                <button type="button" @click="openMax()" class="text-zinc-400 transition-colors hover:text-zinc-600 dark:hover:text-zinc-300">
                                    <flux:icon name="arrows-pointing-out" class="size-4" />
                                </button>
                            </div>
                            <div x-data="{}"
                                x-init="
                                    const ta = $root.querySelector('textarea');
                                    if (ta) {
                                        const resize = () => { ta.style.overflow = 'hidden'; ta.style.resize = 'none'; ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; };
                                        ta.addEventListener('input', resize);
                                        $nextTick(resize);
                                    }
                                "
                            >
                                <flux:textarea wire:model="editTaskDescription" rows="3" />
                            </div>

                            {{-- Full-screen description overlay --}}
                            <div
                                x-show="descExpanded"
                                x-cloak
                                class="fixed inset-0 z-50 flex flex-col gap-4 bg-white p-6 dark:bg-zinc-900"
                                @keydown.escape.window="descExpanded = false"
                            >
                                <span class="text-base font-semibold text-zinc-800 dark:text-zinc-200">{{ __('Description') }}</span>
                                <textarea
                                    x-model="draft"
                                    @keydown.ctrl.enter.prevent.stop="$wire.editTaskDescription = draft; $wire.saveTask(); descExpanded = false"
                                    @keydown.meta.enter.prevent.stop="$wire.editTaskDescription = draft; $wire.saveTask(); descExpanded = false"
                                    class="flex-1 w-full resize-none rounded-xl border border-zinc-200 bg-white p-4 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    placeholder="{{ __('Enter description...') }}"
                                ></textarea>
                                <div class="flex justify-end gap-2">
                                    <flux:button type="button" @click="descExpanded = false">{{ __('Cancel') }}</flux:button>
                                    <flux:tooltip content="Ctrl+Enter">
                                        <flux:button variant="primary" type="button"
                                            @click="$wire.editTaskDescription = draft; $wire.saveTask(); descExpanded = false"
                                        >{{ __('Save') }}</flux:button>
                                    </flux:tooltip>
                                </div>
                            </div>
                        </div>

                        {{-- Status · Priority · TDD · Environment --}}
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <flux:select wire:model="editTaskStatus" :label="__('Status')">
                                @foreach (TaskStatus::cases() as $status)
                                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="editTaskPriority" :label="__('Priority')">
                                @for ($i = 0; $i <= 10; $i++)
                                    <flux:select.option value="{{ $i }}">{{ $i }}</flux:select.option>
                                @endfor
                            </flux:select>
                            <flux:select wire:model="editTaskTdd" :label="__('TDD')">
                                <flux:select.option value="">
                                    {{ __('Inherit') }}@php $pTdd = $this->selectedTask->feature?->resolvedTdd(); @endphp@if($pTdd !== null) ({{ $pTdd ? 'En' : 'Dis' }})@endif
                                </flux:select.option>
                                <flux:select.option value="1">{{ __('Enabled') }}</flux:select.option>
                                <flux:select.option value="0">{{ __('Disabled') }}</flux:select.option>
                            </flux:select>
                            @php $pEnv = $this->selectedTask->feature?->resolvedEnvironment(); @endphp
                            <flux:select wire:model="editTaskEnvironment" :label="__('Environment')">
                                <flux:select.option value="">{{ __('Inherit') }}@if($pEnv) ({{ Str::limit($pEnv, 4, '') }})@endif</flux:select.option>
                                <flux:select.option value="Development">{{ __('Development') }}</flux:select.option>
                                <flux:select.option value="Production">{{ __('Production') }}</flux:select.option>
                                <flux:select.option value="Staging">{{ __('Staging') }}</flux:select.option>
                                <flux:select.option value="Other">{{ __('Other') }}</flux:select.option>
                            </flux:select>
                        </div>

                        {{-- AI mode — collapsed by default --}}
                        @php $pAi = $this->selectedTask->feature?->resolvedAiMode(); @endphp
                        <div x-data="{ aiOpen: {{ $editTaskAiMode ? 'true' : 'false' }} }">
                            <button type="button" @click="aiOpen = !aiOpen"
                                class="flex items-center gap-1.5 text-sm font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                            >
                                <flux:icon x-show="!aiOpen" name="chevron-right" class="size-3.5" />
                                <flux:icon x-show="aiOpen" name="chevron-down" class="size-3.5" />
                                {{ __('AI mode') }}
                                @if (! $editTaskAiMode && $pAi)
                                    <span class="text-xs font-normal text-zinc-400">(inherited)</span>
                                @endif
                            </button>
                            <div x-show="aiOpen" class="mt-2">
                                <flux:textarea wire:model="editTaskAiMode" rows="2" :placeholder="$pAi ? __('Inherits: ').$pAi : __('Describe how AI should behave...')" />
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex justify-end gap-2">
                            <flux:button variant="ghost" type="button" wire:click="closeTask">
                                {{ __('Cancel') }}
                            </flux:button>
                            <flux:tooltip content="Ctrl+Enter">
                                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                            </flux:tooltip>
                        </div>
                    </form>
                </div>

                {{-- Thread --}}
                <div class="flex min-h-0 flex-1 flex-col pt-5">
                    <div class="flex-1 overflow-y-auto space-y-2 pb-2">
                        @forelse ($this->selectedTask->history as $entry)
                            @if ($entry->action === HistoryAction::Note)
                                <div @class([
                                    'rounded-xl p-4',
                                    'bg-purple-50 dark:bg-purple-950/30' => $entry->actor_type === ActorType::Ai,
                                    'bg-zinc-50 dark:bg-zinc-800/40' => $entry->actor_type !== ActorType::Ai,
                                ])>
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            @if ($entry->actor_type === ActorType::Ai)
                                                <flux:badge color="purple" size="sm" icon="cpu-chip">
                                                    {{ $entry->changedByToken?->name ?? 'AI' }}
                                                </flux:badge>
                                            @else
                                                <flux:badge color="zinc" size="sm" icon="user">
                                                    {{ $entry->changedByUser?->name ?? $entry->actor_name ?? 'User' }}
                                                </flux:badge>
                                            @endif
                                        </div>
                                        <span class="shrink-0 text-xs text-zinc-400">{{ $entry->created_at->diffForHumans() }}</span>
                                    </div>
                                    @if ($entry->body)
                                        <p class="whitespace-pre-wrap text-sm text-zinc-800 dark:text-zinc-200">{{ $entry->body }}</p>
                                    @elseif (isset($entry->metadata['message']))
                                        <p class="text-sm text-zinc-800 dark:text-zinc-200">{{ $entry->metadata['message'] }}</p>
                                    @endif
                                    @php $meta = $entry->metadata ?? []; @endphp
                                    @if (isset($meta['model']) || isset($meta['duration_ms']))
                                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-zinc-400">
                                            @if (isset($meta['model']))<span>{{ $meta['model'] }}</span>@endif
                                            @if (isset($meta['duration_ms']))<span>{{ number_format($meta['duration_ms'] / 1000, 1) }}s</span>@endif
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="flex items-center gap-2 px-1 py-1 text-xs text-zinc-400 dark:text-zinc-500">
                                    <span class="size-1.5 shrink-0 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                                    <span class="flex-1">{{ $entry->summary() }}</span>
                                    <span class="shrink-0">{{ $entry->created_at->diffForHumans() }}</span>
                                </div>
                            @endif
                        @empty
                            <flux:text class="text-sm text-zinc-400">{{ __('No history yet.') }}</flux:text>
                        @endforelse
                    </div>

                    {{-- Reply box --}}
                    <div class="mt-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:textarea
                            wire:model="taskReplyBody"
                            :placeholder="__('Add a note...')"
                            rows="3"
                        />
                        <div class="mt-2 flex justify-between">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="cpu-chip"
                                wire:click="addTaskReply(true)"
                                :disabled="! trim($taskReplyBody)"
                            >{{ __('Send to Claude') }}</flux:button>
                            <flux:button
                                variant="primary"
                                size="sm"
                                wire:click="addTaskReply"
                                :disabled="! trim($taskReplyBody)"
                            >{{ __('Send') }}</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Delete Feature Modal --}}
    <flux:modal name="delete-feature" class="w-full sm:min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete feature') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This will permanently delete the feature and all its tasks. This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteFeature">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete Task Modal --}}
    <flux:modal name="delete-task" class="w-full sm:min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete task') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This will permanently delete the task. This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteTask">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
