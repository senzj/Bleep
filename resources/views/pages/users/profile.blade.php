@push('scripts')
    <script>
        // Pass backend config to frontend (keep anonymity feature hidden if disabled)
        window.isAnonymousEnabled = {{ env('ANONYMITY', true) ? 'true' : 'false' }};
    </script>
    @vite([
        'resources/js/bleep/users/profile-lazyload.js',
        'resources/js/bleep/users/profile.js',
        'resources/js/bleep/users/follow.js',
        'resources/js/bleep/modals/mediamodal.js'
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
        <div class="bg-base-100 rounded-lg shadow-lg p-6 mb-4">
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
                    <div class="flex justify-between sm:flex-row sm:items-start sm:justify-between gap-4 mb-2">
                        <div>
                            {{-- Name Row --}}
                            <div class="flex items-center gap-1.5 flex-wrap {{ $canViewContent ? '' : 'mt-2' }}">
                                {{-- User Display Name --}}
                                <h1 class="text-2xl font-bold text-base-content truncate">
                                    {{ $user->dname ?? $user->username }}
                                </h1>

                                @if ($canViewContent)
                                    {{-- Role --}}
                                    @if ($user->role === 'admin')
                                        <i data-lucide="sparkles" class="w-5 h-5 text-teal-500" aria-label="Admin"></i>
                                    @elseif ($user->role === 'moderator')
                                        <i data-lucide="sparkles" class="w-5 h-5 text-amber-500" aria-label="Moderator"></i>
                                    @endif

                                    {{-- Verified Badge --}}
                                    @if ($user->is_verified)
                                        <i data-lucide="badge-check" class="w-5 h-5 text-blue-500 shrink-0"></i>
                                    @endif

                                    {{-- Bot Badge --}}
                                    @if ($user->is_bot)
                                        <i data-lucide="cpu" class="w-5 h-5 text-green-500 shrink-0" title="This account is a bot"></i>
                                    @endif
                                @endif

                                {{-- Private Lock --}}
                                @if (!$canViewContent)
                                    <i data-lucide="lock" class="w-5 h-5 text-base-content/60 shrink-0" title="This profile is private"></i>
                                @endif
                            </div>

                            {{-- User Alias Name --}}
                            <p class="text-base-content/60 text-sm">{{ "@" . $user->username }}</p>
                        </div>

                        {{-- Edit/Follow/Message Button --}}
                        @if ($isOwnProfile)
                            <a href="{{ route('settings') }}" class="btn btn-sm btn-outline gap-2 rounded-lg">
                                <i data-lucide="settings" class="w-4 h-4"></i>
                                Edit Profile
                            </a>
                        @else
                            <x-button.follow :user="$user" :showMessage="$canViewContent" />
                        @endif
                    </div>

                    {{-- Stats --}}
                    @if ($canViewContent)
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            {{-- Left: Social info --}}
                            <div class="flex gap-3 mb-2">
                                <div class="text-center">
                                    <div class="text-xl font-bold">{{ $bleeps->total() }}</div>
                                    <div class="text-xs text-base-content/60">Bleeps</div>
                                </div>
                                <div class="text-center cursor-pointer hover:text-primary">
                                    <div class="text-xl font-bold">{{ $followersCount }}</div>
                                    <div class="text-xs text-base-content/60">Followers</div>
                                </div>
                                <div class="text-center cursor-pointer hover:text-primary">
                                    <div class="text-xl font-bold">{{ $followingCount }}</div>
                                    <div class="text-xs text-base-content/60">Following</div>
                                </div>
                            </div>

                            {{-- Right: Account info --}}
                            <div>
                                {{-- Joined Date --}}
                                <div class="flex items-center gap-2 text-xs text-base-content/60">
                                    <i data-lucide="calendar" class="w-4 h-4"></i>
                                    <span>Joined {{ $user->created_at->format('M Y') }}</span>
                                </div>

                                {{-- Location --}}
                                @if($user->timezone)
                                    <div class="flex items-center gap-2 text-xs text-base-content/60 mt-1">
                                        <i data-lucide="map-pin" class="w-4 h-4"></i>
                                        <span>{{ $user->timezone }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Bio/Description --}}
                    @if($user->bio)
                        <p class="text-base-content/80 text-sm mb-3">{{ $user->bio }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        @if(!$canViewContent)
            <div class="bg-base-100 rounded-lg shadow-lg border border-base-300 p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-base-200 mb-4">
                    <i data-lucide="lock" class="w-8 h-8 text-base-content/50"></i>
                </div>
                <h2 class="text-lg font-bold mb-1">This account is private</h2>
                <p class="text-sm text-base-content/60 mb-4">
                    Follow <span class="font-medium">{{'@' . $user->username }}</span> to see their bleeps and reposts.
                </p>
                @if(!Auth::check())
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Log in to follow</a>
                @endif
            </div>
        @else

            <div class="bg-base-100 rounded-lg shadow-lg border border-base-300" x-data="{
                tab: 'bleeps',
                tabs: ['bleeps', 'reposts'],
                focusTab(idx) {
                    this.tab = this.tabs[idx];
                    this.$nextTick(() => {
                        document.getElementById('tab-btn-' + this.tab).focus();
                        if (window.lucide && typeof window.lucide.createIcons === 'function') {
                            window.lucide.createIcons();
                        }
                    });
                }
            }" role="tablist" aria-label="Profile Tabs">
                {{-- Tab Headers --}}
                <div class="flex border-b border-base-300">
                    <template x-for="(tabName, idx) in tabs" :key="tabName">
                        <button
                            :id="'tab-btn-' + tabName"
                            type="button"
                            :aria-selected="tab === tabName"
                            :tabindex="tab === tabName ? 0 : -1"
                            @click="tab = tabName; $nextTick(() => { if (window.lucide && typeof window.lucide.createIcons === 'function') { window.lucide.createIcons(); } })"
                            @keydown.right.prevent="focusTab((idx + 1) % tabs.length)"
                            @keydown.left.prevent="focusTab((idx - 1 + tabs.length) % tabs.length)"
                            :class="tab === tabName
                                ? 'border-b-2 border-primary text-primary font-semibold'
                                : 'text-base-content/50 hover:text-base-content'"
                            class="flex-1 py-3 text-sm transition-colors focus:outline-none cursor-pointer"
                            role="tab"
                            x-text="tabName.charAt(0).toUpperCase() + tabName.slice(1)"
                        ></button>
                    </template>
                </div>

                {{-- Tab Panels --}}
                <div class="p-6" role="tabpanel" :aria-labelledby="'tab-btn-' + tab" x-transition>
                    <template x-if="tab === 'bleeps'">
                        <div>
                            @if($bleeps && $bleeps->count() > 0)
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
                                            {{ "@" . $user->username }} hasn't posted any bleeps yet.
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>
                    </template>
                    <template x-if="tab === 'reposts'">
                        <div>
                            @if($reposts && $reposts->count() > 0)
                                <div id="reposts-list" class="space-y-4"
                                    data-next-url="{{ $reposts->hasMorePages() ? route('user.reposts', ['username' => $user->username, 'page' => $reposts->currentPage() + 1]) : '' }}">
                                    @foreach($reposts as $repost)
                                        @if($repost->bleep && !$repost->bleep->deleted_at)
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
                                            {{ "@" . $user->username }} hasn't reposted anything yet.
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>
                    </template>
                </div>
            </div>
        @endif

    </div>

    {{-- Global Modals (available for all bleeps on the page) --}}
    <x-subcomponents.bleeps.mediamodal />
    <x-modals.posts.comments />
    <x-modals.posts.edit />
    <x-modals.posts.share />

</x-layout>
