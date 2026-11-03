{{-- Selects all or none of the checkboxes of the checklist next to it. Keys: A and N. --}}
<div class="flex items-center gap-1 text-xs">
    <flux:tooltip content="A">
        <button type="button" class="rounded px-1.5 py-0.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
            x-on:click="$el.closest('[data-checklist-head]').parentElement.querySelectorAll('[data-checklist] input[type=checkbox]').forEach((box) => { if (!box.checked) box.click(); })">{{ __('All') }}</button>
    </flux:tooltip>
    <flux:tooltip content="N">
        <button type="button" class="rounded px-1.5 py-0.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
            x-on:click="$el.closest('[data-checklist-head]').parentElement.querySelectorAll('[data-checklist] input[type=checkbox]').forEach((box) => { if (box.checked) box.click(); })">{{ __('None') }}</button>
    </flux:tooltip>
</div>
