<?php

use App\Models\EpicTemplate;
use App\Models\EpicTemplateFeature;
use App\Models\FeatureTemplate;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Epic Template')] class extends Component {
    public EpicTemplate $epicTemplate;

    public string $editName = '';
    public string $editDescription = '';
    public string $editRepositoryUrl = '';
    public string $editTdd = '';
    public string $editAiMode = '';
    public string $editEnvironment = '';

    public ?string $removingEpicTemplateFeatureId = null;

    public function mount(EpicTemplate $epicTemplate): void
    {
        $this->epicTemplate = $epicTemplate;
        $this->editName = $epicTemplate->name;
        $this->editDescription = $epicTemplate->description ?? '';
        $this->editRepositoryUrl = $epicTemplate->repository_url ?? '';
        $this->editTdd = $this->boolToTddString($epicTemplate->tdd);
        $this->editAiMode = $epicTemplate->ai_mode ?? '';
        $this->editEnvironment = $epicTemplate->environment ?? '';
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

    public function updateEpicTemplate(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editDescription' => ['nullable', 'string'],
            'editRepositoryUrl' => ['nullable', 'regex:/^(https?:\/\/\S+|git@[^:]+:\S+)$/', 'max:500'],
            'editTdd' => ['nullable', 'in:0,1'],
            'editAiMode' => ['nullable', 'string'],
            'editEnvironment' => ['nullable', 'string', 'max:100'],
        ]);

        $this->epicTemplate->update([
            'name' => $this->editName,
            'description' => $this->editDescription ?: null,
            'repository_url' => $this->editRepositoryUrl ?: null,
            'tdd' => $this->tddNullable($this->editTdd),
            'ai_mode' => $this->editAiMode ?: null,
            'environment' => $this->editEnvironment ?: null,
        ]);

        Flux::toast(variant: 'success', text: 'Epic template updated.');
    }

    public function addFeatureTemplate(string $featureTemplateId): void
    {
        $this->epicTemplate->linkFeatureTemplate($featureTemplateId);

        unset($this->epicTemplateFeatures, $this->availableFeatureTemplates);
        Flux::toast(variant: 'success', text: 'Feature template added.');
    }

    public function pasteFeatureTemplate(string $featureTemplateId, string $mode, ?string $sourceLinkId = null): void
    {
        if (! FeatureTemplate::find($featureTemplateId)) {
            Flux::toast(variant: 'danger', text: 'That feature template no longer exists.');
            $this->dispatch('feature-template-pasted', mode: $mode);

            return;
        }

        $this->epicTemplate->linkFeatureTemplate($featureTemplateId);

        if ($mode === 'cut' && $sourceLinkId) {
            EpicTemplateFeature::where('id', $sourceLinkId)
                ->where('epic_template_id', '!=', $this->epicTemplate->id)
                ->delete();
        }

        unset($this->epicTemplateFeatures, $this->availableFeatureTemplates);
        Flux::toast(variant: 'success', text: 'Feature template added.');
        $this->dispatch('feature-template-pasted', mode: $mode);
    }

    public function confirmRemoveFeatureTemplate(string $epicTemplateFeatureId): void
    {
        $this->removingEpicTemplateFeatureId = $epicTemplateFeatureId;
        $this->modal('remove-epic-template-feature')->show();
    }

    public function removeFeatureTemplate(): void
    {
        EpicTemplateFeature::findOrFail($this->removingEpicTemplateFeatureId)->delete();
        $this->removingEpicTemplateFeatureId = null;
        $this->modal('remove-epic-template-feature')->close();
        unset($this->epicTemplateFeatures, $this->availableFeatureTemplates);
        Flux::toast(variant: 'success', text: 'Feature template removed.');
    }

    public function sortEpicTemplateFeatures(string $epicTemplateFeatureId, int $position): void
    {
        $ids = EpicTemplateFeature::where('epic_template_id', $this->epicTemplate->id)
            ->where('id', '!=', $epicTemplateFeatureId)
            ->orderBy('order_index')
            ->pluck('id')
            ->toArray();

        array_splice($ids, $position, 0, [$epicTemplateFeatureId]);

        foreach ($ids as $idx => $id) {
            EpicTemplateFeature::where('id', $id)->update(['order_index' => $idx]);
        }

        unset($this->epicTemplateFeatures);
    }

    /** @return Collection<int, EpicTemplateFeature> */
    #[Computed]
    public function epicTemplateFeatures(): Collection
    {
        return $this->epicTemplate->epicTemplateFeatures()->with('featureTemplate')->orderBy('order_index')->get();
    }

    /** @return Collection<int, FeatureTemplate> */
    #[Computed]
    public function availableFeatureTemplates(): Collection
    {
        $linkedIds = $this->epicTemplate->epicTemplateFeatures()->pluck('feature_template_id');

        return FeatureTemplate::whereNotIn('id', $linkedIds)->orderBy('name')->get();
    }
}; ?>

