@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

<x-layout>
    <x-slot:title>System Credits</x-slot:title>

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 py-6">
        {{-- Hero --}}
        <header class="rounded-2xl border border-base-200 bg-base-100 p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="max-w-2xl space-y-3">
                    <span class="badge badge-outline badge-sm gap-1">
                        <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                        The people behind Bleep
                    </span>
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">System credits</h1>
                    <p class="text-sm leading-relaxed text-base-content/70 sm:text-base">
                        Bleep is a small, open project built with a lot of curiosity. This page is kept off the
                        navigation on purpose and exists to thank the people, teams, and open-source projects
                        that make it possible.
                    </p>
                </div>

                <a href="{{ $projectUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="btn btn-neutral btn-sm shrink-0 gap-2 self-start">
                    <i data-lucide="github" class="h-4 w-4"></i>
                    View the project
                </a>
            </div>
        </header>

        {{-- Maintainer --}}
        <section aria-labelledby="maintainer-heading" class="space-y-3">
            <h2 id="maintainer-heading" class="px-1 text-xs font-semibold uppercase tracking-widest text-base-content/50">
                Maintainer
            </h2>

            <article class="flex flex-col gap-4 rounded-2xl border border-base-200 bg-base-100 p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-base-200 text-xl font-bold uppercase">
                        {{ substr($maintainer['name'], 0, 1) }}
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">{{ $maintainer['name'] }}</h3>
                        <p class="text-sm text-base-content/60">{{ $maintainer['role'] }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ $maintainer['github'] }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn btn-ghost btn-sm gap-2 border border-base-300">
                        <i data-lucide="github" class="h-4 w-4"></i>
                        {{ '@'.$maintainer['name'] }}
                    </a>
                    <a href="{{ $projectUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn btn-ghost btn-sm gap-2 border border-base-300">
                        <i data-lucide="external-link" class="h-4 w-4"></i>
                        Bleep on GitHub
                    </a>
                </div>
            </article>
        </section>

        {{-- Purpose --}}
        <section aria-labelledby="purpose-heading" class="space-y-3">
            <h2 id="purpose-heading" class="px-1 text-xs font-semibold uppercase tracking-widest text-base-content/50">
                Why Bleep exists
            </h2>

            <div class="grid gap-4 md:grid-cols-2">
                <article class="rounded-2xl border border-base-200 bg-base-100 p-5 shadow-sm">
                    <div class="mb-3 flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i data-lucide="book-open" class="h-5 w-5"></i>
                        </span>
                        <h3 class="font-semibold">A place to learn Laravel</h3>
                    </div>
                    <p class="text-sm leading-relaxed text-base-content/70">
                        Bleep is first a learning project: a real application for exploring Laravel routing,
                        Eloquent, queues, broadcasting, testing, and the unglamorous details that tutorials
                        usually skip.
                    </p>
                </article>

                <article class="rounded-2xl border border-base-200 bg-base-100 p-5 shadow-sm">
                    <div class="mb-3 flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-secondary/10 text-secondary">
                            <i data-lucide="git-branch" class="h-5 w-5"></i>
                        </span>
                        <h3 class="font-semibold">A Chirper continuation</h3>
                    </div>
                    <p class="text-sm leading-relaxed text-base-content/70">
                        It began as a fork of Laravel Chirper and kept growing from there, adding profiles,
                        follows, media, messaging, moderation, and the features a small community actually asks
                        for.
                    </p>
                </article>

                <article class="rounded-2xl border border-base-200 bg-base-100 p-5 shadow-sm">
                    <div class="mb-3 flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-accent/10 text-accent">
                            <i data-lucide="brain" class="h-5 w-5"></i>
                        </span>
                        <h3 class="font-semibold">Learning AI in practice</h3>
                    </div>
                    <p class="text-sm leading-relaxed text-base-content/70">
                        Bleep is also a platform for learning how to use AI in modern software development:
                        planning, building, reviewing, and testing real code while still understanding what
                        ships.
                    </p>
                </article>

                <article class="rounded-2xl border border-base-200 bg-base-100 p-5 shadow-sm">
                    <div class="mb-3 flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-success/10 text-success">
                            <i data-lucide="heart-handshake" class="h-5 w-5"></i>
                        </span>
                        <h3 class="font-semibold">A space for friends and family</h3>
                    </div>
                    <p class="text-sm leading-relaxed text-base-content/70">
                        At its heart, Bleep was meant to be a digital space for friends and my father to share
                        thoughts, say how they feel, and be heard. That is why anonymous posting exists.
                    </p>
                </article>

                <article class="rounded-2xl border border-base-200 bg-base-100 p-5 shadow-sm md:col-span-2">
                    <div class="mb-3 flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-info/10 text-info">
                            <i data-lucide="palette" class="h-5 w-5"></i>
                        </span>
                        <h3 class="font-semibold">A smoother experience</h3>
                    </div>
                    <p class="text-sm leading-relaxed text-base-content/70">
                        Bleep is an experiment in UX-centred social software: smooth, clean interfaces,
                        customizable themes and navigation, and personal preferences that let the product get
                        out of the way.
                    </p>
                </article>
            </div>
        </section>

        {{-- Contributors and teams --}}
        <section aria-labelledby="contributors-heading" class="space-y-3">
            <h2 id="contributors-heading" class="px-1 text-xs font-semibold uppercase tracking-widest text-base-content/50">
                Contributors & teams
            </h2>

            @if (count($teams) > 0 || count($contributors) > 0)
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($teams as $team)
                        <article class="rounded-2xl border border-base-200 bg-base-100 p-5 shadow-sm">
                            <h3 class="font-semibold">{{ $team['name'] }}</h3>
                            <p class="mt-1 text-sm text-base-content/60">{{ implode(', ', $team['members']) }}</p>
                        </article>
                    @endforeach

                    @foreach ($contributors as $contributor)
                        <article class="flex items-center gap-3 rounded-2xl border border-base-200 bg-base-100 p-5 shadow-sm">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-base-200 font-semibold uppercase">
                                {{ substr($contributor['name'], 0, 1) }}
                            </span>
                            <div>
                                <h3 class="font-semibold">{{ $contributor['name'] }}</h3>
                                <p class="text-sm text-base-content/60">{{ $contributor['role'] }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-base-300 bg-base-100/60 p-6 text-center">
                    <i data-lucide="users" class="mx-auto mb-3 h-8 w-8 text-base-content/30"></i>
                    <p class="text-sm text-base-content/60">
                        No other contributors are credited yet. This page will grow as people help shape Bleep.
                    </p>
                </div>
            @endif
        </section>

        {{-- Built with --}}
        <section aria-labelledby="built-with-heading" class="space-y-3">
            <h2 id="built-with-heading" class="px-1 text-xs font-semibold uppercase tracking-widest text-base-content/50">
                Built with
            </h2>

            <div class="rounded-2xl border border-base-200 bg-base-100 p-5 shadow-sm">
                <p class="mb-4 text-sm text-base-content/70">
                    Thanks to the teams and communities behind the open-source tools that power Bleep.
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($builtWith as $tool)
                        <a href="{{ $tool['url'] }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="badge badge-outline gap-1 py-3 hover:border-primary hover:text-primary">
                            {{ $tool['name'] }}
                            <i data-lucide="external-link" class="h-3 w-3"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Inspiration --}}
        <section aria-labelledby="inspiration-heading" class="space-y-3">
            <h2 id="inspiration-heading" class="px-1 text-xs font-semibold uppercase tracking-widest text-base-content/50">
                Inspiration
            </h2>

            <article class="flex flex-col gap-3 rounded-2xl border border-base-200 bg-base-100 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-base-200">
                        <i data-lucide="quote" class="h-5 w-5"></i>
                    </span>
                    <div>
                        <h3 class="font-semibold">{{ $inspiration['name'] }}</h3>
                        <p class="text-sm text-base-content/60">{{ $inspiration['note'] }}</p>
                    </div>
                </div>
                <a href="{{ $inspiration['url'] }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="btn btn-ghost btn-sm shrink-0 gap-2 self-start border border-base-300 sm:self-auto">
                    Visit the Bootcamp
                    <i data-lucide="external-link" class="h-4 w-4"></i>
                </a>
            </article>
        </section>

        <footer class="pb-4 text-center text-xs text-base-content/50">
            This page is intentionally unlisted. Spotted a missing credit?
            <a href="{{ $projectUrl }}/issues" target="_blank" rel="noopener noreferrer" class="link link-hover">Open an issue on GitHub</a>.
        </footer>
    </div>
</x-layout>
