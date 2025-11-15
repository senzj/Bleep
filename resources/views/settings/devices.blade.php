@push('scripts')
    @vite('resources/js/settings/device.js')
@endpush

@php
    use App\Helpers\UserAgentParser;
@endphp

<x-settings.layout>

    {{-- Header + Stats --}}
    <div class="mb-8 space-y-6">
        <h1 class="text-2xl font-bold">Sessions & Devices</h1>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-base-200 rounded-lg p-4 text-center">
                <div class="text-sm font-medium text-base-content/70 mb-1">Active Sessions</div>
                <div class="text-3xl font-bold">{{ $sessions->total() }}</div>
            </div>
            <div class="bg-base-200 rounded-lg p-4 text-center">
                <div class="text-sm font-medium text-base-content/70 mb-1">Remembered Devices</div>
                <div class="text-3xl font-bold">{{ $devices->total() }}</div>
            </div>
            <div class="bg-base-200 rounded-lg p-4 text-center">
                <div class="text-sm font-medium text-base-content/70 mb-1">Total Users with Sessions</div>
                <div class="text-3xl font-bold">{{ $users->count() }}</div>
            </div>
        </div>
    </div>

    {{-- Sessions Table --}}
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4">Active Sessions</h2>
        <div class="bg-base-100 border border-base-300 rounded-xl overflow-x-auto">
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

                <div class="grid md:grid-cols-[1.2fr_1fr_1.2fr_1fr_.8fr] grid-cols-1 gap-2 md:gap-3 px-4 py-4 border-t border-base-300 hover:bg-base-200/40 transition
                    @if($s->id === $currentSessionId) bg-blue-300/50 @endif">

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
                        @if($s->id === $currentSessionId)
                            <button class="btn btn-sm btn-disabled ml-auto md:ml-0" disabled>Current Session</button>
                        @else
                            <button class="btn btn-sm btn-error revoke-session-btn ml-auto md:ml-0" data-session-id="{{ $s->id }}">Log Out</button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-2 py-3 md:px-4 md:py-4 text-sm opacity-70">No sessions found.</div>
            @endforelse
        </div>
        <div class="mt-4">{{ $sessions->links() }}</div>
    </div>

    {{-- Remembered Devices Table --}}
    <div>
        <h2 class="text-xl font-semibold mb-4">Remembered Devices</h2>
        <div class="bg-base-100 border border-base-300 rounded-xl overflow-x-auto">
            <div class="hidden md:grid grid-cols-[1.2fr_1fr_1.2fr_1fr_.8fr] gap-3 px-4 py-3 bg-base-200 text-[11px] uppercase tracking-wide font-semibold text-center">
                <div>User</div>
                <div>IP Address</div>
                <div>Device / Browser</div>
                <div>Last Used</div>
                <div>Actions</div>
            </div>

            @forelse($devices as $d)
                <div class="grid md:grid-cols-[1.2fr_1fr_1.2fr_1fr_.8fr] grid-cols-1 gap-2 md:gap-3 px-4 py-4 border-t border-base-300 hover:bg-base-200/40 transition
                    @if($d->token === $currentDeviceToken) bg-amber-100/60 ring-2 ring-amber-400 @endif">

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
                        @if($d->token === $currentDeviceToken)
                            <button class="btn btn-sm btn-disabled" disabled>Current Device</button>
                        @else
                            <button class="btn btn-sm btn-error revoke-device-btn" data-device-id="{{ $d->id }}">Remove</button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-2 py-3 md:px-4 md:py-4 text-sm opacity-70">No remembered devices found.</div>
            @endforelse
        </div>
        <div class="mt-4">{{ $devices->links() }}</div>
    </div>

    <div id="confirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden">
        <div class="bg-base-100 rounded-lg shadow-lg p-6 w-full max-w-sm">
            <div class="mb-4 font-semibold" id="confirmModalText">Are you sure you want to log out this session?</div>
            <div class="flex justify-end gap-2">
                <button id="confirmModalCancel" class="btn btn-sm">Cancel</button>
                <button id="confirmModalConfirm" class="btn btn-sm btn-error">Confirm</button>
            </div>
        </div>
    </div>
</x-settings.layout>
