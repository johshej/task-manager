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

        $feature = $this->epic->features()->create([
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
        session()->flash('highlighted_id', $feature->id);
        $this->redirect(route('epics.board', $this->epic), navigate: true);
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
        unset($this->features, $this->kanbanColumns, $this->allFeatures);
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

        $task = $feature->tasks()->create([
            'title' => $this->newTaskTitle,
            'description' => $this->newTaskDescription ?: null,
            'status' => TaskStatus::Todo,
            'priority' => $this->newTaskPriority,
            'order_index' => $feature->tasks()->count(),
            'tdd' => $this->tddNullable($this->newTaskTdd),
            'ai_mode' => $this->newTaskAiMode ?: null,
            'environment' => $this->newTaskEnvironment ?: null,
        ]);

        $this->reset('newTaskTitle', 'newTaskDescription', 'addingTaskForFeatureId', 'newTaskTdd', 'newTaskAiMode', 'newTaskEnvironment');
        $this->newTaskPriority = 5;
        $this->modal('create-task')->close();
        unset($this->features, $this->kanbanColumns);
        Flux::toast(variant: 'success', text: 'Task created.');
        session()->flash('highlighted_id', $task->id);
        $this->redirect(route('epics.board', $this->epic), navigate: true);
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
        unset($this->selectedTask, $this->features, $this->kanbanColumns);
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
        unset($this->features, $this->kanbanColumns);
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

    public function moveTaskToTop(string $taskId): void
    {
        $this->sortBoard($taskId, 0);
    }

    public function moveTaskToBottom(string $taskId): void
    {
        $featureId = Task::findOrFail($taskId)->feature_id;

        $this->sortBoard($taskId, Task::where('feature_id', $featureId)->count());
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

    public function moveFeatureToTop(string $featureId): void
    {
        $this->sortBoardFeature($featureId, 0);
    }

    public function moveFeatureToBottom(string $featureId): void
    {
        $this->sortBoardFeature($featureId, $this->epic->features()->count());
    }

    public function sortKanban(string $taskId, int $position, string $statusValue): void
    {
        $task = Task::findOrFail($taskId);
        $newStatus = TaskStatus::from($statusValue);

        if ($task->status !== $newStatus) {
            $task->update(['status' => $newStatus]);
        }

        // In Kanban, each feature's tasks are their own isolated drag list per
        // status column (shared across columns for the same feature, so
        // dragging across columns changes status) — $position is already
        // "index among this feature's own same-status tasks". Find that Nth
        // sibling in the feature's full (all-status) order_index sequence and
        // insert there — this is the same ordering the board view reads, so
        // both stay consistent.
        $sameStatusIds = Task::where('feature_id', $task->feature_id)
            ->where('status', $statusValue)
            ->where('id', '!=', $taskId)
            ->orderBy('order_index')
            ->pluck('id')
            ->toArray();

        $featureTaskIds = Task::where('feature_id', $task->feature_id)
            ->where('id', '!=', $taskId)
            ->orderBy('order_index')
            ->pluck('id')
            ->toArray();

        $position = max(0, min($position, count($sameStatusIds)));
        $insertAt = $position < count($sameStatusIds)
            ? array_search($sameStatusIds[$position], $featureTaskIds)
            : count($featureTaskIds);

        array_splice($featureTaskIds, $insertAt, 0, [$taskId]);

        foreach ($featureTaskIds as $idx => $id) {
            Task::where('id', $id)->update(['order_index' => $idx]);
        }

        unset($this->kanbanColumns, $this->features);
        $this->dispatch('board-sorted', id: $taskId, type: 'task');
    }

    public function moveKanbanTaskToTop(string $taskId): void
    {
        $task = Task::findOrFail($taskId);

        $this->sortKanban($taskId, 0, $task->status->value);
    }

    public function moveKanbanTaskToBottom(string $taskId): void
    {
        $task = Task::findOrFail($taskId);
        $count = Task::where('feature_id', $task->feature_id)->where('status', $task->status->value)->count();

        $this->sortKanban($taskId, $count, $task->status->value);
    }

    public function sortKanbanFeature(string $featureId, int $position, string $statusValue): void
    {
        // The dragged feature's "position" is the index among features that are
        // themselves visible in this status column (the only ones in the same
        // drag list) — translate that into the correct spot in the epic's full
        // (all-column) feature order, preserving other columns' relative order.
        $allFeatureIds = Feature::where('epic_id', $this->epic->id)
            ->orderBy('order_index')
            ->pluck('id')
            ->toArray();

        $visibleFeatureIds = Feature::where('epic_id', $this->epic->id)
            ->where('id', '!=', $featureId)
            ->whereHas('tasks', fn ($q) => $q->where('status', $statusValue))
            ->orderBy('order_index')
            ->pluck('id')
            ->toArray();

        $position = max(0, min($position, count($visibleFeatureIds)));
        $spliced = $visibleFeatureIds;
        array_splice($spliced, $position, 0, [$featureId]);

        $siblingsBefore = 0;
        foreach ($spliced as $id) {
            if ($id === $featureId) {
                break;
            }
            $siblingsBefore++;
        }

        $fullIds = array_values(array_filter($allFeatureIds, fn ($id) => $id !== $featureId));

        $insertAt = $siblingsBefore < count($visibleFeatureIds)
            ? array_search($visibleFeatureIds[$siblingsBefore], $fullIds)
            : count($fullIds);

        array_splice($fullIds, $insertAt, 0, [$featureId]);

        foreach ($fullIds as $idx => $id) {
            Feature::where('id', $id)->update(['order_index' => $idx]);
        }

        unset($this->features, $this->kanbanColumns);
        $this->dispatch('board-sorted', id: $featureId, type: 'feature');
    }

    public function moveKanbanFeatureToTop(string $featureId, string $statusValue): void
    {
        $this->sortKanbanFeature($featureId, 0, $statusValue);
    }

    public function moveKanbanFeatureToBottom(string $featureId, string $statusValue): void
    {
        $count = Feature::where('epic_id', $this->epic->id)
            ->where('id', '!=', $featureId)
            ->whereHas('tasks', fn ($q) => $q->where('status', $statusValue))
            ->count();

        $this->sortKanbanFeature($featureId, $count, $statusValue);
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

    /** @return array<int, array{status: TaskStatus, groups: Collection<int, array{feature: Feature, tasks: Collection<int, Task>}>, count: int}> */
    #[Computed]
    public function kanbanColumns(): array
    {
        $statuses = count($this->filterStatuses)
            ? array_values(array_filter(TaskStatus::cases(), fn ($s) => in_array($s->value, $this->filterStatuses)))
            : TaskStatus::cases();

        $features = $this->epic->features()
            ->when(count($this->filterFeatureIds), fn ($q) => $q->whereIn('id', $this->filterFeatureIds))
            ->with(['tasks' => fn ($q) => $q->orderBy('order_index')->with('latestHistory')])
            ->orderBy('order_index')
            ->get();

        $columns = [];
        foreach ($statuses as $status) {
            $groups = $features
                ->map(fn ($feature) => [
                    'feature' => $feature,
                    'tasks' => $feature->tasks->where('status', $status)->values(),
                ])
                ->filter(fn ($group) => $group['tasks']->isNotEmpty())
                ->values();

            $columns[] = [
                'status' => $status,
                'groups' => $groups,
                'count' => $groups->sum(fn ($group) => $group['tasks']->count()),
            ];
        }

        return $columns;
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
             wire:sort:config="{ delay: 200, delayOnTouchOnly: true }"
             x-on:board-task-reorder.window="$wire.sortBoard($event.detail.taskId, $event.detail.position)"
             x-on:board-feature-reorder.window="$wire.sortBoardFeature($event.detail.featureId, $event.detail.position)"
             x-on:board-delete-feature.window="$wire.confirmDeleteFeature($event.detail.featureId)"
             x-on:board-delete-task.window="$wire.confirmDeleteTask($event.detail.taskId)">
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
                                <flux:dropdown>
                                    <button type="button" wire:sort:handle class="block shrink-0 cursor-grab appearance-none border-0 bg-transparent p-0 text-zinc-300 hover:text-zinc-500 dark:hover:text-zinc-400">
                                        <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
                                            <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                                            <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                                            <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                                        </svg>
                                    </button>
                                    <flux:menu>
                                        <flux:menu.item icon="chevron-double-up" wire:click="moveFeatureToTop('{{ $feature->id }}')">{{ __('Move to top') }}</flux:menu.item>
                                        <flux:menu.item icon="chevron-double-down" wire:click="moveFeatureToBottom('{{ $feature->id }}')">{{ __('Move to bottom') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
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
                                    <flux:button variant="ghost" size="sm" icon="plus" data-add-task-btn wire:click="openAddTask('{{ $feature->id }}')" />
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

                        <ul x-show="!collapsed" wire:sort="sortBoard" wire:sort:config="{ delay: 200, delayOnTouchOnly: true }" class="divide-y divide-zinc-100 list-none dark:divide-zinc-800">
                            @foreach ($feature->tasks as $task)
                                <li
                                    wire:key="board-{{ $task->id }}"
                                    wire:sort:item="{{ $task->id }}"
                                    data-selectable
                                    @if ($highlightedId === $task->id) data-highlighted @endif
                                    @class(['flex items-start', 'bg-blue-50 dark:bg-blue-950/20' => $highlightedId === $task->id])
                                    @if ($highlightedId === $task->id) x-data x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))" @endif
                                >
                                    <flux:dropdown>
                                        <button type="button" wire:sort:handle class="block cursor-grab appearance-none border-0 bg-transparent px-3 pt-3 text-zinc-300 hover:text-zinc-500 dark:hover:text-zinc-400">
                                            <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
                                                <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                                                <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                                                <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                                            </svg>
                                        </button>
                                        <flux:menu>
                                            <flux:menu.item icon="chevron-double-up" wire:click="moveTaskToTop('{{ $task->id }}')">{{ __('Move to top') }}</flux:menu.item>
                                            <flux:menu.item icon="chevron-double-down" wire:click="moveTaskToBottom('{{ $task->id }}')">{{ __('Move to bottom') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
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
        <div class="overflow-x-auto pb-4" data-board-mode="kanban"
             x-on:kanban-feature-reorder.window="$wire.sortKanbanFeature($event.detail.featureId, $event.detail.position, $event.detail.statusValue)"
             x-on:kanban-task-reorder.window="$wire.sortKanban($event.detail.taskId, $event.detail.position, $event.detail.statusValue)"
             x-on:board-delete-feature.window="$wire.confirmDeleteFeature($event.detail.featureId)"
             x-on:board-delete-task.window="$wire.confirmDeleteTask($event.detail.taskId)"
        >
            <div class="flex gap-4" style="min-width: max-content">
                @foreach ($this->kanbanColumns as $column)
                    <div class="flex w-64 flex-col gap-2" data-kanban-col="{{ $loop->index }}">
                        <div class="flex items-center gap-2 px-1">
                            <flux:badge color="{{ $column['status']->color() }}" size="sm">{{ $column['status']->label() }}</flux:badge>
                            <span class="text-xs text-zinc-400">{{ $column['count'] }}</span>
                        </div>
                        <ul
                            wire:sort="sortKanbanFeature"
                            wire:sort:group-id="{{ $column['status']->value }}"
                            wire:sort:config="{ delay: 200, delayOnTouchOnly: true }"
                            class="min-h-16 list-none space-y-2 rounded-xl border border-dashed border-zinc-200 p-2 dark:border-zinc-700"
                        >
                            @foreach ($column['groups'] as $group)
                                <li
                                    wire:key="kanban-feature-block-{{ $column['status']->value }}-{{ $group['feature']->id }}"
                                    wire:sort:item="{{ $group['feature']->id }}"
                                    class="space-y-2"
                                >
                                    <div
                                        data-selectable
                                        data-feature-id="{{ $group['feature']->id }}"
                                        class="flex cursor-pointer items-center gap-1 truncate rounded px-1 pt-1 text-xs font-semibold text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                                    >
                                        <flux:dropdown>
                                            <button type="button" wire:sort:handle class="block shrink-0 cursor-grab appearance-none border-0 bg-transparent p-0 text-zinc-300 hover:text-zinc-500 dark:hover:text-zinc-400">
                                                <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
                                                    <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                                                    <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                                                    <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                                                </svg>
                                            </button>
                                            <flux:menu>
                                                <flux:menu.item icon="chevron-double-up" wire:click="moveKanbanFeatureToTop('{{ $group['feature']->id }}', '{{ $column['status']->value }}')">{{ __('Move to top') }}</flux:menu.item>
                                                <flux:menu.item icon="chevron-double-down" wire:click="moveKanbanFeatureToBottom('{{ $group['feature']->id }}', '{{ $column['status']->value }}')">{{ __('Move to bottom') }}</flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                        <span class="truncate">{{ $group['feature']->name }}</span>
                                    </div>

                                    <ul
                                        wire:sort="sortKanban"
                                        wire:sort:group="kanban-tasks-{{ $group['feature']->id }}"
                                        wire:sort:group-id="{{ $column['status']->value }}"
                                        wire:sort:config="{ delay: 200, delayOnTouchOnly: true }"
                                        class="list-none space-y-2"
                                    >
                                        @foreach ($group['tasks'] as $task)
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
                                                    <flux:dropdown>
                                                        <button type="button" wire:sort:handle class="mt-0.5 block shrink-0 cursor-grab appearance-none border-0 bg-transparent p-0 text-zinc-300 hover:text-zinc-500">
                                                            <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
                                                                <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                                                                <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                                                                <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                                                            </svg>
                                                        </button>
                                                        <flux:menu>
                                                            <flux:menu.item icon="chevron-double-up" wire:click="moveKanbanTaskToTop('{{ $task->id }}')">{{ __('Move to top') }}</flux:menu.item>
                                                            <flux:menu.item icon="chevron-double-down" wire:click="moveKanbanTaskToBottom('{{ $task->id }}')">{{ __('Move to bottom') }}</flux:menu.item>
                                                        </flux:menu>
                                                    </flux:dropdown>
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
                                                            @if ($task->latestHistory?->actor_type === ActorType::Ai)
                                                                <flux:badge color="purple" size="sm" icon="cpu-chip">AI</flux:badge>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Modals ───────────────────────────────────────────────────────────── --}}

    {{-- Edit Epic Modal --}}
    <flux:modal name="edit-epic" focusable class="modal-fullscreen" x-on:close="$wire.closeEditEpic()">
        <div class="mx-auto flex h-full w-full max-w-lg flex-col overflow-y-auto">
            <flux:heading size="lg" class="mb-5 shrink-0">{{ __('Edit epic') }}</flux:heading>

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

                <div class="grid grid-cols-2 gap-4">
                    <x-fullscreen-link :label="__('AI mode')" icon="cpu-chip">
                        <flux:textarea wire:model="editEpicAiMode" class="flex-1" rows="6" :placeholder="__('Describe how AI should behave for this epic...')" />
                    </x-fullscreen-link>
                    <x-fullscreen-link :label="__('View notes')" :heading="__('Thread')" icon="chat-bubble-left-right">
                        <x-conversation-thread :history="$this->epicHistory" reply-model="epicReplyBody" send-method="addEpicReply" :show-send-to-claude="false" />
                    </x-fullscreen-link>
                </div>
            </form>

            <div class="mt-5 flex shrink-0 justify-end gap-2">
                <flux:button variant="filled" wire:click="closeEditEpic">{{ __('Cancel') }}</flux:button>
                <flux:tooltip content="Ctrl+Enter">
                    <flux:button variant="primary" form="edit-epic-form" type="submit">{{ __('Save changes') }}</flux:button>
                </flux:tooltip>
            </div>
        </div>
    </flux:modal>

    {{-- Create Feature Modal --}}
    <flux:modal name="create-feature" focusable class="modal-fullscreen">
        <div class="mx-auto flex h-full w-full max-w-lg flex-col justify-center overflow-y-auto">
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
        </div>
    </flux:modal>

    {{-- Edit Feature Modal --}}
    <flux:modal name="edit-feature" focusable class="modal-fullscreen" x-on:close="$wire.closeEditFeature()">
        <div class="mx-auto flex h-full w-full max-w-lg flex-col overflow-y-auto">
            <flux:heading size="lg" class="mb-5 shrink-0">{{ __('Edit feature') }}</flux:heading>

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

                <div class="grid grid-cols-2 gap-4">
                    <x-fullscreen-link :label="__('AI mode')" icon="cpu-chip">
                        <flux:textarea wire:model="editFeatureAiMode" class="flex-1" rows="6" :placeholder="$epic->ai_mode ? __('Inherits: ').$epic->ai_mode : __('Describe how AI should behave...')" />
                    </x-fullscreen-link>
                    <x-fullscreen-link :label="__('View notes')" :heading="__('Thread')" icon="chat-bubble-left-right">
                        <x-conversation-thread :history="$this->editingFeature?->history ?? collect()" reply-model="featureReplyBody" send-method="addFeatureReply" />
                    </x-fullscreen-link>
                </div>
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
    </flux:modal>

    {{-- Create Task Modal --}}
    <flux:modal name="create-task" focusable class="modal-fullscreen">
        <div class="mx-auto flex h-full w-full max-w-lg flex-col justify-center overflow-y-auto">
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
        </div>
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
                                data-fullscreen-overlay
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

                        {{-- AI mode + Notes --}}
                        @php $pAi = $this->selectedTask->feature?->resolvedAiMode(); @endphp
                        <div class="grid grid-cols-2 gap-4">
                            <x-fullscreen-link :label="__('AI mode')" icon="cpu-chip">
                                <flux:textarea wire:model="editTaskAiMode" class="flex-1" rows="6" :placeholder="$pAi ? __('Inherits: ').$pAi : __('Describe how AI should behave...')" />
                            </x-fullscreen-link>
                            <x-fullscreen-link :label="__('View notes')" :heading="__('Thread')" icon="chat-bubble-left-right">
                                <x-conversation-thread :history="$this->selectedTask->history" reply-model="taskReplyBody" send-method="addTaskReply" />
                            </x-fullscreen-link>
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
