@vite([
    'resources/js/bleep/users/profile.js',
])

<x-layout>
    <div class="container mx-auto px-4 max-w-4xl">

        {{-- back to home page --}}
        <a href="/" class="text-md link link-ghost mb-4 inline-block">
            <i data-lucide="arrow-left" class="w-5 h-5 inline-block"></i>
            Back
        </a>

        {{-- Profile Header --}}
        <div class="bg-base-100 rounded-lg shadow-lg p-6 mb-6">
            <div class="flex flex-col sm:flex-row gap-6">

                {{-- Avatar - Clickable for full view --}}
                <div class="shrink-0">
                    <div class="avatar cursor-pointer group" id="profile-avatar" data-bleep-media>
                        <div class="w-32 h-32 rounded-full shadow-lg overflow-hidden ring-2 ring-transparent group-hover:ring-primary transition-all duration-200"
                             data-media-index="0"
                             data-media-type="image"
                             data-media-src="{{ $user->profile_picture_url }}"
                             data-media-alt="{{ '@' . $user->username }} profile picture">
                            <img src="{{ $user->profile_picture_url }}" alt="{{ $user->username }}"
                                onerror="this.src='{{ asset('images/avatar/default.jpg') }}'">
                        </div>
                    </div>
                </div>

                {{-- User Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
                        <div>
                            <h1 class="text-2xl font-bold text-base-content truncate">
                                {{ $user->dname ?? $user->username }}
                            </h1>
                            <p class="text-base-content/60 text-sm">{{"@" . $user->username }}</p>
                        </div>

                        {{-- Follow/Edit Button --}}
                        <div class="flex justify-end gap-2">
                            @if($isOwnProfile)
                                <a href="#" class="btn btn-outline btn-sm gap-2">
                                    <i data-lucide="settings" class="w-4 h-4"></i>
                                    Edit Profile
                                </a>
                            @elseif(Auth::check())
                                <button type="button"
                                    class="btn btn-sm gap-2 follow-btn
                                        {{ $isFollowing ? 'btn-primary' : 'btn-outline' }}"
                                    data-user-id="{{ $user->id }}"
                                    data-following="{{ $isFollowing ? '1' : '0' }}">
                                    <i data-lucide="{{ $isFollowing ? 'user-check' : 'user-plus' }}" class="w-4 h-4 follow-icon"></i>
                                    <span class="follow-text">{{ $isFollowing ? 'Following' : 'Follow' }}</span>
                                    <span class="unfollow-text hidden">Unfollow</span>
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Stats --}}
                    <div class="flex gap-6 mb-4">
                        <div class="text-center">
                            <div class="text-xl font-bold text-base-content">{{ $bleeps->total() }}</div>
                            <div class="text-xs text-base-content/60">Bleeps</div>
                        </div>
                        <div class="text-center cursor-pointer hover:text-primary">
                            <div class="text-xl font-bold text-base-content">{{ $followersCount }}</div>
                            <div class="text-xs text-base-content/60">Followers</div>
                        </div>
                        <div class="text-center cursor-pointer hover:text-primary">
                            <div class="text-xl font-bold text-base-content">{{ $followingCount }}</div>
                            <div class="text-xs text-base-content/60">Following</div>
                        </div>
                    </div>

                    {{-- Bio/Description --}}
                    @if($user->bio)
                        <p class="text-base-content/80 text-sm mb-3">{{ $user->bio }}</p>
                    @endif

                    {{-- Joined Date --}}
                    <div class="flex items-center gap-2 text-xs text-base-content/60">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span>Joined {{ $user->created_at->format('F Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="bg-base-100 rounded-lg shadow-lg overflow-hidden">
            <div role="tablist" class="tabs tabs-bordered w-full border-b border-base-300">
                {{-- Bleeps Tab --}}
                <input type="radio" name="profile_tabs" role="tab"
                    class="tab flex-1 text-base font-semibold border-b-2 border-transparent
                            data-[state=checked]:border-primary data-[state=checked]:text-primary"
                    aria-label="Bleeps" checked />
                <div role="tabpanel" class="tab-content p-6">
                    @if($bleeps->count() > 0)
                        <div class="space-y-4">
                            @foreach($bleeps as $bleep)
                                <x-bleep :bleep="$bleep" />
                            @endforeach
                        </div>
                        <div class="mt-6">
                            {{ $bleeps->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <i data-lucide="message-square" class="w-16 h-16 mx-auto text-base-content/30 mb-4"></i>
                            <p class="text-base-content/60">
                                @if($isOwnProfile)
                                    You haven't posted any bleeps yet.
                                @else
                                    {{"@" . $user->username }} hasn't posted any bleeps yet.
                                @endif
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Reposts Tab --}}
                <input type="radio" name="profile_tabs" role="tab"
                    class="tab flex-1 text-base font-semibold border-b-2 border-transparent
                            data-[state=checked]:border-primary data-[state=checked]:text-primary"
                    aria-label="Reposts" />
                <div role="tabpanel" class="tab-content p-6">
                    @if($reposts->count() > 0)
                        <div class="space-y-4">
                            @foreach($reposts as $repost)
                                @if($repost->bleep && !$repost->bleep->deleted_at)
                                    <div class="relative">
                                        {{-- Repost indicator --}}
                                        <div class="flex items-center gap-2 mb-2 text-xs text-base-content/60 pl-2">
                                            <i data-lucide="repeat" class="w-4 h-4"></i>
                                            <span>
                                                @if($isOwnProfile)
                                                    You reposted
                                                @else
                                                    {{"@" . $user->username }} reposted
                                                @endif
                                                <span class="text-base-content/40">• {{ $repost->created_at->diffForHumans() }}</span>
                                            </span>
                                        </div>
                                        <x-bleep :bleep="$repost->bleep" />
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <div class="mt-6">
                            {{ $reposts->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <i data-lucide="repeat" class="w-16 h-16 mx-auto text-base-content/30 mb-4"></i>
                            <p class="text-base-content/60">
                                @if($isOwnProfile)
                                    You haven't reposted anything yet.
                                @else
                                    {{"@" . $user->username }} hasn't reposted anything yet.
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>

    {{-- Global Modals (available for all bleeps on the page) --}}
    <x-subcomponents.bleeps.mediamodal />
    <x-modals.posts.comments />
    <x-modals.posts.edit />

    {{-- Share Modal (from bleep component, but ensuring it's available) --}}
    <div id="share-modal" class="hidden fixed inset-0 z-50 items-center justify-center">
        <div id="share-modal-overlay" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="relative max-w-sm w-full mx-4 bg-base-100 rounded-2xl shadow-xl border border-base-300 p-5 z-10 space-y-4">
            <div class="space-y-1">
                <h3 class="text-lg font-semibold">Share Post</h3>
                <p class="text-sm text-base-content/70">Copy the link to share this post.</p>
            </div>
            <input id="share-url-input" type="hidden" />
            <div id="share-link-card" role="button" tabindex="0"
                 class="flex items-center justify-between gap-4 rounded-xl border border-base-300 bg-base-200/70 px-4 py-3 cursor-pointer transition hover:border-primary hover:bg-primary/10 focus:outline-none focus:ring-2 focus:ring-primary/60">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="flex items-center justify-center rounded-full bg-primary/10 text-primary size-10">
                        <i data-lucide="link-2" class="w-4 h-4"></i>
                    </span>
                    <div class="min-w-0">
                        <span class="text-xs uppercase tracking-wide text-base-content/60">Share link</span>
                        <span id="share-url-display" class="mt-1 block text-sm font-medium text-base-content truncate">
                            This is a bleep link URL
                        </span>
                    </div>
                </div>
                <button id="share-copy-btn" type="button" class="btn btn-ghost btn-sm shrink-0" title="Copy link">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="flex items-center justify-between gap-3 text-xs text-base-content/70">
                <span>Sharing this post will create a link anyone can use to view it.</span>
                <button id="share-cancel-btn" type="button" class="btn btn-ghost btn-sm bg-gray-300">Cancel</button>
            </div>
        </div>
    </div>

    {{-- Comments Overlay (required by comment.js) --}}
    <div id="comments-overlay" class="hidden fixed inset-0 z-40"></div>
</x-layout>
