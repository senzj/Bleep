@once
    @push('scripts')
        @vite([
            'resources/js/bleep/posts/media/lazyload.js',
        ])
    @endpush
@endonce

@php
    $count = $mediaItems->count();
    $nsfwClass = $isNsfw ? 'nsfw-media' : '';
    // Always use data-src - media loads asynchronously in background
    $nsfwAttr = $isNsfw ? 'data-media-src' : 'data-src';
    $firstItem = $mediaItems->first();
    $isAudioOnly = $firstItem && $firstItem->type === 'audio';

    // Generate unique audio ID to prevent conflicts with duplicate bleeps (e.g., reposts)
    $uniqueAudioId = 'audio-' . $bleep->id . '-' . uniqid();

    // Mini player header: first message line is the title, the rest the subtitle.
    $miniMessage = trim((string) $bleep->message);
    $miniLines = $miniMessage === '' ? [] : preg_split('/\R/u', $miniMessage);
    $miniTitle = trim((string) array_shift($miniLines));
    $miniSubtitle = trim(implode(' ', $miniLines));

    if ($miniTitle === '' && $firstItem) {
        $miniTitle = pathinfo($firstItem->original_name, PATHINFO_FILENAME) ?: 'Media';
    }

    $miniIsAnonymous = (bool) $bleep->is_anonymous;
    $miniViewerSeed = auth()->check() ? auth()->id() : request()->session()->getId();
    $miniPosterName = $miniIsAnonymous
        ? $bleep->anonymousDisplayNameFor($miniViewerSeed)
        : ($bleep->user->dname ?? $bleep->user->username ?? 'Unknown');
    $miniPosterHandle = $miniIsAnonymous
        ? '@anonymous'
        : '@' . ($bleep->user->username ?? 'unknown');

    $miniPosterAvatar = $miniIsAnonymous ? null : $bleep->user?->profile_picture_url;

    $miniMeta = [
        'miniTitle' => $miniTitle,
        'miniSubtitle' => $miniSubtitle,
        'posterName' => $miniPosterName,
        'posterHandle' => $miniPosterHandle,
        'posterAvatar' => $miniPosterAvatar,
    ];

    $allowMediaDownloads = auth()->check() && (bool) auth()->user()->getPreferences()->media_downloads;
@endphp


@if($isAudioOnly) {{-- Audio files are always single, so handle them separately --}}

    <x-media.audio
        :src="route('media.stream', ['path' => $firstItem->path])"
        :mime="$firstItem->mime_type"
        :alt="$firstItem->original_name"
        :nsfw="$isNsfw"
        :audio-id="$uniqueAudioId"
        :media-id="$firstItem->id"
        :duration="$firstItem->durationLabel()"
        :duration-seconds="$firstItem->duration_seconds"
        :download-url="asset('storage/'.$firstItem->path)"
        :download-name="$firstItem->original_name"
        :miniTitle="$miniMeta['miniTitle']"
        :miniSubtitle="$miniMeta['miniSubtitle']"
        :posterName="$miniMeta['posterName']"
        :posterHandle="$miniMeta['posterHandle']"
        :posterAvatar="$miniMeta['posterAvatar']"
        :allowDownload="$allowMediaDownloads"
    />


@else {{-- Image/Video Grid (existing code) --}}
    <x-media.grid
        :mediaItems="$mediaItems"
        :isNsfw="$isNsfw"
        :bleep="$bleep"
        :count="$count"
        :miniTitle="$miniMeta['miniTitle']"
        :miniSubtitle="$miniMeta['miniSubtitle']"
        :posterName="$miniMeta['posterName']"
        :posterHandle="$miniMeta['posterHandle']"
        :posterAvatar="$miniMeta['posterAvatar']"
    />
@endif
