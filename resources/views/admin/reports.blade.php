@push('scripts')
    @vite([
        'resources/js/admin/reports.js',
        'resources/js/bleep/modals/mediamodal.js',
    ])
@endpush

<x-admin.layout>

{{-- Header --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold">Reports</h1>
        <p class="text-sm text-base-content/50 mt-0.5">Review and act on user-submitted reports</p>
    </div>

    {{-- Status tabs --}}
    <div class="flex gap-1 bg-base-200 p-1 rounded-xl">
        @php
            $tabs = [
                'pending'   => ['icon' => 'clock',        'color' => 'text-warning'],
                'dismissed' => ['icon' => 'x-circle',     'color' => 'text-info'],
                'resolved'  => ['icon' => 'check-circle', 'color' => 'text-success'],
            ];
        @endphp
        @foreach($tabs as $state => $cfg)
            <a href="?status={{ $state }}"
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-all
                      {{ $status === $state
                            ? 'bg-base-100 shadow-sm text-base-content'
                            : 'text-base-content/50 hover:text-base-content' }}">
                <i data-lucide="{{ $cfg['icon'] }}" class="w-3.5 h-3.5 {{ $status === $state ? $cfg['color'] : '' }}"></i>
                {{ ucfirst($state) }}
            </a>
        @endforeach
    </div>
</div>

@if($reports->isNotEmpty())

    <div class="space-y-3">
        @foreach($reports as $report)
        @php
            $isComment = $report->reportable instanceof \App\Models\Comments;
            $isBleep   = $report->reportable instanceof \App\Models\Bleep;
            $target = $report->reportable;
            $contentUser = $target?->user ?? null;

            $categoryClass = match($report->category) {
                'harassment', 'hate', 'illegal' => 'bg-[#FCEBEB] text-[#791F1F] border-[#F09595]',
                'spam', 'nsfw' => 'bg-[#FAEEDA] text-[#633806] border-[#EF9F27]',
                default => 'bg-[#EEF0F2] text-[#435164] border-[#BBC4D0]',
            };

            $statusClass = match($report->status) {
                'pending'   => 'bg-[#FAEEDA] text-[#633806] border-[#EF9F27]',
                'dismissed' => 'bg-[#EEF0F2] text-[#435164] border-[#BBC4D0]',
                default     => 'bg-[#E1F5EE] text-[#085041] border-[#5DCAA5]',
            };

            $isNsfwFlag = (bool) ($target?->is_nsfw ?? false) || $report->category === 'nsfw';
            $previewUsername = $contentUser?->username ?? 'deleted';
            $previewInitials = strtoupper(substr($previewUsername, 0, 2));
            $previewAgo = $target?->created_at?->diffForHumans() ?? null;

            $bleepLikeCount = (int) ($target?->likes_count ?? 0);
            $bleepCommentCount = (int) ($target?->comments_count ?? 0);
            $bleepRepostCount = (int) ($target?->reposts_count ?? 0);
            $bleepShareCount = (int) ($target?->shares_count ?? 0);
            $bleepViewCount = (int) ($target?->views ?? 0);

            $commentLikeCount = (int) ($target?->likes_count ?? 0);
            $commentReplyCount = (int) ($target?->replies_count ?? 0);
            $commentParentViews = (int) ($target?->bleep?->views ?? 0);

            $bleepMediaItems = collect();
            if ($isBleep) {
                $bleepMediaItems = $target->media ?? collect();

                if ($bleepMediaItems->isEmpty() && !empty($target->media_path)) {
                    $mime = $target->media_mime ?? null;
                    $mediaType = $target->media_type
                        ?? (is_string($mime) && str_starts_with($mime, 'video/') ? 'video' : 'image');

                    $bleepMediaItems = collect([(object) [
                        'type' => $mediaType,
                        'path' => $target->media_path,
                        'original_name' => basename($target->media_path),
                        'mime_type' => $mime ?? 'image/jpeg',
                    ]]);
                }
            }

            $commentMedia = null;
            if ($isComment && !empty($target?->media_path)) {
                $mime = $target->media_mime ?? null;
                $mediaType = $target->media_type
                    ?? (is_string($mime) && str_starts_with($mime, 'video/') ? 'video'
                        : (is_string($mime) && str_starts_with($mime, 'audio/') ? 'audio' : 'image'));

                $commentMedia = [
                    'type' => $mediaType,
                    'path' => $target->media_path,
                    'original_name' => basename($target->media_path),
                    'mime_type' => $mime ?? 'image/jpeg',
                ];
            }
        @endphp
        <div class="overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-sm transition-shadow hover:shadow-md"
             id="report-card-{{ $report->id }}">
            <div class="card-body p-0">

                {{-- Card header strip --}}
                <div class="flex items-center justify-between gap-3 border-b border-base-300 bg-base-200 px-4 py-3">
                    <div class="flex items-center gap-2 flex-wrap">

                        {{-- Type badge --}}
                        <span class="badge badge-sm gap-1 border {{ $isComment ? 'bg-[#E6F1FB] text-[#0C447C] border-[#85B7EB]' : 'bg-[#E6F5EE] text-[#075845] border-[#7BCFAF]' }}">
                            <i data-lucide="{{ $isComment ? 'message-circle' : 'file-text' }}" class="w-3 h-3"></i>
                            {{ $isComment ? 'Comment' : 'Bleep' }}
                        </span>

                        {{-- Category badge --}}
                        @if(!$isNsfwFlag || $report->category !== 'nsfw')
                            <span class="badge badge-sm border {{ $categoryClass }}">
                                {{ ucfirst($report->category) }}
                            </span>
                        @endif

                        @if($isNsfwFlag)
                            <span class="badge badge-sm border bg-[#FCEBEB] text-[#791F1F] border-[#F09595]">NSFW</span>
                        @endif

                        {{-- Status badge --}}
                        <span class="badge badge-sm border {{ $statusClass }}">
                            {{ ucfirst($report->status) }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-xs text-base-content/40">
                            {{ $report->created_at->diffForHumans() }}
                        </span>

                        {{-- Mobile toggle --}}
                        <button type="button"
                                class="md:hidden btn btn-xs btn-ghost btn-circle"
                                data-toggle-actions="{{ $report->id }}">
                            <i data-lucide="more-vertical" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                {{-- Card body --}}
                <div class="flex min-h-80 flex-col divide-y md:flex-row md:divide-y-0">

                    {{-- LEFT: Report details --}}
                    <div class="flex-1 min-w-0 p-4 md:p-5 space-y-4">

                        {{-- Reporter + Content author --}}
                        <div class="flex flex-col gap-2 text-xs">

                            {{-- Reporter(s) --}}
                            <div class="flex items-start gap-1.5">
                                <i data-lucide="flag" class="w-3.5 h-3.5 text-base-content/40 mt-0.5 shrink-0"></i>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-base-content/50">Reported by</span>

                                    @php
                                        $reporters = $report->all_reporters ?? collect();
                                        $reportCount = $report->report_count ?? 1;
                                    @endphp

                                    @if($reporters->count() === 1)
                                        {{-- Single reporter: show inline --}}
                                        @if($reporters->first()?->reporter)
                                            <a href="{{ route('user.profile', $reporters->first()->reporter->username) }}"
                                            class="font-semibold link link-primary">
                                                {{ '@' . $reporters->first()->reporter->username }}
                                            </a>
                                        @else
                                            <span class="text-base-content/30">[Deleted]</span>
                                        @endif

                                    @elseif($reporters->count() > 1)
                                        {{-- Multiple reporters: badge + collapsible --}}
                                        <button type="button"
                                                class="badge badge-warning badge-outline gap-1 cursor-pointer hover:badge-warning transition-colors"
                                                onclick="document.getElementById('reporters-{{ $report->id }}').classList.toggle('hidden')">
                                            <i data-lucide="users" class="w-2.5 h-2.5"></i>
                                            {{ $reportCount }} reporters
                                            <i data-lucide="chevron-down" class="w-2.5 h-2.5"></i>
                                        </button>

                                        <div id="reporters-{{ $report->id }}" class="hidden w-full mt-2">
                                            <div class="rounded-lg border border-base-300 bg-base-200/50 divide-y divide-base-300 overflow-hidden">
                                                @foreach($reporters as $r)
                                                    @if($r->reporter)
                                                        <div class="flex items-center justify-between px-3 py-2 gap-2">
                                                            <a href="{{ route('user.profile', $r->reporter->username) }}"
                                                            class="font-medium link link-primary text-xs">
                                                                {{ '@' . $r->reporter->username }}
                                                            </a>
                                                            <div class="flex items-center gap-2 shrink-0">
                                                                @if($r->category)
                                                                    <span class="badge badge-xs badge-outline">{{ ucfirst($r->category) }}</span>
                                                                @endif
                                                                <span class="text-xs text-base-content/40">{{ $r->created_at->diffForHumans() }}</span>
                                                            </div>
                                                        </div>
                                                        @if($r->reason)
                                                            <div class="px-3 pb-2 text-xs text-base-content/50 italic">
                                                                "{{ $r->reason }}"
                                                            </div>
                                                        @endif
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Author --}}
                            <div class="flex items-center gap-1.5">
                                <i data-lucide="user" class="w-3.5 h-3.5 text-base-content/40"></i>
                                <span class="text-base-content/50">Author</span>
                                @if($contentUser)
                                    <a href="{{ route('user.profile', $contentUser->username) }}"
                                    class="font-semibold link link-primary">
                                        {{ '@' . $contentUser->username }}
                                    </a>
                                    {{-- Strike info inline --}}
                                    @php $strikes = $contentUser->report_strikes ?? 0; @endphp
                                    <span class="text-base-content/30">·</span>
                                    <span class="font-medium {{ $strikes >= 2 ? 'text-error' : ($strikes >= 1 ? 'text-warning' : 'text-base-content/40') }}">
                                        {{ $strikes }}/3 strikes
                                    </span>
                                    @if(($contentUser->suspension_count ?? 0) > 0)
                                        <span class="badge badge-xs badge-error badge-outline">
                                            {{ $contentUser->suspension_count }}× suspended
                                        </span>
                                    @endif
                                @else
                                    <span class="text-base-content/30">[Deleted]</span>
                                @endif
                            </div>

                            {{-- Reviewer --}}
                            @if($report->reviewer)
                                <div class="flex items-center gap-1.5">
                                    <i data-lucide="shield-check" class="w-3.5 h-3.5 text-base-content/40"></i>
                                    <span class="text-base-content/50">Reviewed by</span>
                                    <span class="font-semibold">{{ $report->reviewer->username }}</span>
                                    @if($report->reviewed_at)
                                        <span class="text-base-content/30">· {{ $report->reviewed_at->diffForHumans() }}</span>
                                    @endif
                                </div>
                            @endif

                        </div>

                        {{-- Reason --}}
                        <div class="text-sm">
                            <span class="text-base-content/50 text-xs uppercase tracking-wide font-semibold">Reason</span>
                            <p class="mt-1 text-base-content/50 leading-relaxed {{ $report->reason ? '' : 'italic' }}">{{ $report->reason ?? 'No additional information provided.' }}</p>
                        </div>

                        {{-- Reported content --}}
                        @php
                            $target = $report->reportable;
                        @endphp

                        @if($target)
                            <div class="space-y-2">
                                <p class="text-sm mb-1">
                                    <span class="text-base-content/50 text-xs uppercase tracking-wide font-semibold">Content Preview</span>
                                </p>

                                <div class="rounded-lg">
                                    @if($isComment)
                                        <div class="space-y-3">
                                            <div class="overflow-hidden rounded-lg border border-base-300/80">
                                                <div class="border-b border-base-300 bg-base-100/30 px-3 py-3">
                                                    <p class="text-[13px] text-base-content/90 leading-relaxed">{{ Str::limit($target->message, 300) }}</p>
                                                </div>

                                                @if($commentMedia)
                                                    <div class="overflow-hidden" data-bleep-media>
                                                        @if(in_array($commentMedia['type'], ['image', 'video']))
                                                            <div class="relative" data-media-index="0" data-media-type="{{ $commentMedia['type'] }}" data-media-src="{{ $commentMedia['type'] === 'video' ? route('media.stream', ['path' => $commentMedia['path']]) : asset('storage/' . ltrim($commentMedia['path'], '/')) }}" data-media-alt="{{ $commentMedia['original_name'] }}" data-media-mime="{{ $commentMedia['mime_type'] }}">
                                                                @if($commentMedia['type'] === 'image')
                                                                    <img src="{{ asset('storage/' . ltrim($commentMedia['path'], '/')) }}"
                                                                        alt="{{ $commentMedia['original_name'] }}"
                                                                        class="h-auto max-h-72 w-full cursor-zoom-in object-contain bg-black/5"
                                                                        loading="lazy">
                                                                @else
                                                                    <video controls preload="metadata" class="w-full max-h-72 bg-black object-contain">
                                                                        <source src="{{ route('media.stream', ['path' => $commentMedia['path']]) }}"
                                                                                type="{{ $commentMedia['mime_type'] }}">
                                                                    </video>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <div class="p-4">
                                                                <audio controls class="w-full">
                                                                    <source src="{{ route('media.stream', ['path' => $commentMedia['path']]) }}"
                                                                            type="{{ $commentMedia['mime_type'] }}">
                                                                </audio>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif

                                                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-base-300 bg-base-100 px-3 py-2">
                                                    <div class="flex flex-wrap items-center gap-3">
                                                        <span class="inline-flex items-center gap-1 text-xs text-base-content/60"><i data-lucide="heart" class="h-3.5 w-3.5"></i>{{ number_format($commentLikeCount) }} likes</span>
                                                        <span class="inline-flex items-center gap-1 text-xs text-base-content/60"><i data-lucide="message-circle" class="h-3.5 w-3.5"></i>{{ number_format($commentReplyCount) }} replies</span>
                                                        <span class="inline-flex items-center gap-1 text-xs text-base-content/60"><i data-lucide="eye" class="h-3.5 w-3.5"></i>{{ number_format($commentParentViews) }} views</span>
                                                    </div>
                                                    <span class="text-xs text-base-content/50">Posted {{ $previewAgo }}</span>
                                                </div>
                                            </div>
                                        </div>

                                    @elseif($isBleep)
                                        <div class="space-y-3">
                                            <div class="overflow-hidden rounded-lg border border-base-300/80">
                                                <div class="border-b border-base-300 bg-base-200 px-3 py-3">
                                                    <p class="text-[13px] text-base-content/90 leading-relaxed">{{ Str::limit($target->message, 300) }}</p>
                                                </div>

                                                @if($bleepMediaItems->count() > 0)
                                                    <div class="overflow-hidden" data-bleep-media>
                                                    <div class="grid grid-cols-1 gap-2 p-2 {{ $bleepMediaItems->count() > 1 ? 'sm:grid-cols-2' : '' }}">
                                                        @foreach($bleepMediaItems as $index => $media)
                                                            @php
                                                                $mediaPath = ltrim($media->path, '/');
                                                                $mediaUrl = ($media->type === 'video')
                                                                    ? route('media.stream', ['path' => $mediaPath])
                                                                    : asset('storage/' . $mediaPath);
                                                            @endphp
                                                            @if(in_array($media->type, ['image', 'video']))
                                                                <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100"
                                                                    data-media-index="{{ $index }}"
                                                                    data-media-type="{{ $media->type }}"
                                                                    data-media-src="{{ $mediaUrl }}"
                                                                    data-media-alt="{{ $media->original_name }}"
                                                                    data-media-mime="{{ $media->mime_type }}">
                                                                    @if($media->type === 'image')
                                                                        <img src="{{ $mediaUrl }}"
                                                                            alt="{{ $media->original_name }}"
                                                                            class="h-auto max-h-72 w-full cursor-zoom-in object-contain bg-black/5"
                                                                            loading="lazy">
                                                                    @else
                                                                        <video controls preload="metadata" class="w-full max-h-72 bg-black object-contain">
                                                                            <source src="{{ $mediaUrl }}" type="{{ $media->mime_type }}">
                                                                        </video>
                                                                    @endif
                                                                </div>
                                                            @else
                                                                <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100 p-4">
                                                                    <audio controls class="w-full">
                                                                        <source src="{{ $mediaUrl }}" type="{{ $media->mime_type }}">
                                                                    </audio>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                </div>
                                                @endif

                                                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-base-300 bg-base-100 px-3 py-2">
                                                    <div class="flex flex-wrap items-center gap-3">
                                                        <span class="inline-flex items-center gap-1 text-xs text-base-content/60"><i data-lucide="heart" class="h-3.5 w-3.5"></i>{{ number_format($bleepLikeCount) }} likes</span>
                                                        <span class="inline-flex items-center gap-1 text-xs text-base-content/60"><i data-lucide="message-circle" class="h-3.5 w-3.5"></i>{{ number_format($bleepCommentCount) }} comments</span>
                                                        <span class="inline-flex items-center gap-1 text-xs text-base-content/60"><i data-lucide="repeat" class="h-3.5 w-3.5"></i>{{ number_format($bleepRepostCount) }} reposts</span>
                                                        <span class="inline-flex items-center gap-1 text-xs text-base-content/60"><i data-lucide="forward" class="h-3.5 w-3.5"></i>{{ number_format($bleepShareCount) }} shares</span>
                                                        <span class="inline-flex items-center gap-1 text-xs text-base-content/60"><i data-lucide="eye" class="h-3.5 w-3.5"></i>{{ number_format($bleepViewCount) }} views</span>
                                                    </div>
                                                    <span class="text-xs text-base-content/50">Posted {{ $previewAgo }} </span>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="alert alert-warning py-2 text-xs">
                                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                Content has been deleted.
                            </div>
                        @endif

                        {{-- Resolution info --}}
                        @if($report->status === 'resolved')
                            <div class="rounded-xl border border-success/30 bg-success/5 p-3 text-xs space-y-1">
                                <div class="flex items-center gap-1.5 font-semibold text-success">
                                    <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                                    Resolved
                                </div>
                                <div><span class="text-base-content/50">Action:</span> {{ ucfirst(str_replace('_', ' ', $report->action_taken ?? 'none')) }}</div>
                                @if($report->notes)
                                    <div><span class="text-base-content/50">Notes:</span> {{ $report->notes }}</div>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- RIGHT: Actions panel (desktop) --}}
                    <div class="hidden w-px bg-base-300 md:block"></div>

                    <div class="md:w-45 shrink-0 p-4 hidden md:flex flex-col gap-2"
                         data-actions-panel="{{ $report->id }}">

                        @include('admin.partials.report.actions', ['report' => $report, 'isComment' => $isComment])
                    </div>
                </div>

                {{-- Mobile actions panel --}}
                <div class="hidden p-4 border-t border-base-200 flex-col gap-2"
                     data-actions-panel-mobile="{{ $report->id }}">
                    @include('admin.partials.report.actions', ['report' => $report, 'isComment' => $isComment])
                </div>

            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $reports->links() }}
    </div>
@else
    <div class="flex flex-col items-center justify-center py-20 text-base-content">
        <i data-lucide="inbox" class="w-12 h-12 mb-3"></i>
        <p class="text-base font-medium">No {{ $status }} reports</p>
        <p class="text-sm mt-1">Everything looks clean here.</p>
    </div>
@endif

</x-admin.layout>

<x-modals.admin.ban />
<x-subcomponents.bleeps.mediamodal />
