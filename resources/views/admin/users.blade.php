@once
    @push('styles')
        <style>
            @media(min-width: 768px) {
                .user-table-row {
                    grid-template-columns: 140px 220px 100px 100px 140px 100px !important;
                }
            }
        </style>
    @endpush

    @push('scripts')
        @vite('resources/js/admin/users.js')
    @endpush
@endonce

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
    <div class="bg-base-100 border border-base-300 rounded-xl overflow-x-auto md:overflow-hidden">

        {{-- Header (desktop only) --}}
        <div class="hidden md:grid grid-cols-[60px_1fr_1fr_100px_90px_1fr_100px] gap-4 px-5 py-3 bg-base-200/70 text-xs uppercase tracking-wide font-semibold">
            <div class="text-center">ID</div>
            <div>User</div>
            <div>Email</div>
            <div class="text-center">Role</div>
            <div class="text-center">Status</div>
            <div>Remarks</div>
            <div class="text-center">Actions</div>
        </div>

        @forelse($users as $u)
            <div class="grid grid-cols-1 md:grid-cols-[60px_1fr_1fr_100px_90px_1fr_100px] gap-2 md:gap-4 px-5 py-4 border-t border-base-300 hover:bg-base-200/40 transition items-center">

                {{-- ID --}}
                <div class="flex items-center gap-2 md:justify-center">
                    <span class="md:hidden text-[11px] uppercase font-semibold text-base-content/50 w-20 shrink-0">ID</span>
                    <span class="text-sm text-base-content/60">{{ $u->id }}</span>
                </div>

                {{-- USER --}}
                <div class="flex items-center gap-2">
                    <span class="md:hidden text-[11px] uppercase font-semibold text-base-content/50 w-20 shrink-0">User</span>
                    <div class="flex items-center gap-3 min-w-0">
                        <img src="{{ $u->profile_picture_url }}" alt="Avatar"
                            class="w-9 h-9 rounded-full object-cover shrink-0"/>
                        <div class="min-w-0 leading-tight">
                            <div class="font-semibold text-sm truncate flex items-center gap-1">
                                {{ $u->dname }}
                                @if($u->is_verified)
                                    <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-500 shrink-0"></i>
                                @endif
                            </div>
                            <div class="text-xs text-base-content/50 truncate">{{ '@'.$u->username }}</div>
                        </div>
                    </div>
                </div>

                {{-- EMAIL --}}
                <div class="flex items-center gap-2 min-w-0">
                    <span class="md:hidden text-[11px] uppercase font-semibold text-base-content/50 w-20 shrink-0">Email</span>
                    <span class="text-sm truncate text-base-content/70">{{ $u->email }}</span>
                </div>

                {{-- ROLE --}}
                <div class="flex items-center gap-2 md:justify-center">
                    <span class="md:hidden text-[11px] uppercase font-semibold text-base-content/50 w-20 shrink-0">Role</span>
                    @php
                        [$roleText, $roleClasses] = match($u->role) {
                            'admin'     => ['Admin',     'bg-blue-500/10 text-blue-600 border-blue-500/30'],
                            'moderator' => ['Moderator', 'bg-violet-500/10 text-violet-600 border-violet-500/30'],
                            default     => ['User',      'bg-base-300/50 text-base-content/60 border-base-300'],
                        };
                    @endphp
                    <span class="badge badge-sm border {{ $roleClasses }} font-semibold">{{ $roleText }}</span>
                </div>

                {{-- STATUS --}}
                <div class="flex items-center gap-2 md:justify-center">
                    <span class="md:hidden text-[11px] uppercase font-semibold text-base-content/50 w-20 shrink-0">Status</span>
                    @if($u->is_banned)
                        <span class="badge badge-sm badge-error badge-outline font-semibold">Banned</span>
                    @else
                        <span class="badge badge-sm badge-success badge-outline font-semibold">Active</span>
                    @endif
                </div>

                {{-- REMARKS --}}
                <div class="flex items-start gap-2 min-w-0">
                    <span class="md:hidden text-[11px] uppercase font-semibold text-base-content/50 w-20 shrink-0 mt-0.5">Remarks</span>
                    @if($u->is_banned)
                        <div class="min-w-0">
                            @if($u->ban_reason)
                                <p class="text-sm line-clamp-2 text-base-content/80">{{ $u->ban_reason }}</p>
                            @endif
                            <p class="text-xs text-base-content/50 mt-0.5">
                                Until:
                                <span data-unban data-utc="{{ optional($u->banned_until)->toIso8601String() }}">
                                    {{ $u->banned_until ? $u->banned_until->format('M d, Y · H:i') : 'Permanent' }}
                                </span>
                            </p>
                        </div>
                    @else
                        <span class="text-sm text-base-content/40 italic">—</span>
                    @endif
                </div>

                {{-- ACTIONS --}}
                <div class="flex items-center gap-2 md:justify-center">
                    <span class="md:hidden text-[11px] uppercase font-semibold text-base-content/50 w-20 shrink-0">Actions</span>
                    <button class="btn btn-sm btn-outline w-full md:w-auto edit-user-btn"
                        data-user-id="{{ $u->id }}"
                        data-username="{{ e($u->username) }}"
                        data-email="{{ e($u->email) }}"
                        data-role="{{ $u->role }}"
                        data-verified="{{ $u->is_verified ? '1' : '0' }}"
                        data-is-banned="{{ $u->is_banned ? '1' : '0' }}"
                        data-ban-reason="{{ e($u->ban_reason) }}"
                        data-banned-until="{{ optional($u->banned_until)->toIso8601String() }}">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                        Edit
                    </button>
                </div>

            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-base-content/50">
                <i data-lucide="users" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                No users found.
            </div>
        @endforelse

    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>

    @once
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
    @endonce

</x-admin.layout>
