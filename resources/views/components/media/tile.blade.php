@props([
    'media',
    'index' => 0,
    'isNsfw' => false,
    'objectClass' => 'h-64 w-full object-cover',
    'videoClass' => null,
    'wrapVideo' => true,
])

@php
    $nsfwAttr = $isNsfw ? 'data-media-src' : 'data-src';
    $nsfwClass = $isNsfw ? 'nsfw-media' : '';
    $videoClass = $videoClass ?: $objectClass;

    $isImage = $media->type === 'image';
    $streamUrl = $isImage ? null : route('media.stream', ['path' => $media->path]);
    $thumbUrl = $isImage ? $media->thumbnailUrl() : $streamUrl;
    $viewUrl = $isImage ? $media->viewerUrl() : $streamUrl;
@endphp

<div class="relative cursor-pointer group media-pending bg-base-200 {{ $attributes->get('class') }}"
    {{ $attributes->except('class') }}
    data-media-index="{{ $index }}"
    data-media-type="{{ $media->type }}"
    data-media-src="{{ $thumbUrl }}"
    data-media-view="{{ $viewUrl }}"
    data-media-original="{{ $isImage ? $media->url() : $streamUrl }}"
    data-media-alt="{{ $media->original_name }}"
    data-media-mime="{{ $media->mime_type }}">

    {{-- Fills the tile while the media downloads/decodes, so the post never
         shows a thin or broken media box on first paint. --}}
    <div class="media-skeleton skeleton absolute inset-0 z-[1] h-full w-full rounded-none pointer-events-none motion-reduce:animate-none" aria-hidden="true"></div>

    @if($media->type === 'image')
        <img class="{{ $nsfwClass }} {{ $objectClass }}"
            {{ $nsfwAttr }}="{{ $thumbUrl }}"
            alt="{{ $media->original_name }}"
            loading="lazy">
    @elseif($wrapVideo)
        <div class="relative {{ $objectClass === 'h-full w-full object-cover' ? 'h-full' : 'h-64' }} w-full bg-base-300 overflow-hidden flex items-center justify-center">
            <video class="{{ $nsfwClass }} {{ $videoClass }} pointer-events-none"
                   controls
                   preload="metadata"
                   muted
                   playsinline>
                <source {{ $nsfwAttr }}="{{ $streamUrl }}"
                        type="{{ $media->mime_type }}">
            </video>
        </div>
    @else
        <video class="{{ $nsfwClass }} {{ $videoClass }} pointer-events-none"
               controls
               preload="metadata"
               muted
               playsinline>
            <source {{ $nsfwAttr }}="{{ $streamUrl }}"
                    type="{{ $media->mime_type }}">
            Your browser does not support the video tag.
        </video>
    @endif
</div>
