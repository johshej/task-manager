@props([
    'history',
    'replyModel',
    'sendMethod',
    'showSendToClaude' => true,
])

<div class="flex-1 space-y-2 overflow-y-auto pb-2">
    @forelse ($history as $entry)
        @if ($entry->action === \App\Enums\HistoryAction::Note)
            <div @class([
                'rounded-xl p-4',
                'bg-purple-50 dark:bg-purple-950/30' => $entry->actor_type === \App\Enums\ActorType::Ai,
                'bg-zinc-50 dark:bg-zinc-800/40' => $entry->actor_type !== \App\Enums\ActorType::Ai,
            ])>
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        @if ($entry->actor_type === \App\Enums\ActorType::Ai)
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
                @php $meta = $entry->metadata ?? []; @endphp
                @if (isset($meta['model']) || isset($meta['duration_ms']))
                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-zinc-400">
                        @if (isset($meta['model']))<span>{{ $meta['model'] }}</span>@endif
                        @if (isset($meta['duration_ms']))<span>{{ number_format($meta['duration_ms'] / 1000, 1) }}s</span>@endif
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
</div>

<div class="mt-3 shrink-0 border-t border-zinc-200 pt-4 dark:border-zinc-700">
    <flux:textarea
        wire:model="{{ $replyModel }}"
        :placeholder="__('Add a note...')"
        rows="3"
    />
    <div @class(['mt-2 flex', $showSendToClaude ? 'justify-between' : 'justify-end'])>
        @if ($showSendToClaude)
            <flux:button
                variant="ghost"
                size="sm"
                icon="cpu-chip"
                wire:click="{{ $sendMethod }}(true)"
                x-bind:disabled="! $wire.{{ $replyModel }}.trim()"
            >{{ __('Send to Claude') }}</flux:button>
        @endif
        <flux:button
            variant="primary"
            size="sm"
            wire:click="{{ $sendMethod }}"
            x-bind:disabled="! $wire.{{ $replyModel }}.trim()"
        >{{ __('Send') }}</flux:button>
    </div>
</div>
