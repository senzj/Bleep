@auth
    @php
        $quickNotifications = auth()->user()->notifications()
            ->with('actor:id,username,dname,profile_picture')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn ($notification) => \App\Services\Notifications\NotificationPresenter::toArray($notification));
    @endphp

    <div class="mt-4 rounded-lg border border-base-200 bg-base-100 shadow-lg">
        <div class="flex items-center justify-between border-b border-base-200 px-4 py-3">
            <h2 class="font-semibold">Notifications</h2>
            <a href="{{ route('notifications.index') }}" class="text-xs font-medium text-primary hover:underline">
                View all
            </a>
        </div>

        @if ($quickNotifications->isEmpty())
            <div class="px-4 py-8 text-center">
                <i data-lucide="bell-off" class="mx-auto mb-2 w-7 h-7 text-base-content/40"></i>
                <p class="text-sm text-base-content/60">Nothing new right now.</p>
            </div>
        @else
            <div>
                @foreach ($quickNotifications as $notification)
                    <a
                        href="{{ $notification['url'] ?? route('notifications.index') }}"
                        class="flex gap-3 border-b border-base-200/70 px-4 py-3 transition-colors last:border-b-0 hover:bg-base-200/60 {{ $notification['read'] ? '' : 'bg-primary/5' }}"
                    >
                        @if ($notification['actor'])
                            <img
                                src="{{ $notification['actor']['profile_picture_url'] }}"
                                alt="{{ $notification['actor']['dname'] ?? $notification['actor']['username'] }}"
                                class="h-8 w-8 shrink-0 rounded-full object-cover"
                            >
                        @else
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-base-200">
                                <i data-lucide="{{ $notification['icon'] }}" class="h-4 w-4 text-base-content/70"></i>
                            </span>
                        @endif

                        <span class="min-w-0 flex-1">
                            <span class="block text-sm leading-snug">{{ $notification['title'] }}</span>
                            <span class="block text-[11px] text-base-content/50">{{ $notification['created_at_human'] }}</span>
                        </span>

                        @unless ($notification['read'])
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" aria-label="Unread"></span>
                        @endunless
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endauth
