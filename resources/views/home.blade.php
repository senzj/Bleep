@push('scripts')
    <script>
        // Pass backend config to frontend (keep anonymity feature hidden if disabled)
        window.isAnonymousEnabled = {{ config('app.anonymity', true) ? 'true' : 'false' }};
    </script>
    @vite([
        'resources/js/bleep/posts/post.js',
        'resources/js/bleep/modals/posts/edit.js',
        'resources/js/bleep/posts/infinitescroll.js',
        'resources/js/bleep/posts/send_notif.js',
        'resources/js/ui/mobile.js',
    ])
@endpush

@push('meta')
    <meta name="auth" content="{{ Auth::check() ? 'true' : 'false' }}" />
    <meta name="anonymity-enabled" content="{{ config('app.anonymity', true) ? 'true' : 'false' }}" />
    @auth
        @php
            $userAvatar = '/images/avatar/default.jpg';
            $usr = Auth::user();
            $avatarPath = $usr->profile_picture ?? null;
            if ($avatarPath) {
                $userAvatar = asset('storage/' . $avatarPath);
            }
        @endphp
        <meta name="user-avatar" content="{{ $userAvatar }}" />
    @endauth

@endpush

<x-layout>
    <x-slot:title>Home</x-slot:title>

    <div class="grid grid-cols-1 lg:grid-cols-12 lg:gap-3">
        {{-- Left panel - left navivation, hidden when vertical navbar --}}
        @php
            // Get user's nav layout preference, default to horizontal
            $navLayout = 'horizontal';
            if (Auth::check()) {
                $navLayout = Auth::user()->getNavLayout();
            }
        @endphp

        @if ($navLayout !== 'vertical')
            <div class="lg:block lg:col-span-3" id="left-panel">
            </div>
        @endif

        {{-- Center panel - main content --}}
        <div class="lg:block {{ $navLayout === 'vertical' ? 'lg:col-span-8 lg:ml-45' : 'lg:col-span-6' }}" id="center-panel">

            {{-- Feed panel: post form + bleeps (toggled as a single unit on mobile) --}}
            <div id="feed-panel">
                {{-- Post Form --}}
                @auth
                    <div class="card bg-base-100">
                        <div class="card-body rounded-lg">

                            <form method="POST" action="/bleeps" enctype="multipart/form-data" id="bleep-form">
                                @csrf
                                <div class="form-control w-full">
                                    <textarea
                                        name="message"
                                        placeholder="What's on your mind? Share them with a bleep!"
                                        class="textarea textarea-bordered rounded-lg w-full resize-none placeholder:text-lg @error('message') textarea-error @enderror"
                                        rows="2"
                                        maxlength="255"
                                    ></textarea>
                                </div>

                                {{-- hidden media input (single trigger button will open this) --}}
                                <input
                                    id="bleep-media-input"
                                    type="file"
                                    name="media[]"
                                    class="hidden"
                                    multiple
                                    accept="image/*,video/mp4,video/webm,audio/mpeg,audio/wav,audio/mp3"
                                />

                                {{-- preview grid --}}
                                <div id="bleep-media-preview" class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-2"></div>

                                {{-- upload progress --}}
                                <div id="upload-progress" class="mt-3 hidden">
                                    <div class="flex items-center gap-2">
                                        <progress id="upload-progress-bar" class="progress progress-primary flex-1" value="0" max="100"></progress>
                                        <span id="upload-progress-percent" class="text-xs w-10 text-right">0%</span>
                                    </div>
                                    <div id="upload-status" class="text-xs mt-1 text-base-content/60">Starting upload...</div>
                                </div>

                                <div class="mt-1 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                                    {{-- Left buttons (keep as first column on desktop, but render inline controls on mobile) --}}
                                    <div class="flex w-full sm:w-auto items-center justify-end sm:space-y-5 gap-3">
                                        <div class="flex items-center gap-8">

                                            {{-- Anonymous: icon/label + toggle --}}
                                            @if (config('app.anonymity', true))
                                                <div class="flex items-center gap-1">
                                                    <label for="post-anonymous-toggle" class="flex items-center gap-2 cursor-pointer select-none">
                                                        <span id="post-anonymous-icon" class="p-2 rounded-full bg-base-400 transition-colors duration-150" title="Post anonymously" aria-hidden="true">
                                                            <i data-lucide="hat-glasses" class="w-5 h-5"></i>
                                                        </span>
                                                    </label>
                                                    <input id="post-anonymous-toggle"
                                                        name="is_anonymous"
                                                        type="checkbox"
                                                        value="1"
                                                        class="toggle toggle-sm"
                                                        {{ old('is_anonymous') ? 'checked' : '' }}>
                                                </div>
                                            @endif

                                            {{-- NSFW: icon/label + toggle --}}
                                            <div class="flex items-center gap-1">
                                                <label for="post-nsfw-toggle" class="flex items-center gap-2 cursor-pointer select-none">
                                                    <span id="post-nsfw-icon" class="p-2 rounded-full bg-base-400 transition-colors duration-150" title="Marked as NSFW" aria-hidden="true">
                                                        <i data-lucide="eye-off" class="w-5 h-5"></i>
                                                    </span>
                                                </label>
                                                <input id="post-nsfw-toggle"
                                                       name="is_nsfw"
                                                       type="checkbox"
                                                       value="1"
                                                       class="toggle toggle-sm"
                                                       {{ old('is_nsfw') ? 'checked' : '' }}>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Right buttons (remains second row on mobile because parent is flex-col on small screens) --}}
                                    <div class="flex flex-col sm:flex-row items-center justify-end gap-2 w-full sm:w-auto">
                                        <div class="flex w-full sm:w-auto justify-between gap-2">

                                            {{-- Add media button --}}
                                            <button type="button" id="open-media-picker" class="btn btn-ghost btn-sm flex-1 sm:flex-none justify-center border border-base-300 shadow-lg">
                                                <i data-lucide="image-plus" class="w-4 h-4 mr-1"></i>
                                                Add media

                                                <span id="bleep-media-count" class="badge badge-neutral badge-sm ml-2 hidden"></span>
                                            </button>

                                            {{-- Submit post --}}
                                            <button type="submit" class="btn btn-primary btn-sm flex-1 sm:flex-none justify-center shadow-lg" id="post-submit-btn">
                                                <i data-lucide="send" class="w-4 h-4"></i>
                                                Post
                                            </button>

                                        </div>
                                    </div>

                                </div>

                            </form>
                        </div>
                    </div>
                @endauth

                {{-- Bleep feed --}}
                <div>
                    {{-- Sticky top Bleep Sort --}}
                    @auth
                        <div class="sticky {{ $navLayout === 'horizontal' ? 'top-20' : 'top-1' }} z-20 mt-3 border border-base-200 px-1 py-1 bg-base-100/95 backdrop-blur rounded-sm shadow-sm">
                            <div class="flex gap-2 w-full" role="tablist" aria-label="Main tabs">
                                {{-- Shows For You Page --}}
                                <button type="button" class="flex-1 btn btn-sm btn-ghost data-tab-active" data-tab="bleep" aria-controls="bleeps-container" aria-selected="true">For You</button>

                                {{-- Shows Followings Bleep Only --}}
                                <button type="button" class="flex-1 btn btn-sm btn-ghost" data-tab="following" aria-controls="following-container" aria-selected="false">Following</button>

                                {{-- Shows Friends Bleep Only --}}
                                <button type="button" class="flex-1 btn btn-sm btn-ghost" data-tab="friends" aria-controls="friends-container" aria-selected="false">Friends</button>
                            </div>
                        </div>
                    @endauth

                    {{-- For You --}}
                    <div id="bleeps-container" class="space-y-4 mt-1" data-tab-panel="bleep" style="overflow-anchor: auto;">
                        @forelse ($bleeps as $bleep)
                            <x-bleep :bleep="$bleep" />
                        @empty
                            <div class="hero py-12">
                                <div class="hero-content text-center">
                                    <div>
                                        <i data-lucide="inbox" class="w-16 h-16 mx-auto text-base-content-100"></i>
                                        <p class="mt-4 text-base-content">No bleeps yet. Be the first to share!</p>
                                    </div>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    {{-- Following --}}
                    <div id="following-container" class="space-y-4 mt-1 hidden" data-tab-panel="following" aria-hidden="true">
                        @auth
                            @if ($followingBleeps && $followingBleeps->count())
                                @foreach ($followingBleeps as $bleep)
                                    <x-bleep :bleep="$bleep" />
                                @endforeach
                            @else
                                <div class="hero py-12">
                                    <div class="hero-content text-center">
                                        <div>
                                            <i data-lucide="users" class="w-16 h-16 mx-auto text-base-content-100"></i>
                                            <p class="mt-4 text-base-content">No posts from people you follow yet.</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="hero py-12">
                                <div class="hero-content text-center">
                                    <div>
                                        <i data-lucide="log-in" class="w-16 h-16 mx-auto text-base-content-100"></i>
                                        <p class="mt-4 text-base-content">Sign in to see followed posts.</p>
                                    </div>
                                </div>
                            </div>
                        @endauth
                    </div>

                    {{-- Friends --}}
                    <div id="friends-container" class="space-y-4 mt-1 hidden" data-tab-panel="friends" aria-hidden="true">
                        @auth
                            @if ($friendsBleeps && $friendsBleeps->count())
                                @foreach ($friendsBleeps as $bleep)
                                    <x-bleep :bleep="$bleep" />
                                @endforeach
                            @else
                                <div class="hero py-12">
                                    <div class="hero-content text-center">
                                        <div>
                                            <i data-lucide="user-check" class="w-16 h-16 mx-auto text-base-content-100"></i>
                                            <p class="mt-4 text-base-content">No posts from mutuals yet.</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="hero py-12">
                                <div class="hero-content text-center">
                                    <div>
                                        <i data-lucide="log-in" class="w-16 h-16 mx-auto text-base-content-100"></i>
                                        <p class="mt-4 text-base-content">Sign in to see friends posts.</p>
                                    </div>
                                </div>
                            </div>
                        @endauth
                    </div>

                    {{-- Loading indicator --}}
                    <div id="loading-indicator" class="hidden text-center py-8">
                        <span class="loading loading-spinner loading-lg text-primary"></span>
                        <p class="mt-2 text-base-content/60">Loading more bleeps...</p>
                    </div>

                    {{-- End of content indicator --}}
                    <div id="end-of-content" class="hidden text-center py-8">
                        <i data-lucide="circle-check" class="w-8 h-8 mx-auto text-base-content/40"></i>
                        <p class="mt-2 text-base-content/60">You've reached the end of the bleeps!</p>
                    </div>

                    {{-- Infinite scroll trigger --}}
                    <div id="infinite-scroll-trigger" data-page="2" data-has-more="{{ $bleeps->hasMorePages() ? 'true' : 'false' }}"></div>
                </div>
            </div>

        </div>

        {{-- Right panel - announcement & notifications --}}
        <div class="hidden lg:block lg:col-span-3" id="right-panel">
            {{-- Server Announcement --}}
            <x-announcement.system />

            {{-- Quick Few Notifications --}}

        </div>
    </div>

</x-layout>

{{-- Media Bleep Modal --}}
<x-subcomponents.bleeps.mediamodal />

{{-- Edit Bleep Modal --}}
<x-modals.posts.edit />

{{-- Comments Bleep Modal --}}
<x-modals.posts.comments />

{{-- Share Bleep Modal --}}
<x-modals.posts.share />
