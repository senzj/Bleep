<x-admin.layout>
    @php
        $statusBadge = fn (string $status) => match ($status) {
            'ok' => 'badge-success',
            'warning' => 'badge-warning',
            'error' => 'badge-error',
            default => 'badge-info',
        };

        $allItems = collect($groups)->flatMap(fn (array $group) => $group['items']);
        $errorCount = $allItems->where('status', 'error')->count();
        $warningCount = $allItems->where('status', 'warning')->count();
    @endphp

    <div class="mb-8 space-y-6">
        <div class="rounded-2xl border border-base-300 bg-gradient-to-r from-base-200 to-base-100 p-5 md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-black tracking-tight md:text-3xl">System</h1>
                    <p class="mt-1 max-w-2xl text-sm opacity-70">
                        Runtime versions, service health, and application-wide defaults. Only staff can view this page.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge {{ $errorCount ? 'badge-error' : 'badge-success' }} gap-1.5">
                        <i data-lucide="{{ $errorCount ? 'circle-alert' : 'check-circle' }}" class="h-3.5 w-3.5"></i>
                        {{ $errorCount }} {{ \Illuminate\Support\Str::plural('error', $errorCount) }}
                    </span>
                    <span class="badge {{ $warningCount ? 'badge-warning' : 'badge-ghost' }} gap-1.5">
                        <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i>
                        {{ $warningCount }} {{ \Illuminate\Support\Str::plural('warning', $warningCount) }}
                    </span>
                    <a href="{{ route('admin.system') }}" class="btn btn-outline btn-sm gap-2">
                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                        Refresh
                    </a>
                </div>
            </div>
        </div>

        {{-- Health status --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($groups as $group)
                <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
                    <header class="flex items-center gap-2 border-b border-base-200 bg-base-200/40 px-4 py-3">
                        <span class="grid size-8 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i data-lucide="{{ $group['icon'] }}" class="h-4 w-4"></i>
                        </span>
                        <h2 class="text-sm font-semibold uppercase tracking-wide opacity-70">{{ $group['title'] }}</h2>
                    </header>

                    <dl class="divide-y divide-base-200">
                        @foreach ($group['items'] as $item)
                            <div class="grid gap-1 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start sm:gap-3">
                                <div class="min-w-0">
                                    <dt class="text-sm font-medium">{{ $item['label'] }}</dt>
                                    @if ($item['note'])
                                        <p class="mt-0.5 text-xs text-base-content/60">{{ $item['note'] }}</p>
                                    @endif
                                </div>
                                <dd class="flex flex-wrap items-center gap-2 sm:justify-end">
                                    <span class="break-words text-xs text-base-content/70 sm:text-right">{{ $item['value'] }}</span>
                                    <span class="badge badge-sm {{ $statusBadge($item['status']) }}">{{ ucfirst($item['status']) }}</span>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>

        {{-- Editable settings --}}
        <form method="POST" action="{{ route('admin.system.update') }}" class="space-y-4">
            @csrf

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold">System configuration</h2>
                    <p class="text-sm text-base-content/60">
                        Values are stored in the database and override the environment defaults at runtime.
                    </p>
                </div>
                <button type="submit" class="btn btn-primary btn-sm gap-2">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    Save settings
                </button>
            </div>

            @foreach ($grouped as $groupName => $groupDefinitions)
                <section class="rounded-2xl border border-base-300 bg-base-100 p-4">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide opacity-70">{{ $groupName }}</h3>

                    <div class="space-y-3">
                        @foreach ($groupDefinitions as $key => $definition)
                            <div class="rounded-xl border border-base-200 bg-base-200/30 p-3">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <label for="setting-{{ $key }}" class="text-sm font-semibold">
                                            {{ $definition['label'] }}
                                        </label>
                                        <p class="text-xs text-base-content/60">{{ $definition['description'] }}</p>
                                        <p class="mt-1 break-all text-[11px] text-base-content/40">
                                            {{ $key }}
                                            @if ($definition['env'])
                                                · env {{ $definition['env'] }}
                                            @endif
                                            @if ($definition['config'])
                                                · config {{ $definition['config'] }}
                                            @endif
                                        </p>
                                    </div>

                                    <div class="w-full shrink-0 sm:w-64">
                                        @if ($definition['type'] === 'boolean')
                                            <input type="hidden" name="{{ $key }}" value="0">
                                            <label class="flex cursor-pointer items-center gap-2">
                                                <input
                                                    id="setting-{{ $key }}"
                                                    type="checkbox"
                                                    name="{{ $key }}"
                                                    value="1"
                                                    class="checkbox checkbox-primary checkbox-sm"
                                                    @checked((bool) $values[$key])
                                                >
                                                <span class="text-xs text-base-content/70">
                                                    {{ $values[$key] ? 'Enabled' : 'Disabled' }}
                                                </span>
                                            </label>
                                        @elseif ($definition['type'] === 'integer')
                                            <input
                                                id="setting-{{ $key }}"
                                                type="number"
                                                name="{{ $key }}"
                                                value="{{ $values[$key] }}"
                                                min="{{ $definition['min'] }}"
                                                max="{{ $definition['max'] }}"
                                                class="input input-bordered input-sm w-full"
                                            >
                                        @elseif ($definition['type'] === 'select')
                                            <select
                                                id="setting-{{ $key }}"
                                                name="{{ $key }}"
                                                class="select select-bordered select-sm w-full"
                                            >
                                                @foreach ($definition['options'] ?? [] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $values[$key] === (string) $optionValue)>
                                                        {{ $optionLabel }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input
                                                id="setting-{{ $key }}"
                                                type="text"
                                                name="{{ $key }}"
                                                value="{{ $values[$key] }}"
                                                class="input input-bordered input-sm w-full"
                                            >
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-sm gap-2">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    Save settings
                </button>
            </div>
        </form>

        {{-- Existing accounts --}}
        <section class="rounded-2xl border border-warning/40 bg-warning/5 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide opacity-70">Apply personal defaults</h2>
                    <p class="mt-1 max-w-2xl text-xs text-base-content/60">
                        New accounts always receive the defaults above. This action overwrites the matching
                        personal preferences on every existing account, including any changes those users made.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.system.defaults') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm gap-2">
                        <i data-lucide="users" class="h-4 w-4"></i>
                        Apply to existing accounts
                    </button>
                </form>
            </div>
        </section>
    </div>
</x-admin.layout>
