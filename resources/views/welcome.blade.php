<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => __('Welcome')])
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-900">
        <div class="flex min-h-screen flex-col">

            {{-- Header --}}
            <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-2">
                        <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
                            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
                        </div>
                        <span class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Task Manager</span>
                    </div>
                    <nav class="flex items-center gap-3">
                        @auth
                            <a
                                href="{{ route('epics') }}"
                                class="inline-flex items-center rounded-md px-4 py-1.5 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            >
                                Open app
                            </a>
                        @else
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex items-center rounded-md px-4 py-1.5 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            >
                                Log in
                            </a>
                            @if (Route::has('register'))
                                <a
                                    href="{{ route('register') }}"
                                    class="inline-flex items-center rounded-md bg-zinc-900 px-4 py-1.5 text-sm font-medium text-white transition-colors hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                                >
                                    Get started
                                </a>
                            @endif
                        @endauth
                    </nav>
                </div>
            </header>

            {{-- Hero --}}
            <main class="flex flex-1 flex-col items-center justify-center px-6 py-20 text-center">
                <div class="mx-auto max-w-2xl">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-medium text-purple-700 dark:border-purple-800 dark:bg-purple-950/40 dark:text-purple-400">
                        <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 5.323V3a1 1 0 011-1z" />
                        </svg>
                        Human + AI collaboration
                    </div>

                    <h1 class="text-5xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100 sm:text-6xl">
                        Task Manager
                    </h1>
                    <p class="mt-5 text-lg leading-relaxed text-zinc-500 dark:text-zinc-400">
                        Organise work into epics, features, and tasks.
                        Track every change — whether made by a human or an AI agent — with a full audit trail.
                    </p>

                    <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                        @auth
                            <a
                                href="{{ route('epics') }}"
                                class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                            >
                                Go to epics
                                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        @else
                            @if (Route::has('register'))
                                <a
                                    href="{{ route('register') }}"
                                    class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                                >
                                    Get started
                                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            @endif
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-6 py-2.5 text-sm font-semibold text-zinc-700 shadow-sm transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"
                            >
                                Log in
                            </a>
                        @endauth
                    </div>
                </div>

                {{-- Feature highlights --}}
                <div class="mx-auto mt-20 grid max-w-4xl gap-6 sm:grid-cols-3">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-6 text-left dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="mb-3 flex size-9 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-950/50">
                            <svg class="size-5 text-blue-600 dark:text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Epic → Feature → Task</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            Hierarchical work organisation so nothing gets lost.
                        </p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-6 text-left dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="mb-3 flex size-9 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-950/50">
                            <svg class="size-5 text-purple-600 dark:text-purple-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Full audit trail</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            Every change is recorded with who made it and when.
                        </p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-6 text-left dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="mb-3 flex size-9 items-center justify-center rounded-lg bg-green-100 dark:bg-green-950/50">
                            <svg class="size-5 text-green-600 dark:text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">API-first</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            Token-based API lets AI agents work alongside humans seamlessly.
                        </p>
                    </div>
                </div>
            </main>

            <footer class="border-t border-zinc-200 py-6 text-center text-sm text-zinc-400 dark:border-zinc-800 dark:text-zinc-600">
                Task Manager
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
