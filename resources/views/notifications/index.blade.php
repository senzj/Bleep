<x-layout>
    <x-slot:title>Notifications</x-slot:title>

    <div class="container mx-auto max-w-4xl px-4">
        <div class="rounded-lg border border-base-300 bg-base-100 shadow-lg">
            {{-- Header --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-200 px-6 py-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                        <i data-lucide="bell" class="h-5 w-5 text-primary"></i>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold">Notifications</h1>
                        <p class="text-sm text-base-content/60">
                            @if ($unreadCount > 0)
                                {{ $unreadCount }} unread
                            @else
                                You're all caught up
                            @endif
                        </p>
                    </div>
                </div>

                @if ($unreadCount > 0)
                    <div x-data="{ marking: false }">
                        <button
                            type="button"
                            class="btn btn-outline btn-sm"
                            x-bind:disabled="marking"
                            @click="
                                marking = true;
                                window.axios.post(@js(route('notifications.read-all')))
                                    .then(() => window.location.reload())
                                    .catch(() => { marking = false; });
                            "
                        >
                            <i data-lucide="check-check" class="h-4 w-4"></i>
                            <span x-text="marking ? 'Marking…' : 'Mark all read'">Mark all read</span>
                        </button>
                    </div>
                @endif
            </div>

            {{-- Filters --}}
            <div class="flex flex-wrap items-center gap-2 border-b border-base-200 px-6 py-3">
                @php
                    $filters = ['all' => 'All', 'unread' => 'Unread'];
                    if ($isStaff) {
                        $filters['staff'] = 'Staff';
                    }
                @endphp

                @foreach ($filters as $key => $label)
                    <a
                        href="{{ route('notifications.index', array_filter(['filter' => $key])) }}"
                        class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-ghost' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            {{-- List --}}
            @if ($notifications->isEmpty())
                <div class="px-6 py-16 text-center">
                    <i data-lucide="bell-off" class="mx-auto mb-4 h-12 w-12 text-base-content/30"></i>
                    <p class="font-medium text-base-content/70">
                        @if ($filter === 'unread')
                            No unread notifications.
                        @elseif ($filter === 'staff')
                            No staff notifications yet.
                        @else
                            Nothing here yet.
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-base-content/50">
                        Likes, comments, follows and moderation updates will show up here.
                    </p>
                </div>
            @else
                <div>
                    @foreach ($notifications as $notification)
                        <x-notifications.row :notification="$notification" />
                    @endforeach
                </div>

                <div class="border-t border-base-200 px-6 py-4">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                document.addEventListener('click', function (event) {
                    const link = event.target.closest('[data-notification-id]');

                    if (!link || link.dataset.notificationRead === '1') {
                        return;
                    }

                    if (link.getAttribute('href') === '#') {
                        event.preventDefault();
                    }

                    link.dataset.notificationRead = '1';

                    const url = @js(route('notifications.read', ['notification' => '__ID__']))
                        .replace('__ID__', encodeURIComponent(link.dataset.notificationId));

                    fetch(url, {
                        method: 'POST',
                        keepalive: true,
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                    }).catch(() => {});
                });
            </script>
        @endpush
    @endonce
</x-layout>
