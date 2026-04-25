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

    public ?string $editingEpicId = null;
    public string $editName = '';
    public string $editDescription = '';
    public string $editRepositoryUrl = '';
    public string $editStatus = '';
    public string $editTdd = '';
    public string $editAiMode = '';
    public string $editEnvironment = '';

    public ?string $deletingEpicId = null;

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

    /** @return Collection<int, Epic> */
    #[Computed]
    public function epics(): Collection
    {
        return Epic::withCount('features')->latest()->get();
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
                <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 transition-shadow hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
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
                                    class="mt-1 inline-flex items-center gap-1 text-xs text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400"
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

            <div class="grid grid-cols-2 gap-4">
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
    <flux:modal name="edit-epic" focusable class="md:w-[520px]">
        <form wire:submit="updateEpic" class="space-y-5">
            <flux:heading size="lg">{{ __('Edit epic') }}</flux:heading>

            <flux:input wire:model="editName" :label="__('Name')" autofocus required />
            <flux:textarea wire:model="editDescription" :label="__('Description (optional)')" rows="3" />
            <flux:input wire:model="editRepositoryUrl" :label="__('Repository URL (optional)')" type="text" placeholder="https://github.com/org/repo or git@github.com:org/repo.git" />

            <flux:select wire:model="editStatus" :label="__('Status')">
                @foreach (EpicStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-2 gap-4">
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

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Epic Modal --}}
    <flux:modal name="delete-epic" class="min-w-[22rem]">
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
