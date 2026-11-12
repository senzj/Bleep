@auth
    @php
        $activityUnread = once(fn () => app(\App\Services\Notifications\NotificationService::class)->unreadCount(auth()->user()));
    @endphp

    {{-- Right slide-over: the single activity surface on phones and tablets. --}}
    <div id="activity-drawer" class="fixed inset-0 z-60 hidden lg:hidden" aria-hidden="true">
        <div
            id="activity-drawer-overlay"
            class="absolute inset-0 bg-black/50 opacity-0 transition-opacity duration-300"
        ></div>

        <aside
            id="activity-drawer-panel"
            class="absolute right-0 top-0 flex h-full w-full max-w-md translate-x-full flex-col bg-base-100 shadow-2xl transition-transform duration-300 ease-out"
            role="dialog"
            aria-modal="true"
            aria-labelledby="activity-drawer-title"
        >
            <div class="flex items-center justify-between gap-3 border-b border-base-200 px-4 py-3">
                <h2 id="activity-drawer-title" class="flex items-center gap-2 font-semibold">
                    <i data-lucide="panel-right" class="w-5 h-5"></i>
                    Activity
                </h2>

                <button
                    type="button"
                    id="activity-drawer-close"
                    class="btn btn-ghost btn-sm btn-circle"
                    aria-label="Close activity"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="grid grid-cols-2 gap-1 border-b border-base-200 bg-base-200/60 p-1">
                <button
                    type="button"
                    class="activity-tab btn btn-sm border-0 shadow-none gap-2 btn-active"
                    data-activity-tab="notifications"
                    aria-selected="true"
                    role="tab"
                >
                    <i data-lucide="bell" class="w-4 h-4"></i>
                    Notifications
                    <span
                        class="badge badge-xs {{ $activityUnread > 0 ? 'badge-error' : 'hidden' }}"
                        data-activity-tab-badge
                    >{{ $activityUnread > 99 ? '99+' : $activityUnread }}</span>
                </button>
                <button
                    type="button"
                    class="activity-tab btn btn-sm border-0 shadow-none gap-2"
                    data-activity-tab="announcements"
                    aria-selected="false"
                    role="tab"
                >
                    <i data-lucide="megaphone" class="w-4 h-4"></i>
                    Announcements
                </button>
            </div>

            <div class="flex-1 overflow-y-auto overscroll-contain p-3">
                <div data-activity-panel="notifications" class="space-y-3">
                    <x-notifications.quick />
                </div>

                <div data-activity-panel="announcements" class="hidden space-y-3">
                    <x-announcement.system />
                </div>
            </div>
        </aside>
    </div>
@endauth

