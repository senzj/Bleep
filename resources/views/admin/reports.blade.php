@push('scripts')
    @vite('resources/js/admin/reports.js')
@endpush

<x-admin.layout>

    {{-- Header + Filters --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold">Reports</h1>

        <div class="flex flex-wrap gap-2">
            @foreach(['pending'=>'clock','reviewed'=>'eye','resolved'=>'check-circle'] as $state => $icon)
                <a href="?status={{ $state }}"
                   class="btn btn-sm {{ $status === $state ? 'btn-primary' : 'btn-ghost' }}">
                    <i data-lucide="{{ $icon }}" class="w-4 h-4 mr-1"></i>
                    {{ ucfirst($state) }}
                </a>
            @endforeach
        </div>
    </div>

    @if($reports->isEmpty())
        <div class="alert shadow-md">
            <i data-lucide="inbox" class="w-5 h-5"></i>
            <span>No {{ $status }} reports found.</span>
        </div>
    @else
        <div class="space-y-4">
            @foreach($reports as $report)
                <div class="card bg-base-100 shadow-sm border border-base-300">
                    <div class="card-body p-5 space-y-5">

                        {{-- TOP: Category + Time + mobile action toggle --}}
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
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

                            {{-- Mobile: toggle actions --}}
                            <button
                                class="md:hidden btn btn-xs btn-ghost gap-1"
                                type="button"
                                data-toggle-actions="{{ $report->id }}">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                Actions
                            </button>
                        </div>

                        {{-- MAIN CONTENT --}}
                        <div class="flex flex-col md:flex-row md:items-start md:gap-6">

                            {{-- LEFT CONTENT --}}
                            <div class="flex-1 min-w-0 space-y-4">

                                {{-- Reason --}}
                                <p class="text-sm leading-relaxed">
                                    <strong>Reason:</strong> {{ $report->reason }}
                                </p>

                                {{-- Reporter + OP --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
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
                                        <strong>Bleep:</strong>
                                        <p class="mt-1 opacity-80">
                                            {{ Str::limit($report->bleep->message, 200) }}
                                        </p>
                                        <a href="{{ route('post', $report->bleep->id) }}"
                                           class="link link-primary text-xs mt-2 inline-block"
                                           target="_blank">
                                            View full →
                                        </a>
                                    </div>
                                @else
                                    <div class="alert alert-warning text-xs">
                                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                        <span>Bleep deleted.</span>
                                    </div>
                                @endif

                                {{-- Resolved Info --}}
                                @if($report->status === 'resolved')
                                    <div class="text-xs opacity-70 leading-relaxed space-y-1">
                                        <div><strong>Action:</strong> {{ ucfirst(str_replace('_',' ',$report->action_taken ?? 'none')) }}</div>
                                        @if($report->notes)
                                            <div><strong>Notes:</strong> {{ $report->notes }}</div>
                                        @endif
                                        @if($report->reviewer)
                                            <div><strong>Reviewed by:</strong> {{ $report->reviewer->username }}</div>
                                        @endif
                                    </div>
                                @endif

                            </div>

                            {{-- RIGHT: Actions (desktop visible / mobile collapsible) --}}
                            <div class="md:w-[170px] mt-4 md:mt-0">
                                {{-- Wrapper with mobile collapse --}}
                                <div
                                    class="space-y-2 bg-base-200/60 p-3 rounded-lg border border-base-300 shadow-sm md:block hidden"
                                    data-actions-panel="{{ $report->id }}">

                                    @if($report->status === 'pending')
                                        <button class="btn btn-info btn-sm shadow-sm w-full mark-reviewed-btn"
                                                data-report-id="{{ $report->id }}">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                            Review
                                        </button>
                                        <div class="divider my-1 text-xs opacity-50">Action</div>
                                    @elseif($report->status === 'reviewed')
                                        <div class="text-xs opacity-60 mb-2">
                                            <i data-lucide="info" class="w-3 h-3 inline"></i>
                                            Reviewed
                                        </div>
                                    @endif

                                    @if(in_array($report->status, ['pending','reviewed']))
                                        @if($report->bleep)
                                            <button class="btn btn-error btn-sm shadow-sm w-full delete-bleep-btn"
                                                    data-report-id="{{ $report->id }}">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                Delete
                                            </button>

                                            <button class="btn btn-error btn-sm shadow-sm w-full ban-op-btn"
                                                    data-report-id="{{ $report->id }}"
                                                    data-user-id="{{ $report->bleep->user->id ?? '' }}">
                                                <i data-lucide="user-x" class="w-4 h-4"></i>
                                                Ban Poster
                                            </button>
                                        @endif

                                        <button class="btn btn-warning btn-sm shadow-sm w-full ban-reporter-btn"
                                                data-report-id="{{ $report->id }}"
                                                data-user-id="{{ $report->reporter->id ?? '' }}">
                                            <i data-lucide="flag" class="w-4 h-4"></i>
                                            Ban Reporter
                                        </button>

                                        <button class="btn btn-neutral btn-sm shadow-sm w-full dismiss-report-btn"
                                                data-report-id="{{ $report->id }}">
                                            <i data-lucide="x" class="w-4 h-4"></i>
                                            Dismiss
                                        </button>
                                    @endif

                                    @if($report->status === 'resolved')
                                        <div class="text-xs opacity-60">
                                            <i data-lucide="check-circle" class="w-3 h-3 inline"></i>
                                            Resolved
                                        </div>
                                    @endif
                                </div>
                            </div>

                        </div>

                        {{-- Mobile actions panel (hidden by default) --}}
                        <div
                            class="space-y-2 bg-base-200/60 p-3 rounded-lg border border-base-300 shadow-sm md:hidden hidden"
                            data-actions-panel-mobile="{{ $report->id }}">

                            @if($report->status === 'pending')
                                <button class="btn btn-info btn-sm shadow-sm w-full mark-reviewed-btn"
                                        data-report-id="{{ $report->id }}">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                    Review
                                </button>
                                <div class="divider my-1 text-xs opacity-50">Action</div>
                            @elseif($report->status === 'reviewed')
                                <div class="text-xs opacity-60 mb-2">
                                    <i data-lucide="info" class="w-3 h-3 inline"></i>
                                    Reviewed
                                </div>
                            @endif

                            @if(in_array($report->status, ['pending','reviewed']))
                                @if($report->bleep)
                                    <button class="btn btn-error btn-sm shadow-sm w-full delete-bleep-btn"
                                            data-report-id="{{ $report->id }}">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        Delete
                                    </button>

                                    <button class="btn btn-error btn-sm shadow-sm w-full ban-op-btn"
                                            data-report-id="{{ $report->id }}"
                                            data-user-id="{{ $report->bleep->user->id ?? '' }}">
                                        <i data-lucide="user-x" class="w-4 h-4"></i>
                                        Ban Poster
                                    </button>
                                @endif

                                <button class="btn btn-warning btn-sm shadow-sm w-full ban-reporter-btn"
                                        data-report-id="{{ $report->id }}"
                                        data-user-id="{{ $report->reporter->id ?? '' }}">
                                    <i data-lucide="flag" class="w-4 h-4"></i>
                                    Ban Reporter
                                </button>

                                <button class="btn btn-neutral btn-sm shadow-sm w-full dismiss-report-btn"
                                        data-report-id="{{ $report->id }}">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                    Dismiss
                                </button>
                            @endif

                            @if($report->status === 'resolved')
                                <div class="text-xs opacity-60">
                                    <i data-lucide="check-circle" class="w-3 h-3 inline"></i>
                                    Resolved
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

<x-modals.admin.ban />

@push('scripts')
<script>
document.addEventListener('click', e => {
    const tgl = e.target.closest('[data-toggle-actions]');
    if (tgl) {
        const id = tgl.getAttribute('data-toggle-actions');
        const panel = document.querySelector(`[data-actions-panel-mobile="${id}"]`);
        if (panel) panel.classList.toggle('hidden');
    }
});
</script>
@endpush
