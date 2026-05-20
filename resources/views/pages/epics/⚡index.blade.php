<?php

use App\Enums\ActorType;
use App\Enums\EpicStatus;
use App\Enums\HistoryAction;
use App\Models\Epic;
use App\Models\EpicHistory;
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

    public ?string $editingEpicId = null;
    public string $editName = '';
    public string $editDescription = '';
    public string $editRepositoryUrl = '';
    public string $editStatus = '';
    public string $editTdd = '';
    public string $editAiMode = '';
    public string $editEnvironment = '';

    public ?string $deletingEpicId = null;

    public string $epicReplyBody = '';

    private function tddNullable(string $value): ?bool
    {
        return $value === '' ? null : (bool) $value;
    }

    private function boolToTddString(?bool $value): string
    {
        return $value === null ? '' : ($value ? '1' : '0');
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
            'status' => EpicStatus::Active,
            'tdd' => $this->tddNullable($this->tdd),
            'ai_mode' => $this->aiMode ?: null,
            'environment' => $this->environment ?: null,
        ]);

        $this->reset('name', 'description', 'repositoryUrl', 'tdd', 'aiMode', 'environment');
        $this->modal('create-epic')->close();
        Flux::toast(variant: 'success', text: 'Epic created.');
    }

    public function editEpic(string $epicId): void
    {
        $epic = Epic::findOrFail($epicId);
        $this->editingEpicId = $epicId;
        $this->editName = $epic->name;
        $this->editDescription = $epic->description ?? '';
        $this->editRepositoryUrl = $epic->repository_url ?? '';
        $this->editStatus = $epic->status->value;
        $this->editTdd = $this->boolToTddString($epic->tdd);
        $this->editAiMode = $epic->ai_mode ?? '';
        $this->editEnvironment = $epic->environment ?? '';
        $this->modal('edit-epic')->show();
    }

    public function updateEpic(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editDescription' => ['nullable', 'string'],
            'editRepositoryUrl' => ['nullable', 'regex:/^(https?:\/\/\S+|git@[^:]+:\S+)$/', 'max:500'],
            'editStatus' => ['required', 'in:' . implode(',', array_column(EpicStatus::cases(), 'value'))],
            'editTdd' => ['nullable', 'in:0,1'],
            'editAiMode' => ['nullable', 'string'],
            'editEnvironment' => ['nullable', 'string', 'max:100'],
        ]);

        Epic::findOrFail($this->editingEpicId)->update([
            'name' => $this->editName,
            'description' => $this->editDescription ?: null,
            'repository_url' => $this->editRepositoryUrl ?: null,
            'status' => $this->editStatus,
            'tdd' => $this->tddNullable($this->editTdd),
            'ai_mode' => $this->editAiMode ?: null,
            'environment' => $this->editEnvironment ?: null,
        ]);

        $this->modal('edit-epic')->close();
        Flux::toast(variant: 'success', text: 'Epic updated.');
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

    public function addEpicReply(): void
    {
        $this->validate(['epicReplyBody' => ['required', 'string', 'max:10000']]);

        EpicHistory::create([
            'epic_id' => $this->editingEpicId,
            'changed_by_user_id' => auth()->id(),
            'actor_type' => ActorType::User,
            'actor_name' => auth()->user()?->name,
            'action' => HistoryAction::Note,
            'body' => $this->epicReplyBody,
        ]);

        $this->epicReplyBody = '';
        unset($this->editingEpic);
    }

    /** @return Collection<int, Epic> */
    #[Computed]
    public function epics(): Collection
    {
        return Epic::withCount('features')->latest()->get();
    }

    #[Computed]
    public function editingEpic(): ?Epic
    {
        if (! $this->editingEpicId) {
            return null;
        }

        return Epic::with([
            'history' => fn ($q) => $q->with('changedByUser', 'changedByToken')->oldest('created_at'),
        ])->find($this->editingEpicId);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Epics') }}</flux:heading>
                <flux:subheading>{{ __('High-level bodies of work broken into features and tasks.') }}</flux:subheading>
            </div>
            <flux:modal.trigger name="create-epic">
                <flux:button variant="primary" icon="plus">{{ __('New epic') }}</flux:button>
            </flux:modal.trigger>
        </div>

        <div class="space-y-3">
            @forelse ($this->epics as $epic)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-zinc-200 bg-white p-4 transition-shadow hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-1 items-start gap-4 min-w-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a
                                    href="{{ route('epics.board', $epic) }}"
                                    wire:navigate
                                    class="font-semibold hover:text-blue-600 dark:hover:text-blue-400"
                                >{{ $epic->name }}</a>
                                <flux:badge color="{{ $epic->status->color() }}" size="sm">{{ $epic->status->label() }}</flux:badge>
                            </div>
                            @if ($epic->description)
                                <flux:text class="mt-1 truncate text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ Str::limit($epic->description, 120) }}
                                </flux:text>
                            @endif
                            @if ($epic->repository_url)
                                <a
                                    href="{{ $epic->repository_url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-1 inline-flex items-center gap-1 text-xs text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 break-all"
                                    wire:navigate.prevent
                                >
                                    <flux:icon.folder-git-2 class="size-3.5" />
                                    {{ $epic->repository_url }}
                                </a>
                            @endif
                        </div>
                        <flux:text class="shrink-0 text-sm text-zinc-400">
                            {{ $epic->features_count }} {{ Str::plural('feature', $epic->features_count) }}
                        </flux:text>
                    </div>
                    <div class="ml-4 flex shrink-0 items-center gap-1">
                        <flux:tooltip :content="__('Open board')">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="arrow-right"
                                :href="route('epics.board', $epic)"
                                wire:navigate
                            />
                        </flux:tooltip>
                        <flux:tooltip :content="__('Edit')">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="pencil"
                                wire:click="editEpic('{{ $epic->id }}')"
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

    {{-- Edit Epic Modal --}}
    <flux:modal name="edit-epic" focusable class="modal-fullscreen">
        <div class="flex h-full flex-col">
            <flux:heading size="lg" class="mb-5 shrink-0">{{ __('Edit epic') }}</flux:heading>

            <div class="flex min-h-0 flex-1 gap-8">
                {{-- Form --}}
                <div class="flex w-full flex-col overflow-y-auto md:w-96 md:shrink-0">
                    <form wire:submit="updateEpic" id="edit-epic-form" class="flex-1 space-y-5">
                        <flux:input wire:model="editName" :label="__('Name')" autofocus required />
                        <flux:textarea wire:model="editDescription" :label="__('Description (optional)')" rows="3" />
                        <flux:input wire:model="editRepositoryUrl" :label="__('Repository URL (optional)')" type="text" placeholder="https://github.com/org/repo or git@github.com:org/repo.git" />

                        <flux:select wire:model="editStatus" :label="__('Status')">
                            @foreach (EpicStatus::cases() as $status)
                                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:select wire:model="editTdd" :label="__('TDD')">
                                <flux:select.option value="">{{ __('Inherit') }}</flux:select.option>
                                <flux:select.option value="1">{{ __('Enabled') }}</flux:select.option>
                                <flux:select.option value="0">{{ __('Disabled') }}</flux:select.option>
                            </flux:select>
                            <flux:select wire:model="editEnvironment" :label="__('Environment')">
                                <flux:select.option value="">{{ __('Inherit') }}</flux:select.option>
                                <flux:select.option value="Development">{{ __('Development') }}</flux:select.option>
                                <flux:select.option value="Production">{{ __('Production') }}</flux:select.option>
                                <flux:select.option value="Staging">{{ __('Staging') }}</flux:select.option>
                                <flux:select.option value="Other">{{ __('Other') }}</flux:select.option>
                            </flux:select>
                        </div>

                        <flux:textarea wire:model="editAiMode" :label="__('AI mode (optional)')" rows="2" placeholder="{{ __('Describe how AI should behave for this epic...') }}" />
                    </form>

                    <div class="mt-5 flex shrink-0 justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" form="edit-epic-form" type="submit">{{ __('Save changes') }}</flux:button>
                    </div>
                </div>

                {{-- Conversation thread --}}
                <div class="hidden min-h-0 flex-1 flex-col border-l border-zinc-200 pl-8 dark:border-zinc-700 md:flex">
                    <flux:heading size="sm" class="mb-3 shrink-0 font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                        {{ __('Thread') }}
                    </flux:heading>

                    <div class="flex-1 space-y-2 overflow-y-auto pb-2">
                        @if ($this->editingEpic)
                            @forelse ($this->editingEpic->history as $entry)
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
                        @endif
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
