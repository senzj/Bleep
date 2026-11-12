@once
    @push('scripts')
        @vite([
            'resources/js/bleep/posts/media/video.js',
            'resources/js/bleep/posts/media/visibility.js',
        ])
    @endpush
@endonce

@props([
    'mediaItems',
    'isNsfw' => false,
    'bleep',
    'count' => null,
    'miniTitle' => null,
    'miniSubtitle' => null,
    'posterName' => null,
    'posterHandle' => null,
    'posterAvatar' => null,
])

@php
    $count = $count ?? $mediaItems->count();
    // Single media: border hugs the content. Multi-media: grid fills the width.
    $widthClass = $count === 1 ? 'media-single mx-auto w-fit max-w-full' : 'w-full';
@endphp

<div class="mt-0.5 {{ $widthClass }} overflow-hidden rounded-xl border border-base-300 {{ $isNsfw ? 'nsfw-media-container' : 'bleep-media-gallery' }}" data-bleep-media
    @if($miniTitle) data-media-title="{{ $miniTitle }}" @endif
    @if($miniSubtitle) data-media-subtitle="{{ $miniSubtitle }}" @endif
    @if($posterName) data-media-poster="{{ $posterName }}" @endif
    @if($posterHandle) data-media-poster-handle="{{ $posterHandle }}" @endif
    @if($posterAvatar) data-media-poster-avatar="{{ $posterAvatar }}" @endif
>
    @if ($count === 1)
        <div class="flex items-center justify-center">
            <x-media.tile
                :media="$mediaItems->first()"
                :index="0"
                :isNsfw="$isNsfw"
                object-class="h-full w-full max-h-72 min-h-48 object-cover"
                video-class="block h-auto w-auto max-h-72 max-w-full object-contain"
                :wrap-video="false"
                class="min-h-48"
            />
        </div>
    @elseif($count === 2)
        <div class="grid h-72 grid-cols-2 gap-1 bg-base-200">
            @foreach($mediaItems as $index => $media)
                <div class="flex items-center justify-center overflow-hidden">
                    <x-media.tile
                        :media="$media"
                        :index="$index"
                        :isNsfw="$isNsfw"
                        object-class="h-full w-full object-cover"
                        class="h-full w-full"
                    />
                </div>
            @endforeach
        </div>
    @elseif($count === 3)
        <div class="grid h-72 grid-cols-2 grid-rows-2 gap-1 bg-base-200">
            @foreach($mediaItems as $index => $media)
                <div class="{{ $index === 0 ? 'col-span-1 row-span-2' : 'col-span-1 row-span-1' }} overflow-hidden">
                    <x-media.tile
                        :media="$media"
                        :index="$index"
                        :isNsfw="$isNsfw"
                        object-class="h-full w-full object-cover"
                        class="h-full w-full"
                    />
                </div>
            @endforeach
        </div>
    @else
        <div class="grid h-72 grid-cols-2 gap-1 bg-base-200">
            @foreach($mediaItems as $index => $media)
                @if ($index >= 4)
                    {{-- Extra items stay in the DOM (hidden) so the viewer can page through all of them. --}}
                    <div class="hidden" aria-hidden="true">
                        <x-media.tile
                            :media="$media"
                            :index="$index"
                            :isNsfw="$isNsfw"
                        />
                    </div>
                @else
                    <div class="relative overflow-hidden">
                        <x-media.tile
                            :media="$media"
                            :index="$index"
                            :isNsfw="$isNsfw"
                            object-class="h-full w-full object-cover"
                            video-class="h-full w-full object-cover"
                        />

                        @if ($index === 3 && $count > 4)
                            <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-black/55 text-2xl font-bold text-white" aria-hidden="true">
                                +{{ $count - 3 }}
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
