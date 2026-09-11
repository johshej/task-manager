@props([
    'templates',
    'selectMethod',
    'triggerLabel',
])

<div x-data="{ templatePickerOpen: false, templatePickerSearch: '' }" class="relative">
    <flux:button
        type="button"
        variant="ghost"
        size="sm"
        icon="document-duplicate"
        @click="templatePickerOpen = !templatePickerOpen; templatePickerSearch = ''"
        {{ $attributes }}
    >
        {{ $triggerLabel }}
    </flux:button>

    <div
        x-show="templatePickerOpen"
        x-cloak
        @click.outside="templatePickerOpen = false"
        @keydown.escape.stop="templatePickerOpen = false"
        class="absolute start-0 top-full z-20 mt-1 w-72 rounded-lg border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
    >
        @if ($templates->isEmpty())
            <p class="px-2 py-1.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No templates yet.') }}</p>
        @else
            <input
                type="text"
                x-model="templatePickerSearch"
                placeholder="{{ __('Search templates...') }}"
                class="mb-2 w-full rounded-md border border-zinc-200 bg-white px-2 py-1.5 text-sm focus:outline-hidden focus:ring-2 focus:ring-accent dark:border-zinc-700 dark:bg-zinc-900"
            />
            <div class="max-h-56 overflow-y-auto">
                @foreach ($templates as $template)
                    <button
                        type="button"
                        x-show="{{ Illuminate\Support\Js::from(Illuminate\Support\Str::lower($template->name)) }}.includes(templatePickerSearch.toLowerCase())"
                        wire:click="{{ $selectMethod }}('{{ $template->id }}')"
                        @click="templatePickerOpen = false"
                        class="block w-full truncate rounded-md px-2 py-1.5 text-start text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                    >{{ $template->name }}</button>
                @endforeach
            </div>
        @endif
    </div>
</div>
