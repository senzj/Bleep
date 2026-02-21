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
@endpush

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
    @endphp

    <x-slot:title>{{ $usr->username }}'s Bleep | {{ $bleep->message ?? '' }}</x-slot:title>

    {{-- Bleep Posts Page Content --}}
    <div class="max-w-4xl mx-auto">
        <a href="/" class="text-md link link-ghost mb-4 inline-block">
            <i data-lucide="arrow-left" class="w-5 h-5 inline-block"></i>
            Back
        </a>

        {{-- Bleep Post --}}
        <div class="space-y-4">
            <x-bleep :bleep="$bleep" />
        </div>

        {{-- Comments Section --}}
        <x-subcomponents.comments.layout :bleepid="$bleep->id" :layoutmode="'post'" />
    </div>

    {{-- Report Bleep Modal --}}
    <x-modals.posts.report />

    {{-- Edit Bleep Modal --}}
    <x-modals.posts.edit />

    {{-- Share Bleep Modal --}}
    <x-modals.posts.share />

    {{-- Media Bleep Modal --}}
    <x-subcomponents.bleeps.mediamodal />
</x-layout>
