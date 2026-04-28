@php
    $target = $report->reportable;
    $contentExists = !is_null($target);
    $contentUser = $target?->user;
    $originalUrl = null;
    if ($contentExists) {
        if ($isComment && isset($target->bleep_id)) {
            $originalUrl = route('post', $target->bleep_id);
        }
        if (!$isComment) {
            $originalUrl = route('post', $target->id);
        }
    }
@endphp

@if($report->status === 'resolved')
    <div class="flex flex-col items-center justify-center h-full gap-2 text-success/70 py-4">
        <i data-lucide="check-circle-2" class="w-8 h-8"></i>
        <span class="text-xs font-medium">Resolved</span>
        @if($report->reviewed_at)
            <span class="text-xs text-base-content/30">{{ $report->reviewed_at->format('M j, Y') }}</span>
        @endif
    </div>
@else
    <p class="text-xs font-semibold text-base-content/40 uppercase tracking-wide mb-1">Actions</p>

    <button class="btn btn-sm w-full justify-start border-success/40 bg-success/10 text-success hover:bg-success/20 dismiss-report-btn"
            data-report-id="{{ $report->id }}">
        <i data-lucide="check" class="w-4 h-4"></i>
        Mark Resolved
    </button>

    @if(in_array($report->status, ['pending']))
        <button class="btn btn-sm w-full justify-start btn-outline mark-reviewed-btn"
                data-report-id="{{ $report->id }}">
            <i data-lucide="x-circle" class="w-4 h-4"></i>
            Dismiss Report
        </button>
    @endif

    @if($originalUrl)
        <a href="{{ $originalUrl }}" target="_blank" class="btn btn-sm w-full justify-start btn-outline">
            <i data-lucide="link" class="w-4 h-4"></i>
            View Original
        </a>
    @endif

    <div class="divider my-1"></div>

    @if($contentExists)
        @if($isComment)
            <button class="btn btn-sm w-full justify-start border-error/40 bg-error/10 text-error hover:bg-error/20 delete-comment-btn"
                    data-report-id="{{ $report->id }}"
                    data-comment-id="{{ $target->id }}">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                Delete Comment
            </button>
        @else
            <button class="btn btn-sm w-full justify-start border-error/40 bg-error/10 text-error hover:bg-error/20 delete-bleep-btn"
                    data-report-id="{{ $report->id }}">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                Delete Bleep
            </button>
        @endif
    @endif

    @if($contentUser)
        <button class="btn btn-sm w-full justify-start border-error/40 bg-error/10 text-error hover:bg-error/20 ban-op-btn"
                data-report-id="{{ $report->id }}"
                data-user-id="{{ $contentUser->id }}">
            <i data-lucide="ban" class="w-4 h-4"></i>
            Ban User
        </button>
    @endif
@endif
