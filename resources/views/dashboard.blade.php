<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
        <div>
            <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
            <flux:subheading>{{ __('Welcome back, :name.', ['name' => auth()->user()->name]) }}</flux:subheading>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <a
                href="{{ route('epics') }}"
                wire:navigate
                class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition-shadow hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="flex size-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-950/50">
                    <svg class="size-5 text-blue-600 dark:text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z" />
                    </svg>
                </div>
                <div>
                    <flux:heading size="sm" class="font-semibold group-hover:text-blue-600 dark:group-hover:text-blue-400">
                        {{ __('Epics') }}
                    </flux:heading>
                    <flux:text class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('View and manage epics, features, and tasks.') }}
                    </flux:text>
                </div>
            </a>

            <a
                href="{{ route('profile.edit') }}"
                wire:navigate
                class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition-shadow hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                    <svg class="size-5 text-zinc-600 dark:text-zinc-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div>
                    <flux:heading size="sm" class="font-semibold group-hover:text-zinc-900 dark:group-hover:text-zinc-100">
                        {{ __('Profile') }}
                    </flux:heading>
                    <flux:text class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Update your account settings.') }}
                    </flux:text>
                </div>
            </a>

            <a
                href="{{ route('teams.index') }}"
                wire:navigate
                class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition-shadow hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                    <svg class="size-5 text-zinc-600 dark:text-zinc-400" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                    </svg>
                </div>
                <div>
                    <flux:heading size="sm" class="font-semibold group-hover:text-zinc-900 dark:group-hover:text-zinc-100">
                        {{ __('Teams') }}
                    </flux:heading>
                    <flux:text class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Manage teams and memberships.') }}
                    </flux:text>
                </div>
            </a>
        </div>
    </div>
</x-layouts::app>