<div class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6" data-view="epic-template">
    <div class="flex items-center gap-2">
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('templates')" wire:navigate />
        <flux:heading size="xl">{{ __('Epic template') }}</flux:heading>
    </div>

    <form wire:submit="updateEpicTemplate" class="space-y-5 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"
        @keydown.ctrl.enter.prevent="$wire.updateEpicTemplate()"
        @keydown.meta.enter.prevent="$wire.updateEpicTemplate()"
    >
        <flux:input wire:model="editName" :label="__('Name')" required />
        <flux:textarea wire:model="editDescription" :label="__('Description (optional)')" rows="3" />
        <flux:input wire:model="editRepositoryUrl" :label="__('Repository URL (optional)')" type="text" placeholder="https://github.com/org/repo or git@github.com:org/repo.git" />

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
            <flux:tooltip content="Ctrl+Enter">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </flux:tooltip>
        </div>
    </form>

    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Feature templates') }}</flux:heading>
            <div class="flex items-center gap-1"
                x-data="{
                    clip: JSON.parse(localStorage.getItem('templateClipboard') || 'null'),
                    refresh() { this.clip = JSON.parse(localStorage.getItem('templateClipboard') || 'null'); },
                }"
                x-on:livewire:navigated.window="refresh()"
                x-on:feature-template-pasted.window="if ($event.detail.mode === 'cut') { localStorage.removeItem('templateClipboard'); refresh(); }"
            >
                <template x-if="clip">
                    <div class="flex items-center gap-1">
                        <flux:tooltip content="V">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="clipboard"
                                data-shortcut="paste-feature-template"
                                x-on:click="$wire.pasteFeatureTemplate(clip.featureTemplateId, clip.mode, clip.sourceLinkId ?? null)"
                            ><span x-text="'{{ __('Paste') }}: ' + clip.featureTemplateName"></span></flux:button>
                        </flux:tooltip>
                        <flux:tooltip :content="__('Clear clipboard')">
                            <flux:button variant="ghost" size="sm" icon="x-mark" x-on:click="localStorage.removeItem('templateClipboard'); clip = null" />
                        </flux:tooltip>
                    </div>
                </template>
                <flux:tooltip content="+ / N">
                    <x-template-picker
                        :templates="$this->availableFeatureTemplates"
                        select-method="addFeatureTemplate"
                        :trigger-label="__('Add feature template')"
                        data-shortcut="add-feature-template"
                    />
                </flux:tooltip>
            </div>
        </div>

        <ul
            wire:sort="sortEpicTemplateFeatures"
            wire:sort:config="{ delay: 200, delayOnTouchOnly: true }"
            class="list-none space-y-2"
        >
            @forelse ($this->epicTemplateFeatures as $link)
                <li
                    wire:key="epic-template-feature-{{ $link->id }}"
                    wire:sort:item="{{ $link->id }}"
                    data-selectable
                    data-href="{{ route('templates.feature', $link->featureTemplate) }}"
                    data-link-id="{{ $link->id }}"
                    data-feature-template-id="{{ $link->featureTemplate->id }}"
                    class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"
                    x-data="{ isPendingCut: false }"
                    x-init="
                        const clip = JSON.parse(localStorage.getItem('templateClipboard') || 'null');
                        isPendingCut = clip?.mode === 'cut' && clip?.sourceLinkId === '{{ $link->id }}';
                    "
                    :class="{ 'opacity-50': isPendingCut }"
                >
                    <button type="button" wire:sort:handle class="block shrink-0 cursor-grab appearance-none border-0 bg-transparent p-0 text-zinc-300 hover:text-zinc-500 dark:hover:text-zinc-400">
                        <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
                            <circle cx="5" cy="4" r="1.5"/><circle cx="11" cy="4" r="1.5"/>
                            <circle cx="5" cy="8" r="1.5"/><circle cx="11" cy="8" r="1.5"/>
                            <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                        </svg>
                    </button>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('templates.feature', $link->featureTemplate) }}" wire:navigate class="truncate text-sm font-medium hover:text-blue-600 dark:hover:text-blue-400">
                            {{ $link->featureTemplate->name }}
                        </a>
                    </div>
                    <div class="flex shrink-0 items-center gap-1" x-data="{ copiedFlash: false, cutFlash: false }">
                        <span x-show="copiedFlash" x-cloak class="text-xs text-green-500">{{ __('Copied!') }}</span>
                        <span x-show="cutFlash" x-cloak class="text-xs text-green-500">{{ __('Cut!') }}</span>
                        <flux:tooltip content="C">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="clipboard-document"
                                data-copy-btn
                                x-show="!copiedFlash && !cutFlash"
                                x-on:click="localStorage.setItem('templateClipboard', JSON.stringify({featureTemplateId: '{{ $link->featureTemplate->id }}', featureTemplateName: {{ Illuminate\Support\Js::from($link->featureTemplate->name) }}, mode: 'copy'})); copiedFlash = true; setTimeout(() => copiedFlash = false, 1200)"
                            />
                        </flux:tooltip>
                        <flux:tooltip content="X">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="scissors"
                                data-cut-btn
                                x-show="!copiedFlash && !cutFlash"
                                x-on:click="localStorage.setItem('templateClipboard', JSON.stringify({featureTemplateId: '{{ $link->featureTemplate->id }}', featureTemplateName: {{ Illuminate\Support\Js::from($link->featureTemplate->name) }}, mode: 'cut', sourceEpicTemplateId: '{{ $epicTemplate->id }}', sourceLinkId: '{{ $link->id }}'})); isPendingCut = true; cutFlash = true; setTimeout(() => cutFlash = false, 1200)"
                            />
                        </flux:tooltip>
                        <flux:tooltip content="{{ __('Delete (Delete)') }}">
                            <flux:button variant="ghost" size="sm" icon="x-mark" data-delete-btn wire:click="confirmRemoveFeatureTemplate('{{ $link->id }}')" />
                        </flux:tooltip>
                    </div>
                </li>
            @empty
                <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 bg-zinc-50 py-10 dark:border-zinc-700 dark:bg-zinc-900/50">
                    <flux:text class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('No feature templates added yet.') }}</flux:text>
                </div>
            @endforelse
        </ul>
    </div>

    {{-- Remove Feature Template Modal --}}
    <flux:modal name="remove-epic-template-feature" class="w-full sm:min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Remove feature template') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This only removes it from this epic template - the feature template itself is not deleted.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="removeFeatureTemplate">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
