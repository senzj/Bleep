<x-settings.layout>
    <div class="mb-5">
        <h1 class="text-2xl font-bold mb-2">System</h1>
        <p class="text-sm text-base-content/70">
            Release notes and current system status for Bleep.
        </p>
    </div>

    {{-- Current release --}}
    <div class="mb-5 rounded-xl border border-primary/30 bg-primary/5 p-4 sm:p-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge badge-primary font-semibold">v{{ $changelog['current'] }}</span>
            <span class="text-sm font-semibold">Current release</span>
            @if (! empty($changelog['releases'][0]['date']))
                <span class="text-xs text-base-content/60">
                    {{ \Illuminate\Support\Carbon::parse($changelog['releases'][0]['date'])->format('j F Y') }}
                </span>
            @endif
        </div>
        @if ($changelog['releases'][0] ?? null)
            <h2 class="mt-2 text-lg font-semibold">{{ $changelog['releases'][0]['title'] }}</h2>
            <p class="mt-1 text-sm text-base-content/70">{{ $changelog['releases'][0]['summary'] ?? '' }}</p>
        @endif
    </div>

    {{-- Release history --}}
    <div class="space-y-5">
        @forelse ($changelog['releases'] as $release)
            @php
                $groups = [
                    'added' => ['label' => 'Added', 'icon' => 'plus-circle', 'class' => 'text-success'],
                    'changed' => ['label' => 'Changed', 'icon' => 'refresh-cw', 'class' => 'text-info'],
                    'updated' => ['label' => 'Updated', 'icon' => 'arrow-up-circle', 'class' => 'text-primary'],
                    'optimized' => ['label' => 'Optimized', 'icon' => 'zap', 'class' => 'text-warning'],
                    'removed' => ['label' => 'Removed', 'icon' => 'minus-circle', 'class' => 'text-error'],
                ];
            @endphp

            <article class="rounded-xl border border-base-200 bg-base-200/40 p-4 sm:p-5">
                <header class="flex flex-wrap items-center gap-2 border-b border-base-300/60 pb-3">
                    <span class="badge badge-neutral font-mono text-xs">v{{ $release['version'] }}</span>
                    <h2 class="text-base font-semibold">{{ $release['title'] }}</h2>
                    @if (! empty($release['date']))
                        <span class="ml-auto text-xs text-base-content/60">
                            {{ \Illuminate\Support\Carbon::parse($release['date'])->format('j M Y') }}
                        </span>
                    @endif
                </header>

                @if (! empty($release['summary']))
                    <p class="mt-3 text-sm text-base-content/70">{{ $release['summary'] }}</p>
                @endif

                <div class="mt-4 space-y-4">
                    @foreach ($groups as $key => $group)
                        @php($items = $release['changes'][$key] ?? [])
                        @if (! empty($items))
                            <section>
                                <h3 class="flex items-center gap-2 text-sm font-semibold {{ $group['class'] }}">
                                    <i data-lucide="{{ $group['icon'] }}" class="h-4 w-4" aria-hidden="true"></i>
                                    {{ $group['label'] }}
                                </h3>
                                <ul class="mt-2 space-y-1.5 pl-1">
                                    @foreach ($items as $item)
                                        <li class="flex gap-2 text-sm text-base-content/80">
                                            <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-base-content/40" aria-hidden="true"></span>
                                            <span>{{ $item }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    @endforeach
                </div>
            </article>
        @empty
            <p class="rounded-lg border border-base-200 bg-base-200/40 p-4 text-sm text-base-content/70">
                No release notes have been published yet.
            </p>
        @endforelse
    </div>
</x-settings.layout>
