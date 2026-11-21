@php
    $announcement = \App\Services\System\SystemSettings::values();
    $announcementMessage = $announcement['announcement_message'] ?? null;
    $announcementEnabled = (bool) ($announcement['announcement_enabled'] ?? false);
    $announcementLevel = $announcement['announcement_level'] ?? 'warning';

    if (! in_array($announcementLevel, ['info', 'success', 'warning', 'error'], true)) {
        $announcementLevel = 'warning';
    }
@endphp

@if ($announcementEnabled && filled($announcementMessage))
    <div class="px-2 pt-2 sm:px-4 lg:px-6" data-announcement-root>
        <div
            class="alert alert-{{ $announcementLevel }} relative mx-auto flex w-full max-w-7xl items-start gap-3 pr-10 text-left"
            role="status"
            data-announcement="admin"
            data-announcement-key="admin-{{ md5($announcementMessage) }}"
        >
            <button
                type="button"
                class="btn btn-ghost btn-xs btn-circle absolute right-2 top-2"
                data-announcement-dismiss
                aria-label="Dismiss announcement"
            >
                <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
            </button>

            <i data-lucide="megaphone" class="h-5 w-5 shrink-0 stroke-current" aria-hidden="true"></i>

            <div class="min-w-0 whitespace-pre-line text-sm">{{ $announcementMessage }}</div>
        </div>
    </div>
@endif
