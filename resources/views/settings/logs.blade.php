@php
    use App\Helpers\UserAgentParser;
@endphp

<x-settings.layout>
    <div class="mb-8">
        <h1 class="text-2xl font-bold mb-2">Your Activity Log</h1>
        <p class="text-sm text-base-content/70">Track all actions and events on your account</p>
    </div>

    {{-- Minimal Filters --}}
    <div class="bg-base-200 rounded-lg p-4 mb-6">
        <form method="GET" action="{{ route('settings.logs') }}" class="flex flex-col md:flex-row gap-3 items-stretch md:items-center">
            <input type="search" name="q" value="{{ old('q', $q ?? request('q')) }}" placeholder="Search action, details or IP" class="input input-sm w-full md:flex-1" />

            <select name="action" class="select select-sm w-full md:min-w-[180px] md:w-auto">
                <option value="">All Actions</option>
                @foreach($actions ?? [] as $a)
                    <option value="{{ $a }}" @if(($action ?? request('action')) === $a) selected @endif>{{ ucwords(str_replace('_',' ',$a)) }}</option>
                @endforeach
            </select>

            <div class="grid grid-cols-2 gap-2 md:flex md:gap-3">
                <input type="date" name="date_from" value="{{ old('date_from', $dateFrom ?? request('date_from')) }}" class="input input-sm" />
                <input type="date" name="date_to" value="{{ old('date_to', $dateTo ?? request('date_to')) }}" class="input input-sm" />
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-1 md:flex-none">Filter</button>
                <a href="{{ route('settings.logs') }}" class="btn btn-sm btn-ghost flex-1 md:flex-none">Reset</a>
            </div>
        </form>
    </div>

    {{-- Stats Overview --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-base-200 rounded-lg p-4">
            <div class="text-xs font-medium text-base-content/70 mb-1">Total Events</div>
            <div class="text-2xl font-bold">{{ $logs->total() }}</div>
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
            <div class="text-xs font-medium text-base-content/70 mb-1">This Month</div>
            <div class="text-2xl font-bold">{{ $logs->where('created_at', '>=', now()->subMonth())->count() }}</div>
        </div>
    </div>

    <div class="bg-base-100 border border-base-300 rounded-xl overflow-hidden">
        {{-- Desktop Header --}}
        <div class="hidden md:grid grid-cols-[1.5fr_1fr_2fr_1fr_1.5fr] gap-3 px-4 py-3 bg-base-200 text-[11px] uppercase tracking-wide font-semibold text-center">
            <div>Date & Time</div>
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
                ];
                $icon = $actionIcons[strtolower($log->action)] ?? 'activity';
            @endphp

            {{-- Desktop Layout --}}
            <div class="hidden md:grid grid-cols-[1.5fr_1fr_2fr_1fr_1.5fr] gap-3 px-4 py-4 border-t border-base-300 hover:bg-base-200/40 transition items-center">
                {{-- Date & Time --}}
                <div>
                    <div class="text-sm">{{ $log->created_at->format('M d, Y') }} | {{ $log->created_at->format('h:i:s A') }}</div>
                    <div class="text-xs text-base-content/50">{{ $log->created_at->diffForHumans() }}</div>
                </div>

                {{-- Action --}}
                <div class="text-center">
                    <span class="badge {{ $badgeColor }} badge-sm gap-1">
                        <i data-lucide="{{ $icon }}" class="w-3 h-3"></i>
                        {{ ucwords(str_replace('_', ' ', $log->action)) }}
                    </span>
                </div>

                {{-- Details --}}
                <div class="text-sm text-center">
                    {{ $log->readableDetails() }}
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

            {{-- Mobile Layout --}}
            <div class="md:hidden border-t border-base-300">
                <div class="p-4 hover:bg-base-200/40 transition">
                    <div class="flex items-start gap-3 mb-3">
                        <div class="bg-base-200 rounded-lg p-2 flex-shrink-0">
                            <i data-lucide="{{ $icon }}" class="w-5 h-5"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2 mb-1">
                                <div class="font-medium text-sm break-words">{{ ucwords(str_replace('_', ' ', $log->action)) }}</div>
                                <span class="badge {{ $badgeColor }} badge-sm flex-shrink-0 whitespace-nowrap">
                                    {{ $log->created_at->diffForHumans(null, true) }}
                                </span>
                            </div>
                            <div class="text-xs text-base-content/70 mb-2">{{ $log->created_at->format('M d, Y • h:i A') }}</div>

                            {{-- Short readable message --}}
                            @if($log->readableDetails())
                                <div class="text-sm text-base-content/90 break-words">
                                    {{ $log->readableDetails() }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Collapsible details --}}
                    @php
                        $hasExtraJsonMobile = is_array($log->details) && (count($log->details) > 1 || !isset($log->details['message']));
                    @endphp
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs text-base-content/60 hover:text-base-content flex items-center gap-2 select-none">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            <span>Show details</span>
                        </summary>

                        <div class="mt-3 space-y-3">
                            @if($log->ip)
                                <div class="p-3 bg-base-200 rounded-lg">
                                    <div class="text-xs font-semibold text-base-content/50 uppercase mb-1">IP Address</div>
                                    <div class="text-sm font-mono break-all">{{ $log->ip }}</div>
                                </div>
                            @endif

                            @if($log->user_agent)
                                @php
                                    $ua_os = UserAgentParser::parseOS($log->user_agent);
                                    $ua_browser = UserAgentParser::parseBrowser($log->user_agent);
                                @endphp
                                <div class="p-3 bg-base-200 rounded-lg">
                                    <div class="text-xs font-semibold text-base-content/50 uppercase mb-1">Device</div>
                                    <div class="text-sm leading-relaxed break-words">{!! $ua_browser !!}</div>
                                    <div class="text-xs leading-relaxed opacity-70 mt-1 break-words">{!! $ua_os !!}</div>
                                </div>
                            @endif

                            @if($hasExtraJsonMobile)
                                <div class="p-3 bg-base-300 rounded-lg">
                                    <div class="text-xs font-semibold text-base-content/50 uppercase mb-2">Raw Details</div>
                                    <pre class="text-xs p-2 bg-base-200 rounded overflow-x-auto whitespace-pre-wrap break-words max-w-full">{{ json_encode($log->details, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif
                        </div>
                    </details>
                </div>
            </div>

        @empty
            <div class="px-4 py-12 text-center">
                <div class="inline-block p-4 bg-base-200 rounded-full mb-3">
                    <i data-lucide="inbox" class="w-8 h-8 text-base-content/50"></i>
                </div>
                <div class="text-sm font-medium mb-1">No activity logs yet</div>
                <div class="text-xs text-base-content/60">Your account activity will appear here</div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-6">{{ $logs->links() }}</div>
</x-settings.layout>
