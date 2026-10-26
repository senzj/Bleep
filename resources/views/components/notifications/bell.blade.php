@auth
    @php
        $bellUnreadCount = once(fn () => app(\App\Services\Notifications\NotificationService::class)->unreadCount(auth()->user()));
        $bellSound = auth()->user()->preferences?->recieve_notification_sound
            ?? \App\Models\UserPreferences::defaults()['recieve_notification_sound'];
    @endphp

    <div
        class="relative"
        data-notifications-bell
        x-data="notificationsBell({
            dataUrl: @js(route('notifications.data')),
            readUrlTemplate: @js(route('notifications.read', ['notification' => '__ID__'])),
            readAllUrl: @js(route('notifications.read-all')),
            unreadCount: {{ $bellUnreadCount }},
            soundUrl: @js($bellSound),
            userId: {{ auth()->id() }},
        })"
        @keydown.escape.window="open = false"
    >
        {{-- Bell --}}
        <button
            type="button"
            class="btn btn-ghost btn-circle"
            @click="toggle()"
            :aria-expanded="open"
            aria-haspopup="true"
            aria-label="Notifications"
        >
            <span class="relative inline-flex">
                <i data-lucide="bell" class="w-5 h-5"></i>
                <span
                    class="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold leading-none text-white"
                    x-show="unreadCount > 0"
                    x-text="unreadCount > 99 ? '99+' : unreadCount"
                    x-cloak
                ></span>
            </span>
        </button>

        {{-- Dropdown --}}
        <div
            class="absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-base-200 bg-base-100 shadow-xl"
            x-show="open"
            x-transition.origin.top.right
            x-cloak
            @click.outside="open = false"
            role="dialog"
            aria-label="Notifications"
        >
            <div class="flex items-center justify-between gap-2 border-b border-base-200 px-4 py-3">
                <span class="font-semibold">Notifications</span>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    @click="markAllRead()"
                    x-bind:disabled="unreadCount === 0"
                    x-show="unreadCount > 0"
                >
                    Mark all read
                </button>
            </div>

            <div class="max-h-[60vh] overflow-y-auto" x-ref="list">
                {{-- Loading --}}
                <div class="flex items-center justify-center gap-2 px-4 py-10 text-sm text-base-content/60" x-show="loading">
                    <span class="loading loading-spinner loading-sm"></span>
                    <span>Loading notifications…</span>
                </div>

                {{-- Empty --}}
                <div class="px-4 py-10 text-center" x-show="!loading && items.length === 0">
                    <i data-lucide="bell-off" class="mx-auto mb-3 w-8 h-8 text-base-content/40"></i>
                    <p class="text-sm text-base-content/60">You're all caught up.</p>
                </div>

                {{-- Rows --}}
                <template x-for="item in items" :key="item.id">
                    <a
                        :href="item.url || '#'"
                        class="flex gap-3 border-b border-base-200/70 px-4 py-3 transition-colors last:border-b-0 hover:bg-base-200/60"
                        x-bind:class="!item.read ? 'bg-primary/5' : ''"
                        @click="onItemClick(item, $event)"
                    >
                        <template x-if="item.actor">
                            <img
                                :src="item.actor.profile_picture_url"
                                :alt="item.actor.dname || item.actor.username"
                                class="h-9 w-9 shrink-0 rounded-full object-cover"
                            >
                        </template>

                        <template x-if="!item.actor">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-base-200">
                                <i :data-lucide="item.icon" class="h-4 w-4 text-base-content/70"></i>
                            </span>
                        </template>

                        <span class="min-w-0 flex-1">
                            <span class="block text-sm leading-snug" x-text="item.title"></span>
                            <span class="block truncate text-xs text-base-content/60" x-show="item.body" x-text="item.body"></span>
                            <span class="mt-1 block text-[11px] text-base-content/50" x-text="item.created_at_human"></span>
                        </span>

                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" x-show="!item.read" aria-label="Unread"></span>
                    </a>
                </template>
            </div>

            <a
                href="{{ route('notifications.index') }}"
                class="block border-t border-base-200 px-4 py-3 text-center text-sm font-medium text-primary hover:bg-base-200/60"
            >
                View all notifications
            </a>
        </div>

        {{-- Toast --}}
        <div
            class="fixed right-4 top-4 z-100 w-80 max-w-[calc(100vw-2rem)]"
            x-show="toast"
            x-transition
            x-cloak
            role="status"
            aria-live="polite"
        >
            <a
                :href="toast?.url || '#'"
                class="flex gap-3 rounded-lg border border-base-200 bg-base-100 p-3 shadow-xl"
                @click="toast && onItemClick(toast, $event); toast = null"
            >
                <template x-if="toast?.actor">
                    <img
                        :src="toast.actor.profile_picture_url"
                        :alt="toast.actor.dname || toast.actor.username"
                        class="h-9 w-9 shrink-0 rounded-full object-cover"
                    >
                </template>

                <template x-if="toast && !toast.actor">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-base-200">
                        <i :data-lucide="toast.icon" class="h-4 w-4 text-base-content/70"></i>
                    </span>
                </template>

                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-medium leading-snug" x-text="toast?.title"></span>
                    <span class="block truncate text-xs text-base-content/60" x-show="toast?.body" x-text="toast?.body"></span>
                </span>

                <button
                    type="button"
                    class="btn btn-ghost btn-xs btn-circle shrink-0"
                    aria-label="Dismiss notification"
                    @click.prevent.stop="toast = null"
                >
                    <i data-lucide="x" class="h-3.5 w-3.5"></i>
                </button>
            </a>
        </div>
    </div>
@endauth
