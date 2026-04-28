@once
    @php
        use App\Helpers\UserAgentParser;
    @endphp
@endonce
<x-admin.layout>
    <div class="mb-8">
        <h1 class="text-2xl font-bold mb-2">System Logs</h1>
        <p class="text-sm text-base-content/70">Monitor all system activities and user actions</p>
    </div>

    {{-- Stats Overview --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-base-200 rounded-lg p-4">
            <div class="text-xs font-medium text-base-content/70 mb-1">Total Events</div>
            <div class="text-2xl font-bold">{{ $logs->total() }}</div>
        </div>
        <div class="bg-base-200 rounded-lg p-4">
            <div class="text-xs font-medium text-base-content/70 mb-1">Today</div>
            <div class="text-2xl font-bold">{{ $logs->where('created_at', '>=', now()->startOfDay())->count() }}</div>
        </div>
        <div class="bg-base-200 rounded-lg p-4">
            <div class="text-xs font-medium text-base-content/70 mb-1">Last 24h</div>
            <div class="text-2xl font-bold">{{ $logs->where('created_at', '>=', now()->subDay())->count() }}</div>
        </div>
        <div class="bg-base-200 rounded-lg p-4">
            <div class="text-xs font-medium text-base-content/70 mb-1">This Week</div>
            <div class="text-2xl font-bold">{{ $logs->where('created_at', '>=', now()->subWeek())->count() }}</div>
        </div>
        <div class="bg-base-200 rounded-lg p-4">
            <div class="text-xs font-medium text-base-content/70 mb-1">Unique Users</div>
            <div class="text-2xl font-bold">{{ $logs->pluck('user_id')->unique()->count() }}</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-base-200 rounded-lg p-4 mb-6 flex flex-col md:flex-row md:items-center gap-3">
        <form method="GET" action="{{ route('admin.logs') }}" class="flex-1 flex gap-3 items-center flex-wrap">
            <div class="flex-1 min-w-[220px]">
                <input type="search" name="q" value="{{ old('q', $q ?? request('q')) }}" placeholder="Search username, display name, email or IP" class="input input-sm w-full" />
            </div>

            <div class="min-w-40">
                <select name="action" class="select select-sm w-full">
                    <option value="">All Actions</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" @if(($action ?? request('action')) === $a) selected @endif>{{ ucwords(str_replace('_',' ',$a)) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-40">
                <select name="device_os" class="select select-sm w-full">
                    <option value="">Any OS</option>
                    @foreach($oses as $os)
                        <option value="{{ $os }}" @if(($device_os ?? request('device_os')) === $os) selected @endif>{{ $os }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-40">
                <select name="device_browser" class="select select-sm w-full">
                    <option value="">Any Browser</option>
                    @foreach($browsers as $b)
                        <option value="{{ $b }}" @if(($device_browser ?? request('device_browser')) === $b) selected @endif>{{ $b }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[140px]">
                <label class="sr-only">From</label>
                <input type="date" name="date_from" value="{{ old('date_from', $dateFrom ?? request('date_from')) }}" class="input input-sm w-full" />
            </div>

            <div class="min-w-[140px]">
                <label class="sr-only">To</label>
                <input type="date" name="date_to" value="{{ old('date_to', $dateTo ?? request('date_to')) }}" class="input input-sm w-full" />
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('admin.logs') }}" class="btn btn-sm btn-ghost">Reset</a>
            </div>
        </form>
    </div>

    <div class="bg-base-100 border border-base-300 rounded-xl overflow-hidden">
        {{-- Desktop Header --}}
        <div class="hidden lg:grid grid-cols-[1.2fr_1.2fr_1fr_2fr_1fr_1.5fr] gap-3 px-4 py-3 bg-base-200 text-[11px] uppercase tracking-wide font-semibold text-center">
            <div>Date & Time</div>
            <div>User</div>
            <div>Action</div>
            <div>Details</div>
            <div>IP Address</div>
            <div>Device</div>
        </div>

        @forelse($logs as $log)
            @php
                $actionColors = [
                    'login' => 'badge-success',
                    'logout' => 'badge-warning',
                    'password_change' => 'badge-info',
                    'profile_update' => 'badge-info',
                    'failed_login' => 'badge-error',
                    'device_added' => 'badge-success',
                    'device_removed' => 'badge-warning',
                    'user_created' => 'badge-success',
                    'user_deleted' => 'badge-error',
                    'user_updated' => 'badge-info',
                ];
                $badgeColor = $actionColors[strtolower($log->action)] ?? 'badge-ghost';

                $actionIcons = [
                    'login' => 'log-in',
                    'logout' => 'log-out',
                    'password_change' => 'key',
                    'profile_update' => 'user',
                    'failed_login' => 'alert-circle',
                    'device_added' => 'smartphone',
                    'device_removed' => 'smartphone',
                    'user_created' => 'user-plus',
                    'user_deleted' => 'user-minus',
                    'user_updated' => 'user-check',
                ];
                $icon = $actionIcons[strtolower($log->action)] ?? 'activity';

                $user = $log->user;
                $isOnline = $user && $user->last_seen && $user->last_seen >= now()->subMinutes(5);
            @endphp

            {{-- Desktop Layout --}}
            <div class="hidden lg:grid grid-cols-[1.2fr_1.2fr_1fr_2fr_1fr_1.5fr] gap-3 px-4 py-4 border-t border-base-300 hover:bg-base-200/40 transition items-center">
                {{-- Date & Time --}}
                <div>
                    <div class="text-sm">{{ $log->created_at->format('M d, Y') }} | {{ $log->created_at->format('h:i:s A') }}</div>
                    <div class="text-xs text-base-content/50">{{ $log->created_at->diffForHumans() }}</div>
                </div>

                {{-- User --}}
                <div class="justify-center">
                    @if($user)
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <img src="{{ $user->profile_picture_url }}" alt="Avatar" class="w-8 h-8 rounded-full object-cover"/>
                                @if($isOnline)
                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 border-2 border-base-100 rounded-full"></span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-medium truncate">{{ $user->dname }}</div>
                                <div class="text-xs text-base-content/60 truncate">{{"@" . $user->username }}</div>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-base-300 flex items-center justify-center">
                                <i data-lucide="user-x" class="w-4 h-4 text-base-content/50"></i>
                            </div>
                            <span class="text-xs text-base-content/50">User ID: {{ $log->user_id ?? '—' }}</span>
                        </div>
                    @endif
                </div>

                {{-- Action --}}
                <div class="text-center">
                    <span class="badge {{ $badgeColor }} badge-sm gap-1 whitespace-nowrap">
                        <i data-lucide="{{ $icon }}" class="w-3 h-3"></i>
                        {{ ucwords(str_replace('_', ' ', $log->action)) }}
                    </span>
                </div>

                {{-- Details --}}
                @php
                    $hasExtraJson = $log->details && is_array($log->details) && (count($log->details) > 1 || !empty($log->details) && !isset($log->details['message']));
                @endphp
                <div class="text-sm text-center">
                    {{ $log->readableDetails() }}
                    @if($hasExtraJson)
                        <details class="cursor-pointer mt-2">
                            <summary class="text-xs text-base-content/60 hover:text-base-content">View JSON</summary>
                            <pre class="text-xs mt-2 p-2 bg-base-200 rounded overflow-x-auto">{{ json_encode($log->details, JSON_PRETTY_PRINT) }}</pre>
                        </details>
                    @endif
                </div>

                {{-- IP Address --}}
                <div class="text-center">
                    <div class="text-sm font-mono">{{ $log->ip ?? '—' }}</div>
                </div>

                {{-- Device --}}
                <div class="text-center">
                    @if($log->user_agent)
                        @php
                            $ua_os = UserAgentParser::parseOS($log->user_agent);
                            $ua_browser = UserAgentParser::parseBrowser($log->user_agent);
                        @endphp
                        <div class="text-xs leading-relaxed opacity-80">{!! $ua_os !!}</div>
                        <div class="text-xs leading-relaxed opacity-80">{!! $ua_browser !!}</div>
                    @else
                        <span class="text-xs opacity-50">—</span>
                    @endif
                </div>
            </div>

            {{-- Mobile/Tablet Layout --}}
            <div class="lg:hidden border-t border-base-300 p-4 hover:bg-base-200/40 transition">
                {{-- Header: User + Action + Time Badge --}}
                <div class="flex items-start gap-3 mb-3">
                    @if($user)
                        <div class="relative flex-shrink-0">
                            <img src="{{ $user->profile_picture_url }}" alt="Avatar" class="w-10 h-10 rounded-full object-cover"/>
                            @if($isOnline)
                                <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-base-100 rounded-full"></span>
                            @endif
                        </div>
                    @else
                        <div class="w-10 h-10 rounded-full bg-base-300 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="user-x" class="w-5 h-5 text-base-content/50"></i>
                        </div>
                    @endif

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="badge {{ $badgeColor }} badge-sm gap-1">
                                <i data-lucide="{{ $icon }}" class="w-3 h-3"></i>
                                {{ ucwords(str_replace('_', ' ', $log->action)) }}
                            </span>
                        </div>
                        @if($user)
                            <div class="font-medium text-sm">{{ $user->dname }}</div>
                            <div class="text-xs text-base-content/60">{{"@" . $user->username }}</div>
                        @else
                            <div class="text-xs text-base-content/50">User ID: {{ $log->user_id ?? 'Unknown' }}</div>
                        @endif
                    </div>

                    <div class="text-right flex-shrink-0">
                        <div class="text-xs font-medium">{{ $log->created_at->format('M d') }}</div>
                        <div class="text-xs text-base-content/60">{{ $log->created_at->format('h:i A') }}</div>
                        <div class="badge badge-ghost badge-xs mt-1">{{ $log->created_at->diffForHumans(null, true) }}</div>
                    </div>
                </div>

                {{-- Details --}}
                @if($log->details)
                    @php
                        $hasExtraJsonMobile = is_array($log->details) && (count($log->details) > 1 || !isset($log->details['message']));
                    @endphp
                    <div class="mb-3 p-3 bg-base-200 rounded-lg">
                        <div class="text-xs font-semibold text-base-content/50 uppercase mb-1">Details</div>
                        <div class="text-sm">{{ $log->readableDetails() }}</div>
                        @if($hasExtraJsonMobile)
                            <details class="cursor-pointer mt-2">
                                <summary class="text-xs text-base-content/60 hover:text-base-content">View JSON Data</summary>
                                <pre class="text-xs mt-2 p-2 bg-base-300 rounded overflow-x-auto">{{ json_encode($log->details, JSON_PRETTY_PRINT) }}</pre>
                            </details>
                        @endif
                    </div>
                @endif

                {{-- IP and Device Info --}}
                <div class="grid grid-cols-2 gap-3">
                    @if($log->ip)
                        <div>
                            <div class="text-xs font-semibold text-base-content/50 uppercase mb-1">IP Address</div>
                            <div class="text-sm font-mono">{{ $log->ip }}</div>
                        </div>
                    @endif

                    @if($log->user_agent)
                        <div>
                            <div class="text-xs font-semibold text-base-content/50 uppercase mb-1">Device</div>
                            @php
                                $ua_os = UserAgentParser::parseOS($log->user_agent);
                                $ua_browser = UserAgentParser::parseBrowser($log->user_agent);
                            @endphp
                            <div class="text-xs leading-relaxed opacity-80">{!! $ua_os !!}</div>
                            <div class="text-xs leading-relaxed opacity-80">{!! $ua_browser !!}</div>
                        </div>
                    @endif
                </div>
            </div>

        @empty
            <div class="px-4 py-12 text-center">
                <div class="inline-block p-4 bg-base-200 rounded-full mb-3">
                    <i data-lucide="database" class="w-8 h-8 text-base-content/50"></i>
                </div>
                <div class="text-sm font-medium mb-1">No system logs found</div>
                <div class="text-xs text-base-content/60">System activity will appear here</div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-6">{{ $logs->links() }}</div>
</x-admin.layout>
