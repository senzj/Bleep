@vite([
    'resources/js/bleep/users/profile.js',
])

<x-layout>
    <div class="container mx-auto px-4 py-8 max-w-4xl">

        {{-- back to home page --}}
        <a href="/" class="text-md link link-ghost mb-4 inline-block">
            <i data-lucide="arrow-left" class="w-5 h-5 inline-block"></i>
            Back
        </a>

        {{-- Profile Header --}}
        <div class="bg-base-100 rounded-lg shadow-lg p-6 mb-6">
            <div class="flex flex-col sm:flex-row gap-6">

                {{-- Avatar --}}
                <div class="shrink-0">
                    <div class="avatar">
                        <div class="w-32 h-32 rounded-full shadow-lg overflow-hidden">
                            <img src="{{ $user->profile_picture_url }}"
                                alt="{{ $user->username }}"
                                onerror="this.src='{{ asset('images/avatar/default.jpg') }}'">
                        </div>
                    </div>
                </div>

                {{-- User Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
                        <div>
                            <h1 class="text-2xl font-bold text-base-content truncate">
                                {{ $user->dname ?? $user->username }}
                            </h1>
                            <p class="text-base-content/60 text-sm">{{"@" . $user->username }}</p>
                        </div>

                        {{-- Follow/Edit Button --}}
                        <div class="flex gap-2">
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
        <div class="bg-base-100 rounded-lg shadow-lg">
            <div role="tablist" class="tabs tabs-bordered w-full">
                <input type="radio" name="profile_tabs" role="tab" class="tab flex-1 text-base font-semibold [--tab-border-color:hsl(var(--p))]" aria-label="Bleeps" checked />
                <div role="tabpanel" class="tab-content p-6">
                    @if($bleeps->count() > 0)
                        <div class="space-y-4">
                            @foreach($bleeps as $bleep)
                                <x-bleep :bleep="$bleep" />
                            @endforeach
                        </div>

                        {{-- Pagination --}}
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

                <input type="radio" name="profile_tabs" role="tab" class="tab flex-1 text-base font-semibold [--tab-border-color:hsl(var(--p))]" aria-label="Reposts" />
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

                        {{-- Pagination --}}
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
</x-layout>
