@push('scripts')
    @vite('resources/js/admin/reports.js')
@endpush

<x-admin.layout>

    {{-- Header + Filters --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Reports</h1>

        {{-- Clicky Button Filters --}}
        <div class="flex gap-2">
            <a href="?status=pending"
               class="btn btn-sm {{ $status === 'pending' ? 'btn-primary' : 'btn-ghost' }}">
                <i data-lucide="clock" class="w-4 h-4 mr-1"></i>
                Pending
            </a>
            <a href="?status=reviewed"
               class="btn btn-sm {{ $status === 'reviewed' ? 'btn-primary' : 'btn-ghost' }}">
                <i data-lucide="eye" class="w-4 h-4 mr-1"></i>
                Reviewed
            </a>
            <a href="?status=resolved"
               class="btn btn-sm {{ $status === 'resolved' ? 'btn-primary' : 'btn-ghost' }}">
                <i data-lucide="check-circle" class="w-4 h-4 mr-1"></i>
                Resolved
            </a>
        </div>
    </div>

    {{-- Empty State --}}
    @if($reports->isEmpty())
        <div class="alert shadow-md">
            <i data-lucide="inbox" class="w-5 h-5"></i>
            <span>No {{ $status }} reports found.</span>
        </div>

    @else

        {{-- Report List --}}
        <div class="space-y-4">

            @foreach($reports as $report)
                <div class="card bg-base-100 shadow-sm border border-base-300">
                    <div class="card-body p-5">

                        <div class="flex items-start justify-between gap-6">

                            {{-- LEFT CONTENT --}}
                            <div class="flex-1 min-w-0">

                                {{-- Badge + Timestamp --}}
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="badge badge-sm
                                        {{ $report->category === 'spam' ? 'badge-warning' : '' }}
                                        {{ in_array($report->category, ['harassment','hate','illegal']) ? 'badge-error' : '' }}
                                        {{ $report->category === 'nsfw' ? 'badge-warning' : '' }}
                                        {{ $report->category === 'other' ? 'badge-neutral' : '' }}">
                                        {{ ucfirst($report->category) }}
                                    </span>

                                    <span class="text-xs opacity-60">
                                        {{ $report->created_at->diffForHumans() }}
                                    </span>
                                </div>

                                {{-- Reason --}}
                                <p class="text-sm mb-4 leading-relaxed">
                                    <strong>Reason:</strong> {{ $report->reason }}
                                </p>

                                {{-- Reporter + OP --}}
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs mb-4">
                                    <div>
                                        <strong>Reported by:</strong>
                                        @if($report->reporter)
                                            <a href="{{ route('user.profile', $report->reporter->username) }}"
                                               class="link link-primary">
                                                {{ $report->reporter->username }}
                                            </a>
                                        @else
                                            <span class="opacity-50">[Deleted User]</span>
                                        @endif
                                    </div>
                                    <div>
                                        <strong>Posted by:</strong>
                                        @if($report->bleep && $report->bleep->user)
                                            <a href="{{ route('user.profile', $report->bleep->user->username) }}"
                                               class="link link-primary">
                                                {{ $report->bleep->user->username }}
                                            </a>
                                        @else
                                            <span class="opacity-50">[Deleted User]</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Bleep Content --}}
                                @if($report->bleep)
                                    <div class="p-4 bg-base-200 border border-base-300 rounded-lg text-sm">
                                        <strong>Bleep Content:</strong>
                                        <p class="mt-1 opacity-80">
                                            {{ Str::limit($report->bleep->message, 200) }}
                                        </p>
                                        <a href="{{ route('post', $report->bleep->id) }}"
                                           class="link link-primary text-xs mt-2 inline-block"
                                           target="_blank">
                                            View bleep →
                                        </a>
                                    </div>
                                @else
                                    <div class="alert alert-warning text-xs">
                                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                        <span>This bleep has been deleted.</span>
                                    </div>
                                @endif

                                {{-- Resolved Info --}}
                                @if($report->status === 'resolved')
                                    <div class="mt-3 text-xs opacity-70 leading-relaxed">
                                        <strong>Action:</strong>
                                        {{ ucfirst(str_replace('_',' ',$report->action_taken ?? 'none')) }}<br>

                                        @if($report->notes)
                                            <strong>Notes:</strong> {{ $report->notes }}<br>
                                        @endif

                                        @if($report->reviewer)
                                            <strong>Reviewed by:</strong> {{ $report->reviewer->username }}
                                        @endif
                                    </div>
                                @endif

                            </div>

                            {{-- RIGHT: Button Actions --}}
                            @if($report->status === 'pending' && $report->bleep)
                                <div class="flex flex-col gap-2 bg-base-200/60 p-3 rounded-lg border border-base-300 shadow-sm min-w-[150px]">

                                    {{-- NEW: Mark as Reviewed button --}}
                                    <button class="btn btn-info btn-sm shadow-sm mark-reviewed-btn"
                                            data-report-id="{{ $report->id }}">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                        Mark Reviewed
                                    </button>

                                    <div class="divider my-1 text-xs opacity-50">or take action</div>

                                    <button class="btn btn-error btn-sm shadow-sm delete-bleep-btn"
                                            data-report-id="{{ $report->id }}">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        Delete
                                    </button>

                                    <button class="btn btn-error btn-sm shadow-sm ban-op-btn"
                                            data-report-id="{{ $report->id }}"
                                            data-user-id="{{ $report->bleep->user->id ?? '' }}">
                                        <i data-lucide="user-x" class="w-4 h-4"></i>
                                        Ban Poster
                                    </button>

                                    <button class="btn btn-warning btn-sm shadow-sm ban-reporter-btn"
                                            data-report-id="{{ $report->id }}"
                                            data-user-id="{{ $report->reporter->id ?? '' }}">
                                        <i data-lucide="flag" class="w-4 h-4"></i>
                                        Ban Reporter
                                    </button>

                                    <button class="btn btn-neutral btn-sm shadow-sm dismiss-report-btn"
                                            data-report-id="{{ $report->id }}">
                                        <i data-lucide="x" class="w-4 h-4"></i>
                                        Dismiss
                                    </button>

                                </div>
                            @elseif($report->status === 'pending' && !$report->bleep)
                                <div class="alert alert-warning text-xs">
                                    <i data-lucide="info" class="w-4 h-4"></i>
                                    <span>Bleep deleted, awaiting dismissal</span>
                                    <button class="btn btn-neutral btn-xs dismiss-report-btn"
                                            data-report-id="{{ $report->id }}">
                                        Dismiss
                                    </button>
                                </div>
                            @endif

                            {{-- Show action buttons for reviewed reports too --}}
                            @if($report->status === 'reviewed')
                                <div class="flex flex-col gap-2 bg-base-200/60 p-3 rounded-lg border border-base-300 shadow-sm min-w-[150px]">
                                    <div class="text-xs opacity-60 mb-2">
                                        <i data-lucide="info" class="w-3 h-3 inline"></i>
                                        Reviewed, awaiting action
                                    </div>

                                    <button class="btn btn-error btn-sm shadow-sm delete-bleep-btn"
                                            data-report-id="{{ $report->id }}">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        Delete
                                    </button>

                                    <button class="btn btn-error btn-sm shadow-sm ban-op-btn"
                                            data-report-id="{{ $report->id }}"
                                            data-user-id="{{ $report->bleep->user->id ?? '' }}">
                                        <i data-lucide="user-x" class="w-4 h-4"></i>
                                        Ban Poster
                                    </button>

                                    <button class="btn btn-warning btn-sm shadow-sm ban-reporter-btn"
                                            data-report-id="{{ $report->id }}"
                                            data-user-id="{{ $report->reporter->id ?? '' }}">
                                        <i data-lucide="flag" class="w-4 h-4"></i>
                                        Ban Reporter
                                    </button>

                                    <button class="btn btn-neutral btn-sm shadow-sm dismiss-report-btn"
                                            data-report-id="{{ $report->id }}">
                                        <i data-lucide="x" class="w-4 h-4"></i>
                                        Dismiss
                                    </button>
                                </div>
                            @endif

                        </div>
                    </div>
                </div>
            @endforeach

        </div>

        <div class="mt-6">
            {{ $reports->links() }}
        </div>
    @endif

</x-admin.layout>

{{-- Ban User Modal --}}
<x-modals.admin.ban />
