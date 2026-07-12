<?php

use App\Enums\EpicStatus;
use App\Models\Epic;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Epics')] class extends Component {
    public string $name = '';
    public string $description = '';
    public string $repositoryUrl = '';
    public string $tdd = '';
    public string $aiMode = '';
    public string $environment = '';
    public array $filterStatuses = [];
    public bool $showFilters = false;

    private static function defaultAiMode(): string
    {
        return <<<'EOT'
        You are working inside a task manager. Work is organized in three levels:
        - Epic: the top-level goal (this context)
        - Feature: a deliverable sub-goal within the epic — each feature has its own git branch
        - Task: a concrete unit of work within a feature

        Each feature maps to a git branch named after the feature (slugified). If the branch does not exist, create it before starting work.

        If a feature has no tasks, treat the feature itself as the unit of work — use its name and description as the specification.

        When given a specific feature or task, work only on that. When asked to keep working on available items, use the AI Queue for this epic to determine priority — work through tasks top to bottom, one at a time, creating or switching to the feature branch for each. If you can do no more on a task and it is not done, add status as described below and move to the next one.

        When given a task, read its title, description, and thread history for context. Check the feature it belongs to for scope, and the epic for overall direction.

        When done with a task, update its status and add a note to the thread summarizing what you did, any decisions made, and what to watch out for.

        If a task is blocked or unclear, add a note explaining why and set the status to Blocked — do not guess.

        Follow the TDD setting: if TDD is enabled, write tests before implementation.

        Prefer small, focused changes. Do not modify tasks or features you were not asked to work on.
        EOT;
    }

    public function mount(): void
    {
        $this->aiMode = self::defaultAiMode();

        $prefs = auth()->user()?->preferences ?? [];
        $this->filterStatuses = $prefs['epics_filter_statuses'] ?? [EpicStatus::New->value, EpicStatus::Active->value];
    }

    public function updatedFilterStatuses(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $user->update([
            'preferences' => array_merge($user->preferences ?? [], [
                'epics_filter_statuses' => $this->filterStatuses,
            ]),
        ]);
    }

    public ?string $deletingEpicId = null;

    private function tddNullable(string $value): ?bool
    {
        return $value === '' ? null : (bool) $value;
    }

    public function createEpic(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'repositoryUrl' => ['nullable', 'regex:/^(https?:\/\/\S+|git@[^:]+:\S+)$/', 'max:500'],
            'tdd' => ['nullable', 'in:0,1'],
            'aiMode' => ['nullable', 'string'],
            'environment' => ['nullable', 'string', 'max:100'],
        ]);

        Epic::create([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'repository_url' => $this->repositoryUrl ?: null,
            'status' => EpicStatus::New,
            'tdd' => $this->tddNullable($this->tdd),
            'ai_mode' => $this->aiMode ?: null,
            'environment' => $this->environment ?: null,
        ]);

        $this->reset('name', 'description', 'repositoryUrl', 'tdd', 'environment');
        $this->aiMode = self::defaultAiMode();
        $this->modal('create-epic')->close();
        Flux::toast(variant: 'success', text: 'Epic created.');
    }

    public function confirmDeleteEpic(string $epicId): void
    {
        $this->deletingEpicId = $epicId;
        $this->modal('delete-epic')->show();
    }

    public function deleteEpic(): void
    {
        Epic::findOrFail($this->deletingEpicId)->delete();
        $this->deletingEpicId = null;
        $this->modal('delete-epic')->close();
        Flux::toast(variant: 'success', text: 'Epic deleted.');
    }

    /** @return Collection<int, Epic> */
    #[Computed]
    public function epics(): Collection
    {
        return Epic::withCount('features')
            ->when(count($this->filterStatuses), fn ($q) => $q->whereIn('status', $this->filterStatuses))
            ->latest()
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6" data-view="epics-index">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Epics') }}</flux:heading>
                <flux:subheading>{{ __('High-level bodies of work broken into features and tasks.') }}</flux:subheading>
            </div>
            <div class="flex items-center gap-2">
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
                <flux:modal.trigger name="create-epic" data-shortcut="new-epic">
                    <flux:button variant="primary" icon="plus">{{ __('New epic') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        {{-- Filter panel --}}
        @if ($showFilters)
            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50" data-filter-panel>
                <div class="space-y-1.5">
                    @foreach (EpicStatus::cases() as $s)
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

        <div class="space-y-3" data-list="epics">
            @forelse ($this->epics as $epic)
                <div
                    data-selectable
                    data-href="{{ route('epics.board', $epic) }}"
                    class="flex flex-col rounded-xl border border-zinc-200 bg-white p-4 transition-shadow hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
                >
                    {{-- Line 1: title + buttons --}}
                    <div class="flex items-start justify-between gap-2">
                        <a
                            href="{{ route('epics.board', $epic) }}"
                            wire:navigate
                            class="font-semibold hover:text-blue-600 dark:hover:text-blue-400"
                        >{{ $epic->name }}</a>
                        <div class="flex shrink-0 items-center gap-1">
                            <flux:tooltip :content="__('Edit')">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="pencil"
                                    data-edit-btn
                                    :href="route('epics.board.edit', $epic)"
                                    wire:navigate
                                />
                            </flux:tooltip>
                            <flux:tooltip :content="__('Delete')">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    wire:click="confirmDeleteEpic('{{ $epic->id }}')"
                                />
                            </flux:tooltip>
                        </div>
                    </div>

                    {{-- Line 2: epic info --}}
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <flux:badge color="{{ $epic->status->color() }}" size="sm">{{ $epic->status->label() }}</flux:badge>
                        @if ($epic->tdd !== null)
                            <flux:badge color="{{ $epic->tdd ? 'lime' : 'zinc' }}" size="sm">{{ $epic->tdd ? 'TDD' : 'No TDD' }}</flux:badge>
                        @endif
                        @if ($epic->ai_mode)
                            <flux:badge color="purple" size="sm" icon="cpu-chip">AI</flux:badge>
                        @endif
                        @if ($epic->environment)
                            <flux:badge color="zinc" size="sm">{{ $epic->environment }}</flux:badge>
                        @endif
                        <flux:text class="text-sm text-zinc-400">
                            {{ $epic->features_count }} {{ Str::plural('feature', $epic->features_count) }}
                        </flux:text>
                    </div>

                    {{-- Line 3: epic ID (copyable) --}}
                    <div
                        class="mt-1 flex cursor-pointer items-center gap-1 self-start"
                        title="{{ __('Click to copy epic ID') }}"
                        x-data="{ copied: false }"
                        x-on:click.stop="navigator.clipboard.writeText('{{ $epic->id }}'); copied = true; setTimeout(() => copied = false, 1500)"
                    >
                        <flux:icon.key class="size-3 shrink-0 text-zinc-400" />
                        <span class="font-mono text-xs text-zinc-400" x-show="!copied">{{ $epic->id }}</span>
                        <span class="text-xs text-green-500" x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                    </div>

                    {{-- Line 4: repo URL --}}
                    @if ($epic->repository_url)
                        <a
                            href="{{ $epic->repository_url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-1 inline-flex items-center gap-1 break-all text-xs text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400"
                            wire:navigate.prevent
                        >
                            <flux:icon.folder-git-2 class="size-3.5 shrink-0" />
                            {{ $epic->repository_url }}
                        </a>
                    @endif
                </div>
            @empty
                <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 bg-zinc-50 py-20 dark:border-zinc-700 dark:bg-zinc-900/50">
                    <flux:text class="text-base font-medium text-zinc-500 dark:text-zinc-400">{{ __('No epics yet') }}</flux:text>
                    <flux:text class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">
                        {{ __('Create your first epic to start organising work.') }}
                    </flux:text>
                    <div class="mt-5">
                        <flux:modal.trigger name="create-epic">
                            <flux:button variant="primary" icon="plus" size="sm">{{ __('New epic') }}</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>
            @endforelse
        </div>

    {{-- Create Epic Modal --}}
    <flux:modal name="create-epic" :show="$errors->isNotEmpty()" focusable class="md:w-[520px]">
        <form wire:submit="createEpic" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('New epic') }}</flux:heading>
                <flux:subheading>{{ __('An epic is a large body of work broken into features and tasks.') }}</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" autofocus required />
            <flux:textarea wire:model="description" :label="__('Description (optional)')" rows="3" />
            <flux:input wire:model="repositoryUrl" :label="__('Repository URL (optional)')" type="text" placeholder="https://github.com/org/repo or git@github.com:org/repo.git" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:select wire:model="tdd" :label="__('TDD')">
                    <flux:select.option value="">{{ __('Inherit') }}</flux:select.option>
                    <flux:select.option value="1">{{ __('Enabled') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Disabled') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model="environment" :label="__('Environment')">
                    <flux:select.option value="">{{ __('Inherit') }}</flux:select.option>
                    <flux:select.option value="Development">{{ __('Development') }}</flux:select.option>
                    <flux:select.option value="Production">{{ __('Production') }}</flux:select.option>
                    <flux:select.option value="Staging">{{ __('Staging') }}</flux:select.option>
                    <flux:select.option value="Other">{{ __('Other') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:textarea wire:model="aiMode" :label="__('AI mode (optional)')" rows="2" placeholder="{{ __('Describe how AI should behave for this epic...') }}" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create epic') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Epic Modal --}}
    <flux:modal name="delete-epic" class="w-full sm:min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete epic') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This will permanently delete the epic and all its features and tasks. This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteEpic">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
