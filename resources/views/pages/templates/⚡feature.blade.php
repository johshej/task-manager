<?php

use App\Models\FeatureTemplate;
use App\Models\FeatureTemplateTask;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Feature Template')] class extends Component {
    public FeatureTemplate $featureTemplate;

    public string $editName = '';
    public string $editDescription = '';
    public string $editTdd = '';
    public string $editAiMode = '';
    public string $editEnvironment = '';

    public string $newTaskTitle = '';
    public string $newTaskDescription = '';
    public int $newTaskPriority = 5;
    public string $newTaskTdd = '';
    public string $newTaskAiMode = '';
    public string $newTaskEnvironment = '';

    public ?string $editingTaskId = null;
    public string $editTaskTitle = '';
    public string $editTaskDescription = '';
    public int $editTaskPriority = 5;
    public string $editTaskTdd = '';
    public string $editTaskAiMode = '';
    public string $editTaskEnvironment = '';

    public ?string $deletingTaskId = null;

    public function mount(FeatureTemplate $featureTemplate): void
    {
        $this->featureTemplate = $featureTemplate;
        $this->editName = $featureTemplate->name;
        $this->editDescription = $featureTemplate->description ?? '';
        $this->editTdd = $this->boolToTddString($featureTemplate->tdd);
        $this->editAiMode = $featureTemplate->ai_mode ?? '';
        $this->editEnvironment = $featureTemplate->environment ?? '';
    }

    private function tddNullable(string $value): ?bool
    {
        return $value === '' ? null : (bool) $value;
    }

    private function boolToTddString(?bool $value): string
    {
        return match ($value) {
            true => '1',
            false => '0',
            default => '',
        };
    }

    public function updateFeatureTemplate(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editDescription' => ['nullable', 'string'],
            'editTdd' => ['nullable', 'in:0,1'],
            'editAiMode' => ['nullable', 'string'],
            'editEnvironment' => ['nullable', 'string', 'max:100'],
        ]);

        $this->featureTemplate->update([
            'name' => $this->editName,
            'description' => $this->editDescription ?: null,
            'tdd' => $this->tddNullable($this->editTdd),
            'ai_mode' => $this->editAiMode ?: null,
            'environment' => $this->editEnvironment ?: null,
        ]);

        Flux::toast(variant: 'success', text: 'Feature template updated.');
    }

    public function openAddTask(): void
    {
        $this->reset('newTaskTitle', 'newTaskDescription', 'newTaskTdd', 'newTaskAiMode', 'newTaskEnvironment');
        $this->newTaskPriority = 5;
        $this->modal('create-template-task')->show();
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

        $this->featureTemplate->tasks()->create([
            'title' => $this->newTaskTitle,
            'description' => $this->newTaskDescription ?: null,
            'priority' => $this->newTaskPriority,
            'tdd' => $this->tddNullable($this->newTaskTdd),
            'ai_mode' => $this->newTaskAiMode ?: null,
            'environment' => $this->newTaskEnvironment ?: null,
            'order_index' => $this->featureTemplate->tasks()->count(),
        ]);

        $this->reset('newTaskTitle', 'newTaskDescription', 'newTaskTdd', 'newTaskAiMode', 'newTaskEnvironment');
        $this->newTaskPriority = 5;
        $this->modal('create-template-task')->close();
        unset($this->tasks);
        Flux::toast(variant: 'success', text: 'Task added.');
    }

    public function openEditTask(string $taskId): void
    {
        $task = FeatureTemplateTask::findOrFail($taskId);
        $this->editingTaskId = $taskId;
        $this->editTaskTitle = $task->title;
        $this->editTaskDescription = $task->description ?? '';
        $this->editTaskPriority = $task->priority;
        $this->editTaskTdd = $this->boolToTddString($task->tdd);
        $this->editTaskAiMode = $task->ai_mode ?? '';
        $this->editTaskEnvironment = $task->environment ?? '';
        $this->modal('edit-template-task')->show();
    }

    public function updateTask(): void
    {
        $this->validate([
            'editTaskTitle' => ['required', 'string', 'max:255'],
            'editTaskDescription' => ['nullable', 'string'],
            'editTaskPriority' => ['required', 'integer', 'min:0', 'max:10'],
            'editTaskTdd' => ['nullable', 'in:0,1'],
            'editTaskAiMode' => ['nullable', 'string'],
            'editTaskEnvironment' => ['nullable', 'string', 'max:100'],
        ]);

        FeatureTemplateTask::findOrFail($this->editingTaskId)->update([
            'title' => $this->editTaskTitle,
            'description' => $this->editTaskDescription ?: null,
            'priority' => $this->editTaskPriority,
            'tdd' => $this->tddNullable($this->editTaskTdd),
            'ai_mode' => $this->editTaskAiMode ?: null,
            'environment' => $this->editTaskEnvironment ?: null,
        ]);

        $this->editingTaskId = null;
        $this->modal('edit-template-task')->close();
        unset($this->tasks);
        Flux::toast(variant: 'success', text: 'Task updated.');
    }

    public function confirmDeleteTask(string $taskId): void
    {
        $this->deletingTaskId = $taskId;
        $this->modal('delete-template-task')->show();
    }

    public function deleteTask(): void
    {
        FeatureTemplateTask::findOrFail($this->deletingTaskId)->delete();
        $this->deletingTaskId = null;
        $this->modal('delete-template-task')->close();
        unset($this->tasks);
        Flux::toast(variant: 'success', text: 'Task deleted.');
    }

    public function sortTasks(string $taskId, int $position): void
    {
        $ids = FeatureTemplateTask::where('feature_template_id', $this->featureTemplate->id)
            ->where('id', '!=', $taskId)
            ->orderBy('order_index')
            ->pluck('id')
            ->toArray();

        array_splice($ids, $position, 0, [$taskId]);

        foreach ($ids as $idx => $id) {
            FeatureTemplateTask::where('id', $id)->update(['order_index' => $idx]);
        }

        unset($this->tasks);
    }

    /** @return Collection<int, FeatureTemplateTask> */
    #[Computed]
    public function tasks(): Collection
    {
        return $this->featureTemplate->tasks()->orderBy('order_index')->get();
    }
}; ?>

