<?php

use App\Models\Epic;
use App\Models\TestMatrix;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Test matrices')] class extends Component {
    public Epic $epic;

    public string $name = '';
    public string $description = '';
    public ?string $deletingMatrixId = null;

    public function mount(Epic $epic): void
    {
        $this->epic = $epic;
    }

    public function createMatrix(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $matrix = TestMatrix::create([
            'epic_id' => $this->epic->id,
            'name' => $this->name,
            'description' => $this->description ?: null,
        ]);

        $this->reset('name', 'description');
        $this->modal('create-test-matrix')->close();
        Flux::toast(variant: 'success', text: 'Test matrix created.');

        $this->redirect(route('test-matrices.show', [$this->epic, $matrix]), navigate: true);
    }

    public function confirmDeleteMatrix(string $matrixId): void
    {
        $this->deletingMatrixId = $matrixId;
        $this->modal('delete-test-matrix')->show();
    }

    public function deleteMatrix(): void
    {
        $this->epic->testMatrices()->findOrFail($this->deletingMatrixId)->delete();
        $this->deletingMatrixId = null;
        $this->modal('delete-test-matrix')->close();
        unset($this->matrices);
        Flux::toast(variant: 'success', text: 'Test matrix deleted.');
    }

    /** @return Collection<int, TestMatrix> */
    #[Computed]
    public function matrices(): Collection
    {
        return $this->epic->testMatrices()->withCount(['scenarios', 'contexts'])->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6" data-view="test-matrices-index">
    <div class="flex flex-col gap-1">
        <div class="flex items-center justify-between gap-2">
            <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('epics.board', $epic)" wire:navigate />
            <flux:modal.trigger name="create-test-matrix" data-shortcut="new-test-matrix">
                <flux:button variant="primary" size="sm" icon="plus">{{ __('New test matrix') }}</flux:button>
            </flux:modal.trigger>
        </div>
        <flux:heading size="xl">{{ __('Test matrices') }}</flux:heading>
        <flux:subheading>{{ $epic->name }} — {{ __('scenarios as rows, contexts as columns, a result in every cell.') }}</flux:subheading>
    </div>

    <div class="space-y-3" data-list="test-matrices">
        @forelse ($this->matrices as $matrix)
            @php
                $summary = $matrix->summary();
            @endphp
            <div wire:key="matrix-{{ $matrix->id }}" class="flex items-start justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-selectable data-href="{{ route('test-matrices.show', [$epic, $matrix]) }}" data-matrix="{{ $matrix->id }}">
                <div class="min-w-0 space-y-1">
                    <a href="{{ route('test-matrices.show', [$epic, $matrix]) }}" wire:navigate class="text-base font-semibold hover:underline">{{ $matrix->name }}</a>
                    @if ($matrix->description)
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $matrix->description }}</flux:text>
                    @endif
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <flux:badge size="sm" color="zinc">{{ trans_choice(':count scenario|:count scenarios', $matrix->scenarios_count) }}</flux:badge>
                        <flux:badge size="sm" color="zinc">{{ trans_choice(':count context|:count contexts', $matrix->contexts_count) }}</flux:badge>
                        <flux:badge size="sm" color="green">{{ __('Passed') }} · {{ $summary['passed'] }}</flux:badge>
                        <flux:badge size="sm" color="red">{{ __('Failed') }} · {{ $summary['failed'] }}</flux:badge>
                        <flux:badge size="sm" color="zinc">{{ __('Untested') }} · {{ $summary['untested'] }}</flux:badge>
                    </div>
                </div>
                <flux:button variant="ghost" size="sm" icon="trash" :aria-label="__('Delete test matrix')" data-delete-btn wire:click="confirmDeleteMatrix('{{ $matrix->id }}')" />
            </div>
        @empty
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 bg-zinc-50 py-20 dark:border-zinc-700 dark:bg-zinc-900/50">
                <flux:text class="text-base font-medium text-zinc-500 dark:text-zinc-400">{{ __('No test matrices yet') }}</flux:text>
                <flux:text class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Create one to test scenarios across roles and devices.') }}</flux:text>
            </div>
        @endforelse
    </div>

    <flux:modal name="create-test-matrix" :show="$errors->isNotEmpty()" focusable class="md:w-[480px]">
        <form wire:submit="createMatrix" @keydown.ctrl.enter.prevent="$wire.createMatrix()" class="space-y-5">
            <flux:heading size="lg">{{ __('New test matrix') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" autofocus required />
            <flux:textarea wire:model="description" :label="__('Description (optional)')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create test matrix') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-test-matrix" class="w-full sm:min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete test matrix') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This permanently deletes the matrix with its contexts, scenarios and results. The tasks made from its comments stay.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteMatrix">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
