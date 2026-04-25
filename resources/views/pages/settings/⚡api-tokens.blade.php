<?php

use App\Models\ApiToken;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $newTokenName = '';
    public string $createdTokenPlainText = '';

    public function createToken(): void
    {
        $this->validate(['newTokenName' => ['required', 'string', 'max:255']]);

        $newToken = auth()->user()->createApiToken($this->newTokenName);
        $this->createdTokenPlainText = $newToken->plainTextToken;
        $this->reset('newTokenName');

        unset($this->tokens);

        $this->modal('create-api-token')->close();
        Flux::toast(variant: 'success', text: __('Token created.'));
    }

    public function revokeToken(string $tokenId): void
    {
        ApiToken::where('id', $tokenId)
            ->where('tokenable_id', auth()->id())
            ->where('tokenable_type', auth()->user()::class)
            ->firstOrFail()
            ->delete();

        $this->createdTokenPlainText = '';

        unset($this->tokens);

        Flux::toast(variant: 'success', text: __('Token revoked.'));
    }

    /** @return Collection<int, ApiToken> */
    #[Computed]
    public function tokens(): Collection
    {
        return ApiToken::where('tokenable_id', auth()->id())
            ->where('tokenable_type', auth()->user()::class)
            ->latest()
            ->get();
    }
}; ?>

<section class="mt-12">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('API tokens') }}</flux:heading>
            <flux:subheading>{{ __('Manage tokens for API access and AI agent integrations.') }}</flux:subheading>
        </div>
        <flux:modal.trigger name="create-api-token">
            <flux:button variant="primary" icon="plus" size="sm">{{ __('New token') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($createdTokenPlainText)
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
            <flux:text class="mb-2 text-sm font-medium text-green-800 dark:text-green-300">
                {{ __('Token created — copy it now, it will not be shown again.') }}
            </flux:text>
            <div class="flex items-center gap-2">
                <flux:input value="{{ $createdTokenPlainText }}" readonly class="font-mono text-xs" />
                <flux:button size="sm" wire:click="$set('createdTokenPlainText', '')">{{ __('Done') }}</flux:button>
            </div>
        </div>
    @endif

    <div class="mt-4 space-y-2">
        @forelse ($this->tokens as $token)
            <div class="flex items-center justify-between rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <div>
                    <flux:text class="font-medium">{{ $token->name }}</flux:text>
                    <flux:text class="text-xs text-zinc-400">
                        {{ __('Created') }} {{ $token->created_at->diffForHumans() }}
                        @if ($token->last_used_at)
                            &middot; {{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}
                        @else
                            &middot; {{ __('Never used') }}
                        @endif
                    </flux:text>
                </div>
                <flux:button variant="danger" size="sm" wire:click="revokeToken('{{ $token->id }}')">
                    {{ __('Revoke') }}
                </flux:button>
            </div>
        @empty
            <flux:text class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('No tokens yet.') }}</flux:text>
        @endforelse
    </div>

    <flux:modal name="create-api-token" class="md:w-96" focusable>
        <form wire:submit="createToken" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('New token') }}</flux:heading>
                <flux:subheading>{{ __('Give your token a name to identify where it is used.') }}</flux:subheading>
            </div>

            <flux:input
                wire:model="newTokenName"
                :label="__('Token name')"
                placeholder="{{ __('e.g. CI/CD, Claude agent') }}"
                autofocus
                required
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create token') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
