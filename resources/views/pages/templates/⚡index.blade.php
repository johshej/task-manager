<?php

use App\Models\EpicTemplate;
use App\Models\FeatureTemplate;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Templates')] class extends Component {
    public string $newFeatureTemplateName = '';
    public string $newEpicTemplateName = '';

    public ?string $deletingFeatureTemplateId = null;
    public ?string $deletingEpicTemplateId = null;

    public function createFeatureTemplate(): void
    {
        $this->validate([
            'newFeatureTemplateName' => ['required', 'string', 'max:255'],
        ]);

        $template = FeatureTemplate::create(['name' => $this->newFeatureTemplateName]);

        $this->reset('newFeatureTemplateName');
        $this->modal('create-feature-template')->close();
        unset($this->featureTemplates);
        Flux::toast(variant: 'success', text: 'Feature template created.');
        $this->redirect(route('templates.feature', $template), navigate: true);
    }

    public function confirmDeleteFeatureTemplate(string $featureTemplateId): void
    {
        $this->deletingFeatureTemplateId = $featureTemplateId;
        $this->modal('delete-feature-template')->show();
    }

    public function deleteFeatureTemplate(): void
    {
        FeatureTemplate::findOrFail($this->deletingFeatureTemplateId)->delete();
        $this->deletingFeatureTemplateId = null;
        $this->modal('delete-feature-template')->close();
        unset($this->featureTemplates);
        Flux::toast(variant: 'success', text: 'Feature template deleted.');
    }

    public function createEpicTemplate(): void
    {
        $this->validate([
            'newEpicTemplateName' => ['required', 'string', 'max:255'],
        ]);

        $template = EpicTemplate::create(['name' => $this->newEpicTemplateName]);

        $this->reset('newEpicTemplateName');
        $this->modal('create-epic-template')->close();
        unset($this->epicTemplates);
        Flux::toast(variant: 'success', text: 'Epic template created.');
        $this->redirect(route('templates.epic', $template), navigate: true);
    }

    public function confirmDeleteEpicTemplate(string $epicTemplateId): void
    {
        $this->deletingEpicTemplateId = $epicTemplateId;
        $this->modal('delete-epic-template')->show();
    }

    public function deleteEpicTemplate(): void
    {
        EpicTemplate::findOrFail($this->deletingEpicTemplateId)->delete();
        $this->deletingEpicTemplateId = null;
        $this->modal('delete-epic-template')->close();
        unset($this->epicTemplates);
        Flux::toast(variant: 'success', text: 'Epic template deleted.');
    }

    /** @return Collection<int, FeatureTemplate> */
    #[Computed]
    public function featureTemplates(): Collection
    {
        return FeatureTemplate::withCount('tasks')->orderBy('name')->get();
    }

    /** @return Collection<int, EpicTemplate> */
    #[Computed]
    public function epicTemplates(): Collection
    {
        return EpicTemplate::withCount('epicTemplateFeatures')->orderBy('name')->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-8" data-view="templates-index">
    <div>
        <flux:heading size="xl">{{ __('Templates') }}</flux:heading>
        <flux:subheading>{{ __('Reusable blueprints for features and epics.') }}</flux:subheading>
    </div>

    {{-- Feature templates --}}
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Feature templates') }}</flux:heading>
            <flux:modal.trigger name="create-feature-template" data-shortcut="new-feature-template">
                <flux:button variant="primary" size="sm" icon="plus">{{ __('New feature template') }}</flux:button>
            </flux:modal.trigger>
        </div>

        <div class="space-y-2" data-list="feature-templates">
            @forelse ($this->featureTemplates as $template)
                <div
                    wire:key="feature-template-{{ $template->id }}"
                    data-selectable
                    data-href="{{ route('templates.feature', $template) }}"
                    class="flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <div class="flex items-center gap-2">
                        <a href="{{ route('templates.feature', $template) }}" wire:navigate class="font-semibold hover:text-blue-600 dark:hover:text-blue-400">
                            {{ $template->name }}
                        </a>
                        <flux:text class="text-sm text-zinc-400">
                            {{ $template->tasks_count }} {{ Str::plural('task', $template->tasks_count) }}
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-1">
                        <flux:tooltip :content="__('Edit')">
                            <flux:button variant="ghost" size="sm" icon="pencil" data-edit-btn :href="route('templates.feature', $template)" wire:navigate />
                        </flux:tooltip>
                        <flux:tooltip :content="__('Delete')">
                            <flux:button variant="ghost" size="sm" icon="trash" data-delete-btn wire:click="confirmDeleteFeatureTemplate('{{ $template->id }}')" />
                        </flux:tooltip>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 bg-zinc-50 py-10 dark:border-zinc-700 dark:bg-zinc-900/50">
                    <flux:text class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('No feature templates yet.') }}</flux:text>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Epic templates --}}
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Epic templates') }}</flux:heading>
            <flux:modal.trigger name="create-epic-template" data-shortcut="new-epic-template">
                <flux:button variant="primary" size="sm" icon="plus">{{ __('New epic template') }}</flux:button>
            </flux:modal.trigger>
        </div>

        <div class="space-y-2" data-list="epic-templates">
            @forelse ($this->epicTemplates as $template)
                <div
                    wire:key="epic-template-{{ $template->id }}"
                    data-selectable
                    data-href="{{ route('templates.epic', $template) }}"
                    class="flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <div class="flex items-center gap-2">
                        <a href="{{ route('templates.epic', $template) }}" wire:navigate class="font-semibold hover:text-blue-600 dark:hover:text-blue-400">
                            {{ $template->name }}
                        </a>
                        <flux:text class="text-sm text-zinc-400">
                            {{ $template->epic_template_features_count }} {{ Str::plural('feature', $template->epic_template_features_count) }}
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-1">
                        <flux:tooltip :content="__('Edit')">
                            <flux:button variant="ghost" size="sm" icon="pencil" data-edit-btn :href="route('templates.epic', $template)" wire:navigate />
                        </flux:tooltip>
                        <flux:tooltip :content="__('Delete')">
                            <flux:button variant="ghost" size="sm" icon="trash" data-delete-btn wire:click="confirmDeleteEpicTemplate('{{ $template->id }}')" />
                        </flux:tooltip>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 bg-zinc-50 py-10 dark:border-zinc-700 dark:bg-zinc-900/50">
                    <flux:text class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('No epic templates yet.') }}</flux:text>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Create Feature Template Modal --}}
    <flux:modal name="create-feature-template" :show="$errors->isNotEmpty()" focusable class="md:w-[420px]">
        <form wire:submit="createFeatureTemplate" class="space-y-5"
            @keydown.ctrl.enter.prevent="$wire.createFeatureTemplate()"
            @keydown.meta.enter.prevent="$wire.createFeatureTemplate()"
        >
            <flux:heading size="lg">{{ __('New feature template') }}</flux:heading>
            <flux:input wire:model="newFeatureTemplateName" :label="__('Name')" autofocus required />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:tooltip content="Ctrl+Enter">
                    <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                </flux:tooltip>
            </div>
        </form>
    </flux:modal>

    {{-- Create Epic Template Modal --}}
    <flux:modal name="create-epic-template" :show="$errors->isNotEmpty()" focusable class="md:w-[420px]">
        <form wire:submit="createEpicTemplate" class="space-y-5"
            @keydown.ctrl.enter.prevent="$wire.createEpicTemplate()"
            @keydown.meta.enter.prevent="$wire.createEpicTemplate()"
        >
            <flux:heading size="lg">{{ __('New epic template') }}</flux:heading>
            <flux:input wire:model="newEpicTemplateName" :label="__('Name')" autofocus required />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:tooltip content="Ctrl+Enter">
                    <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                </flux:tooltip>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Feature Template Modal --}}
    <flux:modal name="delete-feature-template" class="w-full sm:min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete feature template') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This will permanently delete the template and its subtasks. This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteFeatureTemplate">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete Epic Template Modal --}}
    <flux:modal name="delete-epic-template" class="w-full sm:min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete epic template') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This will permanently delete the template. Its linked feature templates are not affected.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteEpicTemplate">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
