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

        // Signed-in members browse the post with its normal NSFW cover. Only
        // visitors have to clear an age check, and it overlays the post instead
        // of pushing the page down.
        $showNsfwAgeGate = (bool) $bleep->is_nsfw && ! Auth::check();
    @endphp

    <x-slot:title>{{ $displayName ?? 'Unknown' }}'s Bleep | {{ $bleep->message ?? '' }}</x-slot:title>

    {{-- Bleep Posts Page Content --}}
    <div class="max-w-4xl mx-auto">
        <div class="mb-4">
            <x-ui.back-button fallback="{{ route('home') }}" />
        </div>

        <div class="relative">
            @if ($showNsfwAgeGate)
                <div
                    data-nsfw-age-gate
                    class="fixed inset-0 z-50 grid place-items-center bg-base-100/95 px-6 py-8 backdrop-blur-sm"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="nsfw-age-title"
                    aria-describedby="nsfw-age-description"
                >
                    <div class="w-full max-w-sm rounded-2xl border border-red-500/30 bg-base-100 p-6 text-center shadow-2xl">
                        <span class="mx-auto grid size-14 place-items-center rounded-full bg-red-500/10 text-red-500">
                            <i data-lucide="shield-alert" class="h-7 w-7"></i>
                        </span>
                        <h2 id="nsfw-age-title" class="mt-4 text-lg font-semibold">Sensitive content warning</h2>
                        <p id="nsfw-age-description" class="mt-1 text-sm text-base-content/70">
                            This bleep is marked NSFW and may contain sensitive or inappropriate
                            content. Continue only if you are of legal age in your region and want
                            to view it at your own risk.
                        </p>
                        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">
                            <a href="{{ route('home') }}" class="btn btn-ghost btn-sm">Go back</a>
                            <button
                                type="button"
                                data-nsfw-age-accept
                                class="btn btn-error btn-sm text-white"
                            >
                                I understand and want to view
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Bleep Post --}}
            <div
                class="{{ $showNsfwAgeGate ? 'hidden ' : '' }}space-y-4"
                @if ($showNsfwAgeGate) data-nsfw-age-content @endif
            >
                <x-bleep :bleep="$bleep" />
            </div>
        </div>

        {{-- Comments Section --}}
        <x-subcomponents.comments.layout :bleepid="$bleep->id" :layoutmode="'post'" />
    </div>

</x-layout>
