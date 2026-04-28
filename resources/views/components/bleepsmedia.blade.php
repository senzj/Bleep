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
@endphp


@if($isAudioOnly) {{-- Audio files are always single, so handle them separately --}}

    <x-media.audio
        :src="route('media.stream', ['path' => $firstItem->path])"
        :mime="$firstItem->mime_type"
        :alt="$firstItem->original_name"
        :nsfw="$isNsfw"
        :audio-id="$uniqueAudioId"
        :download-url="asset('storage/'.$firstItem->path)"
        :download-name="$firstItem->original_name"
    />


@else {{-- Image/Video Grid (existing code) --}}
    <x-media.grid :mediaItems="$mediaItems" :isNsfw="$isNsfw" :bleep="$bleep" :count="$count" />
@endif
