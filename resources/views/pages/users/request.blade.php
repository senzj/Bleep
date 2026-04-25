@push('scripts')
    @vite([
        'resources/js/bleep/users/follow-requests.js',
        'resources/js/bleep/users/follow.js',
    ])
@endpush

<x-layout>
    <x-slot:title>Follow Requests</x-slot:title>

    <div class="container mx-auto px-4 max-w-5xl">
        <div class="bg-base-100 rounded-lg shadow-lg border border-base-300 p-6">
            <div class="flex items-center mb-6">
                <i data-lucide="user-plus" class="w-6 h-6 mr-3"></i>
                <h1 class="text-2xl font-bold">Follow Requests</h1>
            </div>

            @if ($requests && $requests->isNotEmpty())
                @php
                    $viewerTimezone = Auth::user()?->timezone ?: config('app.timezone');
                    $groupedRequests = $requests->groupBy(fn ($request) => $request->created_at->copy()->timezone($viewerTimezone)->format('Y-m-d'));
                @endphp

                <div class="space-y-6" id="follow-requests-groups">
                    @foreach ($groupedRequests as $dateKey => $dayRequests)
                        @php
                            $headerDate = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $dateKey, $viewerTimezone)->format('F j, Y');
                            // Sort requests by status: pending first, then accepted, then rejected
                            $sortedRequests = $dayRequests->sortBy(function($req) {
                                return match($req->status) {
                                    'pending' => 1,
                                    'accepted' => 2,
                                    'rejected' => 3,
                                    default => 4
                                };
                            });
                        @endphp

                        <section class="space-y-3 follow-requests-day-group" data-day-group="{{ $dateKey }}">
                            <h2 class="text-sm font-semibold text-base-content/70">{{ $headerDate }}</h2>

                            @foreach ($sortedRequests as $request)
                                @php
                                    $requestTime = $request->created_at->copy()->timezone($viewerTimezone)->format('g:i:s A');
                                    $isFollowingBack = Auth::user()->isFollowing($request->requester);
                                    $isRejected = $request->status === 'rejected';
                                    $isAccepted = $request->status === 'accepted';
                                    $isPending = $request->status === 'pending';

                                    // Calculate if rejection is within 24 hours
                                    $rejectionAge = $request->updated_at->diffInHours(now());
                                    $isRecentlyRejected = $isRejected && $rejectionAge < 24;
                                @endphp
                                <div class="p-4 rounded-lg border border-base-300 {{ $isPending ? 'hover:bg-base-200' : '' }} transition-colors {{ $isRejected ? 'opacity-60' : '' }}"
                                     data-request-id="{{ $request->id }}"
                                     data-requester-id="{{ $request->requester->id }}"
                                     data-requester-username="{{ $request->requester->username }}"
                                     data-is-following-back="{{ $isFollowingBack ? '1' : '0' }}"
                                     data-status="{{ $request->status }}">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <a href="{{ route('user.profile', ['username' => $request->requester->username]) }}" class="shrink-0">
                                                <img src="{{ $request->requester->profile_picture_url }}"
                                                    alt="{{ $request->requester->dname }}'s Avatar"
                                                    class="w-12 h-12 rounded-full object-cover hover:ring-2 hover:ring-primary transition-all">
                                            </a>

                                            <div class="min-w-0">
                                                <a href="{{ route('user.profile', ['username' => $request->requester->username]) }}" class="block hover:text-primary">
                                                    <p class="font-semibold truncate">{{ $request->requester->dname ?? $request->requester->username }}</p>
                                                    <p class="text-sm text-base-content/60 truncate">{{ "@" . $request->requester->username }}</p>
                                                </a>
                                            </div>
                                        </div>

                                        <div class="flex gap-2 shrink-0 request-actions">
                                            @if ($isPending)
                                                <button type="button"
                                                    class="btn btn-sm btn-primary accept-request-btn"
                                                    data-request-id="{{ $request->id }}"
                                                    title="Accept follow request">
                                                    <i data-lucide="check" class="w-4 h-4"></i>
                                                    Accept
                                                </button>
                                                <button type="button"
                                                    class="btn btn-sm btn-ghost reject-request-btn"
                                                    data-request-id="{{ $request->id }}"
                                                    title="Reject follow request">
                                                    <i data-lucide="x" class="w-4 h-4"></i>
                                                    Reject
                                                </button>
                                            @elseif ($isAccepted)
                                                @if (!$isFollowingBack)
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline gap-2 follow-btn"
                                                        data-user-id="{{ $request->requester->id }}"
                                                        data-following="0"
                                                        title="Follow this user back">
                                                        <i data-lucide="user-plus" class="w-4 h-4 follow-icon"></i>
                                                        <span class="follow-text">Follow back</span>
                                                        <span class="unfollow-text hidden">Unfollow</span>
                                                    </button>
                                                @else
                                                    <button type="button"
                                                        class="btn btn-sm btn-primary gap-2 follow-btn"
                                                        data-user-id="{{ $request->requester->id }}"
                                                        data-following="1"
                                                        title="Following this user">
                                                        <i data-lucide="user-check" class="w-4 h-4 follow-icon"></i>
                                                        <span class="follow-text">Following</span>
                                                        <span class="unfollow-text hidden">Unfollow</span>
                                                    </button>
                                                @endif
                                            @elseif ($isRejected)
                                                @if ($isRecentlyRejected)
                                                    <button type="button"
                                                        class="btn btn-sm btn-disabled cursor-not-allowed opacity-50"
                                                        disabled
                                                        title="Cannot follow for 24 hours after rejection">
                                                        <i data-lucide="user-x" class="w-4 h-4"></i>
                                                        Rejected
                                                    </button>
                                                @else
                                                    <button type="button"
                                                        class="btn btn-sm btn-ghost gap-2 follow-btn"
                                                        data-user-id="{{ $request->requester->id }}"
                                                        data-following="0"
                                                        title="Follow this user">
                                                        <i data-lucide="user-plus" class="w-4 h-4 follow-icon"></i>
                                                        <span class="follow-text">Follow</span>
                                                        <span class="unfollow-text hidden">Unfollow</span>
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center justify-between gap-3">
                                        @if ($isPending)
                                            <p class="text-sm text-base-content/70 request-message">Requested to follow</p>
                                        @elseif ($isAccepted)
                                            <div class="flex items-center gap-2">
                                                <span class="badge badge-success badge-sm">Accepted</span>
                                                <p class="text-sm text-base-content/70 request-message">Request accepted</p>
                                            </div>
                                        @elseif ($isRejected)
                                            <div class="flex items-center gap-2">
                                                <span class="badge badge-error badge-sm">Rejected</span>
                                                <p class="text-sm text-base-content/70 request-message">Request rejected</p>
                                            </div>
                                        @endif
                                        <span class="text-xs text-base-content/50 request-time">{{ $requestTime }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </section>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-4"></i>
                    <p class="text-base-content/60 mb-2">No pending follow requests</p>
                </div>
            @endif
        </div>
    </div>
</x-layout>
