{{-- load once --}}
@once
    @push('scripts')
        @vite([
            'resources/js/app.js'
        ])
    @endpush

    @push('meta')
        <meta name="auth" content="{{ Auth::check() ? 'true' : 'false' }}" />
        <meta name="anonymity-enabled" content="{{ env('ANONYMITY', true) ? 'true' : 'false' }}" />
        @if (Auth::check())
            @php
                $userAvatar = '/images/avatar/default.jpg';
                $usr = Auth::user();
                $avatarPath = $usr->profile_picture ?? null;
                if ($avatarPath) {
                    $userAvatar = asset('storage/' . $avatarPath);
                }
            @endphp
            <meta name="user-avatar" content="{{ $userAvatar }}" />
        @endif

        @php
            // Unfurl metadata is public: anonymous bleeps must never expose the
            // author's real name or avatar to crawlers.
            $metaAuthor = $bleep->is_anonymous ? 'Anonymous' : ($bleep->user->dname ?? 'Unknown');
            $metaTitle = $metaAuthor . ' on Bleep';

            $metaDescription = trim((string) preg_replace('/\s+/u', ' ', (string) $bleep->message));
            if ($metaDescription === '') {
                $metaDescription = 'A bleep on Bleep.';
            }
            $metaDescription = \Illuminate\Support\Str::limit($metaDescription, 160);

            $metaUrl = route('post', $bleep->id);

            // NSFW bleeps must not leak their media into link previews.
            $metaImage = null;
            if (! $bleep->is_nsfw) {
                $metaImage = $bleep->media->firstWhere('type', 'image')?->url();
            }
        @endphp

        <link rel="canonical" href="{{ $metaUrl }}" />
        <meta property="og:site_name" content="Bleep" />
        <meta property="og:type" content="article" />
        <meta property="og:title" content="{{ $metaTitle }}" />
        <meta property="og:description" content="{{ $metaDescription }}" />
        <meta property="og:url" content="{{ $metaUrl }}" />
        @if ($metaImage)
            <meta property="og:image" content="{{ $metaImage }}" />
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:image" content="{{ $metaImage }}" />
        @else
            <meta name="twitter:card" content="summary" />
        @endif
        <meta name="twitter:title" content="{{ $metaTitle }}" />
        <meta name="twitter:description" content="{{ $metaDescription }}" />
    @endpush

    {{-- Report Bleep Modal --}}
    <x-modals.posts.report />

    {{-- Edit Bleep Modal --}}
    <x-modals.posts.edit />

    {{-- Share Bleep Modal --}}
    <x-modals.posts.share />

    {{-- Media Bleep Modal --}}
    <x-subcomponents.bleeps.mediamodal />
@endonce

<x-layout>

    @php

        $viewerSeed = Auth::check() ? Auth::id() : request()->session()->getId();

        $displayName = $bleep->is_anonymous
            ? $bleep->anonymousDisplayNameFor($viewerSeed)
            : ($bleep->user->dname ?? 'Unknown');

        $UserAvatarUrl = null;
        if (Auth::check()) {
            $usr = Auth::user();
            $avatarPath = $usr->profile_picture ?? null;
            if ($avatarPath) {
                $UserAvatarUrl = asset('storage/' . $avatarPath);
            } else {
                $UserAvatarUrl = asset('images/avatar/default.jpg');
            }
        }

        $isAnonymous = (bool) $bleep->is_anonymous;

        if ($isAnonymous) {
            $viewerSeed = auth()->check() ? auth()->id() : request()->session()->getId();
            $displayName = $bleep->anonymousDisplayNameFor($viewerSeed);
            $username = '@anonymous';
        } else {
            $displayName = $bleep->user->dname ?? 'Unknown';
            $username = "@" . ($bleep->user->username ?? 'Unknown');
        }

        // NSFW posts need an age check on every visit unless the signed-in
        // viewer has confirmed their age and opted into unblurred NSFW media.
        $nsfwViewer = Auth::user();
        $nsfwPreferences = $nsfwViewer?->getPreferences();
        $nsfwEligible = $nsfwViewer !== null
            && $nsfwViewer->age_confirmed_at !== null
            && (bool) $nsfwPreferences?->show_nsfw
            && ! (bool) $nsfwPreferences?->blur_nsfw_media;
        $showNsfwAgeGate = (bool) $bleep->is_nsfw && ! $nsfwEligible;
    @endphp

    <x-slot:title>{{ $displayName ?? 'Unknown' }}'s Bleep | {{ $bleep->message ?? '' }}</x-slot:title>

    {{-- Bleep Posts Page Content --}}
    <div class="max-w-4xl mx-auto">
        <div class="mb-4">
            <x-ui.back-button fallback="{{ route('home') }}" />
        </div>

        @if ($showNsfwAgeGate)
            <div
                data-nsfw-age-gate
                class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 p-5 text-center"
                role="alert"
            >
                <i data-lucide="shield-alert" class="mx-auto mb-2 h-8 w-8 text-red-500"></i>
                <p class="text-lg font-semibold">Age check required</p>
                <p class="mx-auto mt-1 max-w-md text-sm text-base-content/70">
                    This post is marked NSFW. Confirm that you are of legal age in your region
                    to view sensitive content.
                </p>
                <button
                    type="button"
                    data-nsfw-age-accept
                    class="btn btn-error btn-sm mt-3 text-white"
                >
                    I am of legal age — show post
                </button>
            </div>
        @endif

        {{-- Bleep Post --}}
        <div
            class="space-y-4 {{ $showNsfwAgeGate ? 'hidden' : '' }}"
            @if ($showNsfwAgeGate) data-nsfw-age-content @endif
        >
            <x-bleep :bleep="$bleep" />
        </div>

        {{-- Comments Section --}}
        <x-subcomponents.comments.layout :bleepid="$bleep->id" :layoutmode="'post'" />
    </div>

</x-layout>
