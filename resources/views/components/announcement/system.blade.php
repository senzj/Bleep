@php
    $changelog = app(\App\Services\System\Changelog::class);
    $latestRelease = $changelog->latest();
    $currentVersion = $changelog->currentVersion();
    $latestBulletin = $changelog->bulletinLatest();
    $bulletinVersion = $changelog->bulletinVersion();

    // Reverb powers live notifications. Without it the bell falls back to a
    // five-minute poll, and the announcement should say so plainly.
    $realtimeNotifications = config('broadcasting.default') === 'reverb'
        && filled(config('broadcasting.connections.reverb.key'));
@endphp

<div class="ml-5 space-y-3" data-announcement-root>
    {{-- hosting limitations announcement --}}
    <div class="alert alert-warning relative pr-10"
         data-announcement="warning"
         data-announcement-reset-hours="24">
        <button type="button"
                class="btn btn-ghost btn-xs btn-circle absolute right-2 top-2"
                data-announcement-dismiss
                aria-label="Dismiss hosting notice">
            <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
        </button>

        <i data-lucide="alert-triangle" class="h-6 w-6 shrink-0 stroke-current"></i>
        <div>
            <span class="font-semibold">Due to hosting limitations, some features may be unavailable:</span>
            <ul class="mt-2 ml-4 list-disc text-sm space-y-1">
                <li>Edit functionality (editing posts, updating profile, changing password)</li>
                <li>Media uploads may be corrupted or unavailable</li>
                <li>
                    @if ($realtimeNotifications)
                        Notifications arrive live when the realtime connection is available; otherwise the bell refreshes every five minutes
                    @else
                        Realtime notifications are unavailable on this host — the bell refreshes every five minutes
                    @endif
                </li>
                <li>Chat is unavailable</li>
            </ul>
        </div>
    </div>

    {{-- Update announcement --}}
    @if ($latestRelease)
        <div class="alert alert-info relative pr-10"
             data-announcement="update"
             data-announcement-key="update-{{ $currentVersion }}-bulletin-{{ $bulletinVersion }}">
            <button type="button"
                    class="btn btn-ghost btn-xs btn-circle absolute right-2 top-2"
                    data-announcement-dismiss
                    aria-label="Dismiss update notice">
                <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
            </button>

            <i data-lucide="info" class="h-6 w-6 shrink-0 stroke-current"></i>
            <div class="space-y-1">
                <span class="font-semibold">System Update v{{ $currentVersion }}: {{ $latestRelease['title'] }}</span>
                <p class="text-sm">{{ $latestRelease['summary'] ?? '' }}</p>
                <p class="text-xs">
                    Bulletin board patch: v{{ $bulletinVersion }}@if ($latestBulletin) — {{ $latestBulletin['title'] }}@endif
                </p>
                <a href="{{ route('settings.system') }}" class="link link-primary text-sm font-medium">
                    See what's new
                </a>
            </div>
        </div>
    @endif
</div>
