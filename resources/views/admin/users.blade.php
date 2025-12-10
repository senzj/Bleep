@push('scripts')
    @vite('resources/js/admin/users.js')
@endpush

<x-admin.layout>

    {{-- Header + quick stats --}}
    <div class="mb-8 space-y-6">

        {{-- Page Title --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <h1 class="text-2xl font-bold tracking-tight">User Management</h1>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">

            <div class="bg-base-100 border border-base-300 rounded-xl p-5 shadow-sm hover:shadow transition">
                <div class="text-xs uppercase opacity-60 font-semibold">Total Users</div>
                <div class="mt-2 text-2xl font-bold text-primary">
                    {{ number_format($totalUsers) }}
                </div>
            </div>

            <div class="bg-base-100 border border-base-300 rounded-xl p-5 shadow-sm hover:shadow transition">
                <div class="text-xs uppercase opacity-60 font-semibold">Banned</div>
                <div class="mt-2 text-2xl font-bold text-error">
                    {{ number_format($bannedUsers) }}
                </div>
            </div>

            <div class="bg-base-100 border border-base-300 rounded-xl p-5 shadow-sm hover:shadow transition">
                <div class="text-xs uppercase opacity-60 font-semibold">New Today</div>
                <div class="mt-2 text-2xl font-bold text-success">
                    {{ number_format($newToday) }}
                </div>
            </div>

            <div class="bg-base-100 border border-base-300 rounded-xl p-5 shadow-sm hover:shadow transition">
                <div class="text-xs uppercase opacity-60 font-semibold">Online Now</div>

                <div class="mt-2 text-2xl font-bold text-info">
                    {{ number_format($onlineNow ?? 0) }}
                </div>

                <div class="mt-1 text-[11px] opacity-60">
                    Active in last 5 minutes
                </div>
            </div>

        </div>

        {{-- Search Bar --}}
        <form method="GET" action="{{ route('admin.users') }}" class="flex gap-2">
            <input
                type="text"
                name="q"
                value="{{ $q ?? '' }}"
                placeholder="Search username or email..."
                class="input input-bordered w-full rounded-xl"
            />
            <button class="btn btn-primary rounded-xl">Search</button>
        </form>

    </div>


    {{-- Users grid --}}
    <div class="bg-base-100 border border-base-300 rounded-xl overflow-hidden">

        {{-- Header (desktop only) --}}
        <div class="hidden md:grid grid-cols-[1.2fr_1.6fr_.7fr_.7fr_.9fr_1.2fr_.8fr] gap-4 px-5 py-3 bg-base-200/70 text-[11px] uppercase tracking-wide font-semibold text-center">
            <div>User</div>
            <div>Email</div>
            <div>Role</div>
            <div>Verified</div>
            <div>Status</div>
            <div>Remarks</div>
            <div class="text-right">Actions</div>
        </div>

        @forelse($users as $u)
            <div class="grid md:grid-cols-[1.2fr_1.6fr_.7fr_.7fr_.9fr_1.2fr_.8fr] grid-cols-1 gap-2 md:gap-4 px-5 py-4 border-t border-base-300 hover:bg-base-200/40 transition">

                {{-- USER --}}
                <div class="flex justify-between md:justify-center items-center md:items-center mt-1 md:mt-0">
                    <div class="md:hidden text-[13px] uppercase font-semibold mb-1">User</div>
                    <div class="flex items-center gap-3">
                        <img src="{{ $u->profile_picture_url }}" alt="Avatar" class="w-10 h-10 rounded-full object-cover shrink-0"/>
                        <div class="min-w-0 leading-tight">
                            <div class="font-semibold text-sm truncate">{{ $u->dname }}</div>
                            <div class="text-xs opacity-70 truncate">{{ '@'.$u->username }}</div>
                        </div>
                    </div>
                </div>

                {{-- EMAIL --}}
                <div class="flex justify-between md:justify-center items-center md:items-center mt-1 md:mt-0">
                    <span class="md:hidden text-[13px] uppercase font-semibold mr-2">Email</span>
                    <div class="truncate text-sm text-right md:text-center w-full md:w-auto">{{ $u->email }}</div>
                </div>

                {{-- ROLE --}}
                <div class="flex justify-between md:justify-center items-center md:items-center mt-1 md:mt-0">
                    <span class="md:hidden text-[13px] uppercase font-semibold mr-2">Role</span>
                    @php
                        [$roleText, $roleClasses] = match($u->role) {
                            'admin' => ['ADMIN', 'px-1 py-0.5 text-xs font-extrabold rounded bg-blue-500/20 text-blue-500 border border-blue-600/20'],
                            'moderator' => ['MODERATOR', 'px-1 py-0.5 text-xs font-extrabold rounded bg-violet-500/20 text-violet-500 border border-violet-600/20'],
                            default => ['USER', 'px-1 py-0.5 text-xs font-extrabold rounded bg-slate-500/20 text-slate-500 border border-slate-600/20'],
                        };
                    @endphp
                    <span class="{{ $roleClasses }}">{{ $roleText }}</span>
                </div>

                {{-- VERIFIED --}}
                <div class="flex justify-between md:justify-center items-center md:items-center mt-1 md:mt-0">
                    <span class="md:hidden text-[13px] uppercase font-semibold mr-2">Verified</span>
                    @if($u->is_verified)
                        <span class="px-1 py-0.5 text-xs font-extrabold rounded bg-emerald-500/20 text-emerald-500 border border-emerald-600/20">VERIFIED</span>
                    @else
                        <span class="px-1 py-0.5 text-xs font-extrabold rounded bg-slate-500/20 text-slate-500 border border-slate-600/20">UNVERIFIED</span>
                    @endif
                </div>

                {{-- STATUS --}}
                <div class="flex justify-between md:justify-center items-center md:items-center mt-1 md:mt-0">
                    <span class="md:hidden text-[13px] uppercase font-semibold mr-2">Status</span>
                    @if($u->is_banned)
                        <span class="px-1 py-0.5 text-xs font-extrabold rounded bg-rose-500/20 text-rose-500 border border-rose-600/20">BANNED</span>
                    @else
                        <span class="px-1 py-0.5 text-xs font-extrabold rounded bg-emerald-500/20 text-emerald-500 border border-emerald-600/20">ACTIVE</span>
                    @endif
                </div>

                {{-- REMARKS (left aligned, stacked on mobile) --}}
                <div>
                    <div class="md:hidden mobile-label">Remarks</div>
                    @if($u->is_banned)
                        @if($u->ban_reason)
                            <div class="text-sm font-medium line-clamp-2 text-base-400">{{ $u->ban_reason }}</div>
                        @endif
                        <div class="text-xs text-base-content/60 mt-1">
                            Until:
                            <span data-unban data-utc="{{ optional($u->banned_until)->toIso8601String() }}">
                                {{ $u->banned_until ? $u->banned_until->format('M d, Y | H:i:s') : '—' }}
                            </span>
                        </div>
                    @else
                        <div class="text-sm opacity-60">—</div>
                    @endif
                </div>

                {{-- ACTIONS --}}
                <div class="flex justify-between md:justify-end items-center gap-2 mt-2 md:mt-0">
                    <span class="md:hidden text-[13px] uppercase opacity-60 font-semibold mr-2">Actions</span>
                    <button class="btn btn-sm btn-neutral edit-user-btn ml-auto md:ml-0"
                        data-user-id="{{ $u->id }}"
                        data-username="{{ e($u->username) }}"
                        data-email="{{ e($u->email) }}"
                        data-role="{{ $u->role }}"
                        data-verified="{{ $u->is_verified ? '1' : '0' }}"
                        data-is-banned="{{ $u->is_banned ? '1' : '0' }}"
                        data-ban-reason="{{ e($u->ban_reason) }}"
                        data-banned-until="{{ optional($u->banned_until)->toIso8601String() }}">
                        Edit
                    </button>
                </div>

            </div>
        @empty
            <div class="px-5 py-6 text-sm opacity-70">No users found.</div>
        @endforelse

    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>

    {{-- Edit User Modal --}}
    <input type="checkbox" id="edit_user_modal" class="modal-toggle" />
    <div class="modal">
        <div class="modal-box relative max-w-lg p-6 rounded-xl">

            {{-- Close Button --}}
            <label for="edit_user_modal"
                class="btn btn-sm btn-circle absolute right-3 top-3">✕</label>

             {{-- Header --}}
            <h3 class="text-xl font-semibold mb-6 flex items-center gap-2">
                <i data-lucide="user-cog" class="w-5 h-5"></i>
                Edit User
            </h3>

            <form id="edit-user-form" class="space-y-8">
                @csrf
                <input type="hidden" id="eu_user_id" name="user_id" />

                {{-- Account --}}
                <div class="space-y-4">
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

                        <div class="space-y-1">
                            <label class="font-semibold text-sm">Role</label>
                            <select id="eu_role"
                                    name="role"
                                    class="select select-bordered w-full">
                                <option value="user">User</option>
                                <option value="moderator">Moderator</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="font-semibold text-sm block">Verified</label>
                            <input type="checkbox"
                                   class="toggle toggle-primary"
                                   name="is_verified"
                                   id="eu_is_verified" />
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                {{-- Moderation --}}
                <div class="collapse collapse-arrow bg-base-200/70 rounded-lg">
                    <input type="checkbox" id="eu_mod_collapse" />
                    <div class="collapse-title font-semibold text-base">
                        Moderation
                    </div>

                    <div class="collapse-content space-y-6">

                         {{-- Banned Toggle --}}
                        <div class="form-control">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox"
                                    id="eu_is_banned"
                                    class="toggle toggle-error" />
                                <span class="font-semibold text-sm">User is Banned</span>
                            </label>
                        </div>

                        {{-- Ban Fields --}}
                        <div id="ban_fields" class="space-y-6 hidden">

                            {{-- Ban Type --}}
                            <div class="space-y-1">
                                <label class="font-semibold text-sm">Ban Type</label>
                                <div class="flex flex-wrap gap-5 mt-1">
                                    <label class="flex items-center gap-2">
                                        <input type="radio"
                                            name="eu_ban_type"
                                            id="eu_ban_type_temp"
                                            value="temporary"
                                            checked />
                                        <span>Temporary</span>
                                    </label>

                                    <label class="flex items-center gap-2">
                                        <input type="radio"
                                            name="eu_ban_type"
                                            id="eu_ban_type_perm"
                                            value="permanent" />
                                        <span>Permanent</span>
                                    </label>
                                </div>
                            </div>

                             {{-- Ban Reason --}}
                            <div class="space-y-1">
                                <label class="font-semibold text-sm">Ban Reason</label>
                                <textarea id="eu_ban_reason"
                                        class="textarea textarea-bordered w-full h-24 leading-relaxed"
                                        maxlength="500"
                                        placeholder="Why is this user banned?"></textarea>
                                <div class="text-right text-xs opacity-60"
                                    id="eu_ban_reason_counter">0 / 500</div>
                            </div>

                             {{-- Ban Duration (temporary only) --}}
                            <div class="space-y-1" id="eu_ban_until_wrap">
                                <label class="font-semibold text-sm">Ban Until (local time)</label>
                                <input type="datetime-local"
                                    id="eu_banned_until"
                                    class="input input-bordered w-full" />

                                <div class="flex flex-wrap gap-2 items-center mt-2 text-xs">
                                    <button type="button" class="btn btn-xs preset" data-hours="6">+6h</button>
                                    <button type="button" class="btn btn-xs preset" data-hours="24">+24h</button>
                                    <button type="button" class="btn btn-xs preset" data-hours="72">+3d</button>
                                    <button type="button" class="btn btn-xs preset" data-hours="168">+7d</button>
                                    <button type="button" class="btn btn-xs preset" data-hours="720">+30d</button>

                                    <span class="opacity-60 ml-1">
                                        Saved in {{ config('app.timezone') }}
                                    </span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- Actions --}}
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
