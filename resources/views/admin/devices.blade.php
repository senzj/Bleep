@push('scripts')
    @vite('resources/js/admin/devices.js')
@endpush

@php
    use App\Helpers\UserAgentParser;
@endphp

<x-admin.layout>

    {{-- Header + Stats --}}
    <div class="mb-8 space-y-6">
        <h1 class="text-2xl font-bold">Sessions & Devices</h1>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="stat bg-base-100 rounded-xl shadow-sm border border-base-300">
                <div class="stat-title">Total Sessions</div>
                <div class="stat-value text-primary text-2xl">{{ number_format($totalSessions) }}</div>
            </div>
            <div class="stat bg-base-100 rounded-xl shadow-sm border border-base-300">
                <div class="stat-title">Active Now</div>
                <div class="stat-value text-success text-2xl">{{ number_format($activeSessions) }}</div>
                <div class="stat-desc text-xs opacity-70">Last 5 minutes</div>
            </div>
            <div class="stat bg-base-100 rounded-xl shadow-sm border border-base-300">
                <div class="stat-title">Unique Users</div>
                <div class="stat-value text-info text-2xl">{{ number_format($uniqueUsers) }}</div>
            </div>
            <div class="stat bg-base-100 rounded-xl shadow-sm border border-base-300">
                <div class="stat-title">Remembered Devices</div>
                <div class="stat-value text-warning text-2xl">{{ number_format($totalDevices) }}</div>
            </div>
            <div class="stat bg-base-100 rounded-xl shadow-sm border border-base-300">
                <div class="stat-title">Active Devices</div>
                <div class="stat-value text-accent text-2xl">{{ number_format($activeDevices) }}</div>
                <div class="stat-desc text-xs opacity-70">Last 7 days</div>
            </div>
        </div>

        {{-- Filter + Search --}}
        <form id="devices-filter-form" method="GET" action="{{ route('admin.devices') }}" class="flex gap-2 items-center w-full flex-wrap">
            <div class="flex-1 min-w-0 flex items-center gap-2">
                <input
                    type="text"
                    name="q"
                    value="{{ $search ?? '' }}"
                    placeholder="Search username / name / email"
                    class="input input-sm input-bordered w-full"
                />
                <button type="submit" class="btn btn-sm btn-primary">Search</button>
            </div>

            <div class="flex items-center gap-2">
                <select name="filter" onchange="document.getElementById('devices-filter-form').submit()" class="select select-sm">
                    <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All</option>
                    <option value="online" {{ $filter === 'online' ? 'selected' : '' }}>Online</option>
                    <option value="offline" {{ $filter === 'offline' ? 'selected' : '' }}>Offline</option>
                </select>

                <a href="{{ route('admin.devices') }}" class="btn btn-sm btn-ghost" title="Clear filters">Clear</a>
            </div>
        </form>
    </div>

    {{-- Sessions Table --}}
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4">Active Sessions</h2>
        <div class="bg-base-100 border border-base-300 rounded-xl overflow-hidden">
            <div class="hidden md:grid grid-cols-[1.2fr_1fr_1.2fr_1fr_.8fr] gap-3 px-4 py-3 bg-base-200 text-[11px] uppercase tracking-wide font-semibold text-center">
                <div>User</div>
                <div>IP Address</div>
                <div>Device / Browser</div>
                <div>Last Activity</div>
                <div>Actions</div>
            </div>

            @forelse($sessions as $s)
                @php
                    $user = $users->get($s->user_id);
                    $isOnline = $s->last_activity >= now()->subMinutes(5)->timestamp;
                    $lastActivity = \Carbon\Carbon::createFromTimestamp($s->last_activity);
                @endphp

                <div class="grid md:grid-cols-[1.2fr_1fr_1.2fr_1fr_.8fr] grid-cols-1 gap-2 md:gap-3 px-4 py-4 border-t border-base-300 hover:bg-base-200/40 transition">

                    <div class="flex justify-between md:justify-start items-center">
                        <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">User</span>
                        @if($user)
                            <div class="flex items-center gap-2">
                                <div class="relative">
                                    <img src="{{ $user->profile_picture_url }}" alt="Avatar" class="w-10 h-10 rounded-full object-cover shrink-0"/>
                                    @if($isOnline)
                                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-emerald-500 border-2 border-base-100 rounded-full"></span>
                                    @else
                                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-slate-400 border-2 border-base-100 rounded-full"></span>
                                    @endif
                                </div>
                                <div class="min-w-0 leading-tight">
                                    <div class="font-medium text-sm truncate">{{ $user->dname }}</div>
                                    <div class="text-xs opacity-70 truncate">{{ '@'.$user->username }}</div>
                                </div>
                            </div>
                        @else
                            <span class="text-xs opacity-50">[Deleted]</span>
                        @endif
                    </div>

                    <div class="flex justify-between md:justify-center items-center">
                        <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">IP Address</span>
                        <div class="text-sm font-mono">{{ $s->ip_address ?? '—' }}</div>
                    </div>

                    @php
                        $ua_os = UserAgentParser::parseOS($s->user_agent);
                        $ua_browser = UserAgentParser::parseBrowser($s->user_agent);
                    @endphp

                    <div class="flex justify-between md:justify-center items-center">
                        <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mb-1">Device</span>
                        <div>
                            <div class="text-xs leading-relaxed opacity-80">{!! $ua_os !!}</div>
                            <div class="text-xs leading-relaxed opacity-80">{!! $ua_browser !!}</div>
                        </div>
                    </div>

                    <div class="flex justify-between md:justify-center items-center">
                        <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">Last Activity</span>
                        <div class="text-sm">
                            <span data-timestamp="{{ $lastActivity->toIso8601String() }}">{{ $lastActivity->diffForHumans() }}</span>
                        </div>
                    </div>

                    <div class="flex justify-between md:justify-center items-center gap-2">
                        <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold">Actions</span>
                        <button class="btn btn-sm btn-error revoke-session-btn ml-auto md:ml-0" data-session-id="{{ $s->id }}">Log Out</button>
                    </div>
                </div>
            @empty
                <div class="px-4 py-6 text-sm opacity-70">No sessions found.</div>
            @endforelse
        </div>
        <div class="mt-4">{{ $sessions->links() }}</div>
    </div>

    {{-- Remembered Devices Table --}}
    <div>
        <h2 class="text-xl font-semibold mb-4">Remembered Devices</h2>
        <div class="bg-base-100 border border-base-300 rounded-xl overflow-hidden">
            <div class="hidden md:grid grid-cols-[1.2fr_1fr_1.2fr_1fr_.8fr] gap-3 px-4 py-3 bg-base-200 text-[11px] uppercase tracking-wide font-semibold text-center">
                <div>User</div>
                <div>IP Address</div>
                <div>Device / Browser</div>
                <div>Last Used</div>
                <div>Actions</div>
            </div>

            @forelse($devices as $d)
                <div class="grid md:grid-cols-[1.2fr_1fr_1.2fr_1fr_.8fr] grid-cols-1 gap-2 md:gap-3 px-4 py-4 border-t border-base-300 hover:bg-base-200/40 transition">
                    <div class="flex justify-between md:justify-start items-center">
                        <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">User</span>
                        @if($d->user)
                            <div class="flex items-center gap-2">
                                <img src="{{ $d->user->profile_picture_url }}" alt="Avatar" class="w-10 h-10 rounded-full object-cover shrink-0"/>
                                <div class="min-w-0 leading-tight">
                                    <div class="font-medium text-sm truncate">{{ $d->user->dname }}</div>
                                    <div class="text-xs opacity-70 truncate">{{ '@'.$d->user->username }}</div>
                                </div>
                            </div>
                        @else
                            <span class="text-xs opacity-50">[Deleted]</span>
                        @endif
                    </div>

                    <div class="flex justify-between md:justify-center items-center">
                        <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">IP Address</span>
                        <div class="text-sm font-mono">{{ $d->ip ?? '—' }}</div>
                    </div>

                    @php
                        $ua_os = UserAgentParser::parseOS($d->user_agent);
                        $ua_browser = UserAgentParser::parseBrowser($d->user_agent);
                    @endphp

                    <div class="flex justify-between md:justify-center items-center">
                        <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mb-1">Device</span>
                        <div>
                            <div class="text-xs leading-relaxed opacity-80">{!! $ua_os !!}</div>
                            <div class="text-xs leading-relaxed opacity-80">{!! $ua_browser !!}</div>
                        </div>
                    </div>

                    <div class="flex justify-between md:justify-center items-center">
                        <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">Last Used</span>
                        <div class="text-sm">
                            <span data-timestamp="{{ $d->last_used_at?->toIso8601String() }}">
                                {{ $d->last_used_at?->diffForHumans() ?? '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-between md:justify-center items-center gap-2">
                        <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold">Actions</span>
                        <button class="btn btn-sm btn-error revoke-device-btn ml-auto md:ml-0" data-device-id="{{ $d->id }}">Remove</button>
                    </div>
                </div>
            @empty
                <div class="px-4 py-6 text-sm opacity-70">No remembered devices found.</div>
            @endforelse
        </div>
        <div class="mt-4">{{ $devices->links() }}</div>
    </div>

</x-admin.layout>
