@props([
    'label',
    'heading' => null,
    'icon' => null,
])

@php $heading ??= $label; @endphp

<div x-data="{ fullscreenLinkOpen: false }">
    <flux:button variant="ghost" :icon="$icon" class="w-full justify-start" @click="fullscreenLinkOpen = true">
        {{ $label }}
    </flux:button>
    <div
        x-show="fullscreenLinkOpen"
        x-cloak
        data-fullscreen-overlay
        class="fixed inset-0 z-50 flex flex-col gap-4 bg-white p-6 dark:bg-zinc-900"
        @keydown.escape.window="fullscreenLinkOpen = false"
    >
        <div class="flex shrink-0 items-center justify-between">
            <flux:heading size="lg">{{ $heading }}</flux:heading>
            <flux:button variant="ghost" size="sm" icon="x-mark" @click="fullscreenLinkOpen = false" />
        </div>
        <div class="flex min-h-0 flex-1 flex-col">
            {{ $slot }}
        </div>
    </div>
</div>
