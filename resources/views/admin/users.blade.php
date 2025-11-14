@push('scripts')
    @vite('resources/js/admin/users.js')
@endpush

<x-admin.layout>

    {{-- Header + quick stats --}}
    <div class="mb-6 space-y-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <h1 class="text-2xl font-bold">Users</h1>

            <form method="GET" action="{{ route('admin.users') }}" class="flex gap-2">
                <input type="text"
                       name="q"
                       value="{{ $q ?? '' }}"
                       placeholder="Search username or email..."
                       class="input input-bordered w-64 max-w-full" />
                <button class="btn btn-primary">Search</button>
            </form>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="stat bg-base-100 rounded-xl shadow-sm border border-base-300">
                <div class="stat-title">Total Users</div>
                <div class="stat-value text-primary">{{ number_format($totalUsers) }}</div>
            </div>
            <div class="stat bg-base-100 rounded-xl shadow-sm border border-base-300">
                <div class="stat-title">Banned</div>
                <div class="stat-value text-error">{{ number_format($bannedUsers) }}</div>
            </div>
            <div class="stat bg-base-100 rounded-xl shadow-sm border border-base-300">
                <div class="stat-title">New Today</div>
                <div class="stat-value text-success">{{ number_format($newToday) }}</div>
            </div>
            <div class="stat bg-base-100 rounded-xl shadow-sm border border-base-300">
                <div class="stat-title">Online Now</div>
                <div class="stat-value text-info">{{ number_format($onlineNow ?? 0) }}</div>
                <div class="stat-desc text-xs opacity-70">Active in last 5 minutes</div>
            </div>
        </div>
    </div>

    {{-- Users grid (pseudo-table: grid, no <table>) --}}
    <div class="bg-base-100 border border-base-300 rounded-xl overflow-hidden">
        {{-- Header (desktop only) --}}
        <div class="hidden md:grid grid-cols-[1.3fr_1.6fr_.9fr_1.4fr_.8fr] gap-3 px-4 py-3 bg-base-200 text-[11px] uppercase tracking-wide">
            <div>User</div>
            <div>Email</div>
            <div>Status</div>
            <div>Banned Until</div>
            <div class="text-right">Actions</div>
        </div>

        @forelse($users as $u)
            <div class="grid md:grid-cols-[1.3fr_1.6fr_.9fr_1.4fr_.8fr] grid-cols-1 gap-2 md:gap-3 px-4 py-3 border-t border-base-300">
                {{-- USER --}}
                <div class="flex items-start justify-between md:block">
                    <div class="md:hidden text-[10px] uppercase opacity-60 mb-1">User</div>
                    <div class="min-w-0">
                        <div class="font-medium truncate">{{ $u->username ?? $u->name ?? 'User #'.$u->id }}</div>
                        <div class="text-[11px] opacity-70">Joined: {{ $u->created_at?->format('Y-m-d') }}</div>
                    </div>
                </div>

                {{-- EMAIL --}}
                <div class="md:flex md:items-center">
                    <div class="md:hidden text-[10px] uppercase opacity-60 mb-1">Email</div>
                    <div class="truncate text-sm">{{ $u->email }}</div>
                </div>

                {{-- STATUS --}}
                <div class="md:flex md:items-center">
                    <div class="md:hidden text-[10px] uppercase opacity-60 mb-1">Status</div>
                    @if($u->is_banned)
                        <div class="badge badge-error badge-outline">Banned</div>
                    @else
                        <div class="badge badge-success badge-outline">Active</div>
                    @endif
                </div>

                {{-- BANNED UNTIL + REASON (if banned) --}}
                <div>
                    <div class="md:hidden text-[10px] uppercase opacity-60 mb-1">Banned Until</div>
                    @if($u->is_banned)
                        <div class="text-sm">
                            <span class="font-medium" data-unban data-utc="{{ optional($u->banned_until)->toIso8601String() ?? '' }}">
                                {{ $u->banned_until ? $u->banned_until->toIso8601String() : '—' }}
                            </span>
                        </div>
                        @if($u->ban_reason)
                            <div class="text-[11px] opacity-70 mt-1 line-clamp-2">
                                Reason: {{ $u->ban_reason }}
                            </div>
                        @endif
                    @else
                        <div class="text-sm opacity-60">—</div>
                    @endif
                </div>

                {{-- ACTIONS --}}
                <div class="flex md:items-center md:justify-end">
                    <div class="md:hidden text-[10px] uppercase opacity-60 mb-1 w-full">Actions</div>
                    <button
                        class="btn btn-sm btn-neutral edit-user-btn ml-auto md:ml-0"
                        data-user-id="{{ $u->id }}"
                        data-username="{{ e($u->username) }}"
                        data-email="{{ e($u->email) }}"
                        data-is-banned="{{ $u->is_banned ? '1' : '0' }}"
                        data-ban-reason="{{ e($u->ban_reason) }}"
                        data-banned-until="{{ optional($u->banned_until)->toIso8601String() }}">
                        Edit
                    </button>
                </div>
            </div>
        @empty
            <div class="px-4 py-6 text-sm opacity-70">No users found.</div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>

    {{-- Edit User Modal --}}
    <input type="checkbox" id="edit_user_modal" class="modal-toggle" />
    <div class="modal">
        <div class="modal-box relative max-w-lg p-6 rounded-xl">

            <!-- Close Button -->
            <label for="edit_user_modal"
                class="btn btn-sm btn-circle absolute right-3 top-3">✕</label>

            <!-- Header -->
            <h3 class="text-xl font-bold mb-5 flex items-center gap-2">
                <i data-lucide="user-cog" class="w-5 h-5"></i>
                Edit User
            </h3>

            <form id="edit-user-form" class="space-y-6">
                @csrf
                <input type="hidden" id="eu_user_id" name="user_id" />

                <!-- User Info -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="font-semibold text-sm">Username</label>
                        <input id="eu_username"
                            class="input input-bordered w-full bg-base-200"
                            disabled />
                    </div>

                    <div class="space-y-1">
                        <label class="font-semibold text-sm">Email</label>
                        <input id="eu_email"
                            class="input input-bordered w-full bg-base-200"
                            disabled />
                    </div>
                </div>

                <div class="divider my-3"></div>

                <!-- Banned Toggle -->
                <div class="form-control">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox"
                            id="eu_is_banned"
                            class="toggle toggle-error" />
                        <span class="font-semibold text-sm">User is Banned</span>
                    </label>
                </div>

                <!-- Ban Fields -->
                <div id="ban_fields" class="space-y-5 hidden">

                    <!-- Ban Reason -->
                    <div class="space-y-1">
                        <label class="font-semibold text-sm">Ban Reason</label>
                        <textarea
                            id="eu_ban_reason"
                            class="textarea textarea-bordered w-full h-24 leading-relaxed"
                            maxlength="500"
                            placeholder="Why is this user banned?"
                        ></textarea>

                        <div class="text-right text-xs opacity-60"
                            id="eu_ban_reason_counter">0 / 500</div>
                    </div>

                    <!-- Ban Duration -->
                    <div class="space-y-1">
                        <label class="font-semibold text-sm">
                            Ban Until (your local time)
                        </label>

                        <input type="datetime-local"
                            id="eu_banned_until"
                            class="input input-bordered w-full" />

                        <div class="mt-2 flex flex-wrap gap-2 items-center text-xs">
                            <button type="button"
                                    class="btn btn-xs preset"
                                    data-hours="6">+6h</button>
                            <button type="button"
                                    class="btn btn-xs preset"
                                    data-hours="24">+24h</button>
                            <button type="button"
                                    class="btn btn-xs preset"
                                    data-hours="72">+3d</button>
                            <button type="button"
                                    class="btn btn-xs preset"
                                    data-hours="168">+7d</button>
                            <button type="button"
                                    class="btn btn-xs preset"
                                    data-hours="720">+30d</button>

                            <span class="opacity-60 ml-1">
                                Saved in {{ config('app.timezone') }}
                            </span>
                        </div>
                    </div>

                </div>

                <!-- Modal Actions -->
                <div class="modal-action">
                    <label for="edit_user_modal" class="btn btn-ghost">Cancel</label>

                    <button type="submit"
                            class="btn btn-primary gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Save Changes
                    </button>
                </div>

            </form>
        </div>
    </div>

</x-admin.layout>
