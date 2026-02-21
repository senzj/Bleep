@push('scripts')
    @vite([
        'resources/js/bleep/posts/media/video.js',
        'resources/js/bleep/posts/media/visibility.js',
    ])
@endpush

@props([
    'mediaItems',
    'isNsfw' => false,
    'bleep',
    'count' => null,
])

@php
    $count = $count ?? $mediaItems->count();
    $nsfwClass = $isNsfw ? 'nsfw-media' : '';
    $nsfwAttr = $isNsfw ? 'data-media-src' : 'data-src';
@endphp

<div class="mt-2 overflow-hidden rounded-xl border border-base-300 {{ $isNsfw ? 'nsfw-media-container' : 'bleep-media-gallery' }}" data-bleep-media>
    @if ($count === 1)
        @php $m = $mediaItems->first(); @endphp
        <div class="flex items-center justify-center bg-base-200 h-64">
            <div class="relative cursor-pointer group"
                data-media-index="0"
                data-media-type="{{ $m->type }}"
                data-media-src="{{ $m->type === 'video' ? route('media.stream', ['path' => $m->path]) : asset('storage/'.$m->path) }}"
                data-media-alt="{{ $m->original_name }}"
                data-media-mime="{{ $m->mime_type }}">
                @if($m->type === 'image')
                    <img class="{{ $nsfwClass }} max-h-96 w-auto rounded-lg object-cover"
                        {{ $nsfwAttr }}="{{ asset('storage/'.$m->path) }}"
                        alt="{{ $m->original_name }}"
                        loading="lazy">
                @else
                    <video class="{{ $nsfwClass }} max-h-96 w-auto rounded-lg object-contain"
                           controls
                           preload="metadata"
                           muted
                           playsinline>
                        <source {{ $nsfwAttr }}="{{ route('media.stream', ['path' => $m->path]) }}"
                                type="{{ $m->mime_type }}">
                        Your browser does not support the video tag.
                    </video>
                @endif
            </div>
        </div>
    @elseif($count === 2)
        <div class="grid grid-cols-2 gap-1 bg-base-200 h-64">
            @foreach($mediaItems as $index => $m)
                <div class="flex items-center justify-center overflow-hidden">
                    <div class="relative cursor-pointer group w-full"
                        data-media-index="{{ $index }}"
                        data-media-type="{{ $m->type }}"
                        data-media-src="{{ $m->type === 'video' ? route('media.stream', ['path' => $m->path]) : asset('storage/'.$m->path) }}"
                        data-media-alt="{{ $m->original_name }}"
                        data-media-mime="{{ $m->mime_type }}">
                        @if($m->type === 'image')
                            <img class="{{ $nsfwClass }} h-full w-full object-cover rounded-lg"
                                {{ $nsfwAttr }}="{{ asset('storage/'.$m->path) }}"
                                alt="{{ $m->original_name }}"
                                loading="lazy">
                        @else
                            <div class="relative w-full h-full bg-base-300 rounded-lg overflow-hidden flex items-center justify-center">
                                @if($isNsfw)
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <i data-lucide="play-circle" class="w-16 h-16 text-base-content/30"></i>
                                    </div>
                                @endif
                                <video class="{{ $nsfwClass }} w-full h-full object-cover rounded-lg"
                                       controls
                                       preload="metadata"
                                       muted
                                       playsinline>
                                    <source {{ $nsfwAttr }}="{{ route('media.stream', ['path' => $m->path]) }}"
                                            type="{{ $m->mime_type }}">
                                </video>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @elseif($count === 3)
        <div class="grid grid-cols-2 grid-rows-2 gap-1 bg-base-200 h-64">
            @foreach($mediaItems as $index => $m)
                <div class="{{ $index === 0 ? 'col-span-1 row-span-2' : 'col-span-1 row-span-1' }}">
                    <div class="relative cursor-pointer group h-full w-full"
                        data-media-index="{{ $index }}"
                        data-media-type="{{ $m->type }}"
                        data-media-src="{{ $m->type === 'video' ? route('media.stream', ['path' => $m->path]) : asset('storage/'.$m->path) }}"
                        data-media-alt="{{ $m->original_name }}"
                        data-media-mime="{{ $m->mime_type }}">
                        @if($m->type === 'image')
                            <img class="{{ $nsfwClass }} w-full h-full object-cover rounded-lg"
                                {{ $nsfwAttr }}="{{ asset('storage/'.$m->path) }}"
                                alt="{{ $m->original_name }}"
                                loading="lazy">
                        @else
                            <div class="relative w-full h-full bg-base-300 rounded-lg overflow-hidden flex items-center justify-center">
                                @if($isNsfw)
                                    <div class="absolute inset-0 flex items-center justify-center z-10">
                                        <i data-lucide="play-circle" class="w-16 h-16 text-base-content/30"></i>
                                    </div>
                                @endif
                                <video class="{{ $nsfwClass }} w-full h-full object-cover rounded-lg"
                                       controls
                                       preload="metadata"
                                       muted
                                       playsinline>
                                    <source {{ $nsfwAttr }}="{{ route('media.stream', ['path' => $m->path]) }}"
                                            type="{{ $m->mime_type }}">
                                </video>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="grid grid-cols-2 gap-1 bg-base-200 h-64">
            @foreach($mediaItems as $index => $m)
                <div class="relative overflow-hidden">
                    <div class="relative cursor-pointer group"
                        data-media-index="{{ $index }}"
                        data-media-type="{{ $m->type }}"
                        data-media-src="{{ $m->type === 'video' ? route('media.stream', ['path' => $m->path]) : asset('storage/'.$m->path) }}"
                        data-media-alt="{{ $m->original_name }}"
                        data-media-mime="{{ $m->mime_type }}">
                        @if($m->type === 'image')
                            <img class="{{ $nsfwClass }} w-full h-full object-cover rounded-lg"
                                {{ $nsfwAttr }}="{{ asset('storage/'.$m->path) }}"
                                alt="{{ $m->original_name }}"
                                loading="lazy">
                        @else
                            <div class="relative w-full h-full bg-base-300 rounded-lg overflow-hidden flex items-center justify-center">
                                @if($isNsfw)
                                    <div class="absolute inset-0 flex items-center justify-center z-10">
                                        <i data-lucide="play-circle" class="w-16 h-16 text-base-content/30"></i>
                                    </div>
                                @endif
                                <video class="{{ $nsfwClass }} w-full h-full object-cover rounded-lg"
                                       controls
                                       preload="metadata"
                                       muted
                                       playsinline>
                                    <source {{ $nsfwAttr }}="{{ route('media.stream', ['path' => $m->path]) }}"
                                            type="{{ $m->mime_type }}">
                                </video>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