<div class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6" data-view="feature-template">
    <div class="flex items-center gap-2">
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('templates')" wire:navigate />
        <flux:heading size="xl">{{ __('Feature template') }}</flux:heading>
    </div>

    <form wire:submit="updateFeatureTemplate" class="space-y-5 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:input wire:model="editName" :label="__('Name')" required />
        <flux:textarea wire:model="editDescription" :label="__('Description (optional)')" rows="3" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:select wire:model="editTdd" :label="__('TDD')">
                <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                <flux:select.option value="1">{{ __('Enabled') }}</flux:select.option>
                <flux:select.option value="0">{{ __('Disabled') }}</flux:select.option>
            </flux:select>
            <flux:select wire:model="editEnvironment" :label="__('Environment')">
                <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                <flux:select.option value="Development">{{ __('Development') }}</flux:select.option>
                <flux:select.option value="Production">{{ __('Production') }}</flux:select.option>
                <flux:select.option value="Staging">{{ __('Staging') }}</flux:select.option>
                <flux:select.option value="Other">{{ __('Other') }}</flux:select.option>
            </flux:select>
        </div>

        <x-fullscreen-link :label="__('AI mode (optional)')" heading="AI mode" icon="cpu-chip">
            <flux:textarea wire:model="editAiMode" class="flex-1" rows="6" :placeholder="__('Describe how AI should behave...')" />
        </x-fullscreen-link>

        <div class="flex justify-end">
            <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
        </div>
    </form>

    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Subtasks') }}</flux:heading>
            <flux:button variant="primary" size="sm" icon="plus" wire:click="openAddTask">{{ __('Add task') }}</flux:button>
        </div>

        <ul
            wire:sort="sortTasks"
            wire:sort:config="{ delay: 200, delayOnTouchOnly: true }"
            class="list-none space-y-2"
        >
            @forelse ($this->tasks as $task)
                <li
                    wire:key="template-task-{{ $task->id }}"
                    wire:sort:item="{{ $task->id }}"
                    class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <button type="button" wire:sort:handle class="block shrink-0 cursor-grab appearance-none border-0 bg-transparent p-0 text-zinc-300 hover:text-zinc-500 dark:hover:text-zinc-400">
                        <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
                            <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                            <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                            <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                        </svg>
                    </button>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium">{{ $task->title }}</div>
                        <flux:badge color="zinc" size="sm" class="tabular-nums">P{{ $task->priority }}</flux:badge>
                    </div>
                    <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditTask('{{ $task->id }}')" />
                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmDeleteTask('{{ $task->id }}')" />
                </li>
            @empty
                <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 bg-zinc-50 py-10 dark:border-zinc-700 dark:bg-zinc-900/50">
                    <flux:text class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('No subtasks yet.') }}</flux:text>
                </div>
            @endforelse
        </ul>
    </div>

    {{-- Create Task Modal --}}
    <flux:modal name="create-template-task" :show="$errors->isNotEmpty()" focusable class="md:w-[480px]">
        <form wire:submit="createTask" class="space-y-5">
            <flux:heading size="lg">{{ __('New task') }}</flux:heading>
            <flux:input wire:model="newTaskTitle" :label="__('Title')" autofocus required />
            <flux:textarea wire:model="newTaskDescription" :label="__('Description (optional)')" rows="3" />
            <flux:input wire:model="newTaskPriority" :label="__('Priority (0–10)')" type="number" min="0" max="10" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Add task') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Task Modal --}}
    <flux:modal name="edit-template-task" :show="$errors->isNotEmpty()" focusable class="md:w-[480px]">
        <form wire:submit="updateTask" class="space-y-5">
            <flux:heading size="lg">{{ __('Edit task') }}</flux:heading>
            <flux:input wire:model="editTaskTitle" :label="__('Title')" autofocus required />
            <flux:textarea wire:model="editTaskDescription" :label="__('Description (optional)')" rows="3" />
            <flux:input wire:model="editTaskPriority" :label="__('Priority (0–10)')" type="number" min="0" max="10" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Task Modal --}}
    <flux:modal name="delete-template-task" class="w-full sm:min-w-[22rem]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete task') }}</flux:heading>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteTask">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
