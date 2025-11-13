@push('scripts')
    @vite([
        'resources/js/bleep/users/profile-lazyload.js',
        'resources/js/bleep/users/profile.js',
        'resources/js/bleep/modals/mediamodal.js',
        ])
@endpush

@push('styles')
    @vite('resources/css/profile.css')
@endpush



<x-layout>
    <x-slot:title>Profile | {{ "@" . $user->username }}</x-slot:title>

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
        <div class="bg-transparent">
            <div role="tablist"
                 class="tabs tabs-boxed w-full bg-base-100 p-1 gap-2 rounded-t-lg border border-base-300">
                {{-- Bleeps Tab --}}
                <input type="radio" name="profile_tabs" role="tab"
                    class="tab flex-1 text-base font-semibold
                           data-[state=checked]:bg-base-300 data-[state=checked]:text-base-content"
                    aria-label="Bleeps" checked />
                <div role="tabpanel"
                     class="tab-content p-6 bg-base-100 border border-base-300 border-t-0 rounded-b-lg">
                    @if($bleeps->count() > 0)
                        <div id="bleeps-list" class="space-y-4"
                             data-next-url="{{ $bleeps->hasMorePages() ? route('user.bleeps', ['username' => $user->username, 'page' => $bleeps->currentPage() + 1]) : '' }}">
                            @foreach($bleeps as $bleep)
                                <x-bleep :bleep="$bleep" />
                            @endforeach
                        </div>
                        <button id="bleeps-load-more"
                                class="btn btn-outline btn-sm mt-4 w-full {{ $bleeps->hasMorePages() ? '' : 'hidden' }}">
                            Load more
                        </button>
                        <div id="bleeps-sentinel" class="h-4"></div>
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
                    class="tab flex-1 text-base font-semibold
                           data-[state=checked]:bg-base-300 data-[state=checked]:text-base-content"
                    aria-label="Reposts" />
                <div role="tabpanel"
                     class="tab-content p-6 bg-base-100 border border-base-300 border-t-0 rounded-b-lg">
                    @if($reposts->count() > 0)
                        <div id="reposts-list" class="space-y-4"
                             data-next-url="{{ $reposts->hasMorePages() ? route('user.reposts', ['username' => $user->username, 'page' => $reposts->currentPage() + 1]) : '' }}">
                            @foreach($reposts as $repost)
                                @if($repost->bleep && !$repost->bleep->deleted_at)
                                    <div class="relative">
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
                        <button id="reposts-load-more"
                                class="btn btn-outline btn-sm mt-4 w-full {{ $reposts->hasMorePages() ? '' : 'hidden' }}">
                            Load more
                        </button>
                        <div id="reposts-sentinel" class="h-4"></div>
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
    <x-modals.posts.share />

</x-layout>
