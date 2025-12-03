@php
    $count = $mediaItems->count();
    $nsfwClass = $isNsfw ? 'nsfw-media' : '';
    $nsfwAttr = $isNsfw ? 'data-media-src' : 'src';
    $firstItem = $mediaItems->first();
    $isAudioOnly = $firstItem && $firstItem->type === 'audio';

    // Generate unique audio ID to prevent conflicts with duplicate bleeps (e.g., reposts)
    $uniqueAudioId = 'audio-' . $bleep->id . '-' . uniqid();
@endphp

{{-- Audio files are always single, so handle them separately --}}
@if($isAudioOnly)

    <div class="mt-2 rounded-xl border border-base-300 bg-base-200" data-bleep-media data-audio-player>
        <div class="p-4">
            {{-- Progress Bar --}}
            <div class="relative bg-base-300 rounded-full overflow-hidden h-3 sm:h-2 md:h-2 lg:h-2 cursor-pointer group select-none mb-3"
                 data-audio-progress-track>

                {{-- Buffered --}}
                <div class="audio-buffered absolute left-0 top-0 h-full bg-base-content/10 rounded-full transition-all duration-300" style="width: 0%"></div>

                {{-- Progress --}}
                <div class="audio-progress absolute left-0 top-0 h-full bg-primary rounded-full transition-all duration-300" style="width: 0%"></div>

                {{-- Hover / Seek Preview --}}
                <div class="audio-hover-progress absolute left-0 top-0 h-full bg-primary/30 rounded-full
                            opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none"
                    style="width: 0%"></div>
            </div>
            {{-- Controls Row --}}
            <div class="flex items-center justify-between gap-2">
                {{-- Left: Time --}}
                <div class="text-xs text-base-content/60 min-w-[70px] tabular-nums font-mono">
                    <span class="audio-current-time" data-audio-id="{{ $uniqueAudioId }}">0:00</span>
                    <span class="mx-1">/</span>
                    <span class="audio-total-time" data-audio-id="{{ $uniqueAudioId }}">0:00</span>
                </div>

                {{-- Center: Play/Pause Only --}}
                <div class="flex items-center gap-1">
                    <button type="button"
                            class="audio-play-btn btn btn-primary btn-sm btn-circle shadow-md hover:scale-105 active:scale-95 transition-transform"
                            data-audio-id="{{ $uniqueAudioId }}">
                        <span class="play-icon pointer-events-none"><i data-lucide="play" class="w-5 h-5 pointer-events-none"></i></span>
                        <span class="pause-icon pointer-events-none" style="display: none;"><i data-lucide="pause" class="w-5 h-5 pointer-events-none"></i></span>
                        <span class="loading-icon pointer-events-none" style="display: none;"><span class="loading loading-spinner loading-sm"></span></span>
                    </button>
                </div>

                {{-- Right: Volume & Speed --}}
                <div class="flex items-center gap-1 min-w-[70px] justify-end">

                    {{-- Playback Speed (Desktop only) --}}
                    <div class="dropdown dropdown-top dropdown-end hidden sm:block">
                        <button tabindex="0"
                                class="audio-speed-btn btn btn-ghost btn-xs tooltip tooltip-left"
                                data-audio-id="{{ $uniqueAudioId }}"
                                data-tip="Playback speed">
                            <span class="audio-speed-label text-xs font-medium">1x</span>
                        </button>
                        <ul tabindex="0" class="dropdown-content z-10 menu p-1 shadow-lg bg-base-100 rounded-lg w-20 border border-base-300"
                            data-audio-speed-menu>
                            <li><button type="button" class="audio-speed-option text-xs" data-speed="0.5">0.5x</button></li>
                            <li><button type="button" class="audio-speed-option text-xs" data-speed="0.75">0.75x</button></li>
                            <li><button type="button" class="audio-speed-option text-xs active bg-primary/20" data-speed="1">1x</button></li>
                            <li><button type="button" class="audio-speed-option text-xs" data-speed="1.25">1.25x</button></li>
                            <li><button type="button" class="audio-speed-option text-xs" data-speed="1.5">1.5x</button></li>
                            <li><button type="button" class="audio-speed-option text-xs" data-speed="2">2x</button></li>
                        </ul>
                    </div>

                    {{-- Download button --}}
                    <a href="{{ asset('storage/'.$firstItem->path) }}"
                        download="{{ $firstItem->original_name }}"
                        class="btn btn-ghost btn-xs btn-circle tooltip tooltip-left hidden sm:flex"
                        data-tip="Download">
                        <i data-lucide="download" class="w-4 h-4"></i>
                    </a>

                    {{-- Volume Control --}}
                    <div class="audio-volume-wrapper flex items-center gap-1">
                        <button type="button"
                                class="audio-volume-btn btn btn-ghost btn-xs btn-circle"
                                data-audio-id="{{ $uniqueAudioId }}">
                            <span class="volume-high-icon pointer-events-none"><i data-lucide="volume-2" class="w-4 h-4 pointer-events-none"></i></span>
                            <span class="volume-low-icon pointer-events-none" style="display: none;"><i data-lucide="volume-1" class="w-4 h-4 pointer-events-none"></i></span>
                            <span class="volume-mute-icon pointer-events-none" style="display: none;"><i data-lucide="volume-x" class="w-4 h-4 pointer-events-none"></i></span>
                        </button>

                        {{-- Volume Slider (Desktop) --}}
                        <div class="audio-volume-slider-container hidden sm:flex items-center w-16 group">
                            <input type="range"
                                   class="audio-volume-slider range range-xs range-primary w-full"
                                   data-audio-id="{{ $uniqueAudioId }}"
                                   min="0"
                                   max="100"
                                   value="100">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Hidden Audio Element --}}
            <audio class="hidden audio-element"
                id="{{ $uniqueAudioId }}"
                preload="metadata"
                src="{{ route('media.stream', ['path' => $firstItem->path]) }}">
            </audio>
        </div>
    </div>

@else
    {{-- Image/Video Grid (existing code) --}}
    <div class="mt-2 overflow-hidden rounded-xl border border-base-300 {{ $isNsfw ? 'nsfw-media-container' : 'bleep-media-gallery' }}" data-bleep-media>
    @if ($count === 1)
        @php $m = $mediaItems->first(); @endphp
        <div class="flex items-center justify-center bg-base-200">
            <div class="relative cursor-pointer group"
                data-media-index="0"
                data-media-type="{{ $m->type }}"
                data-media-src="{{ asset('storage/'.$m->path) }}"
                data-media-alt="{{ $m->original_name }}"
                data-media-mime="{{ $m->mime_type }}">
                @if($m->type === 'image')
                    <img class="{{ $nsfwClass }} max-h-96 w-auto rounded-lg object-cover"
                        {{ $nsfwAttr }}="{{ asset('storage/'.$m->path) }}"
                        alt="{{ $m->original_name }}"
                        loading="lazy">
                @else
                    <video class="{{ $nsfwClass }} max-h-96 w-auto rounded-lg object-contain" controls preload="metadata">
                        <source {{ $nsfwAttr }}="{{ asset('storage/'.$m->path) }}"
                                {{ !$isNsfw ? 'type="' . $m->mime_type . '"' : 'data-media-mime="' . $m->mime_type . '"' }}>
                    </video>
                @endif
            </div>
        </div>
    @elseif($count === 2)
        <div class="grid grid-cols-2 gap-1 bg-base-200">
            @foreach($mediaItems as $index => $m)
                <div class="flex items-center justify-center overflow-hidden">
                    <div class="relative cursor-pointer group w-full"
                        data-media-index="{{ $index }}"
                        data-media-type="{{ $m->type }}"
                        data-media-src="{{ asset('storage/'.$m->path) }}"
                        data-media-alt="{{ $m->original_name }}"
                        data-media-mime="{{ $m->mime_type }}">
                        @if($m->type === 'image')
                            <img class="{{ $nsfwClass }} max-h-64 w-full object-cover rounded-lg"
                                {{ $nsfwAttr }}="{{ asset('storage/'.$m->path) }}"
                                alt="{{ $m->original_name }}"
                                loading="lazy">
                        @else
                            <video class="{{ $nsfwClass }} max-h-64 w-full rounded-lg object-contain" controls preload="metadata">
                                <source {{ $nsfwAttr }}="{{ asset('storage/'.$m->path) }}"
                                        {{ !$isNsfw ? 'type="' . $m->mime_type . '"' : 'data-media-mime="' . $m->mime_type . '"' }}>
                            </video>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @elseif($count === 3)
        <div class="grid grid-cols-3 grid-rows-2 gap-1 bg-base-200">
            @foreach($mediaItems as $index => $m)
                <div class="{{ $index === 2 ? 'col-span-2 row-span-2' : 'col-span-1 row-span-1' }}">
                    <div class="relative cursor-pointer group h-full"
                        data-media-index="{{ $index }}"
                        data-media-type="{{ $m->type }}"
                        data-media-src="{{ asset('storage/'.$m->path) }}"
                        data-media-alt="{{ $m->original_name }}"
                        data-media-mime="{{ $m->mime_type }}">
                        @if($m->type === 'image')
                            <img class="{{ $nsfwClass }} w-full {{ $index === 2 ? 'h-full' : 'h-40' }} object-cover rounded-lg"
                                {{ $nsfwAttr }}="{{ asset('storage/'.$m->path) }}"
                                alt="{{ $m->original_name }}"
                                loading="lazy">
                        @else
                            <video class="{{ $nsfwClass }} w-full {{ $index === 2 ? 'h-full' : 'h-40' }} object-contain rounded-lg" controls preload="metadata">
                                <source {{ $nsfwAttr }}="{{ asset('storage/'.$m->path) }}"
                                        {{ !$isNsfw ? 'type="' . $m->mime_type . '"' : 'data-media-mime="' . $m->mime_type . '"' }}>
                            </video>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="grid grid-cols-2 gap-1 bg-base-200">
            @foreach($mediaItems as $index => $m)
                <div class="relative overflow-hidden">
                    <div class="relative cursor-pointer group"
                        data-media-index="{{ $index }}"
                        data-media-type="{{ $m->type }}"
                        data-media-src="{{ asset('storage/'.$m->path) }}"
                        data-media-alt="{{ $m->original_name }}"
                        data-media-mime="{{ $m->mime_type }}">
                        @if($m->type === 'image')
                            <img class="{{ $nsfwClass }} w-full h-40 object-cover rounded-lg"
                                {{ $nsfwAttr }}="{{ asset('storage/'.$m->path) }}"
                                alt="{{ $m->original_name }}"
                                loading="lazy">
                        @else
                            <video class="{{ $nsfwClass }} w-full h-40 rounded-lg object-contain" controls preload="metadata">
                                <source {{ $nsfwAttr }}="{{ asset('storage/'.$m->path) }}"
                                        {{ !$isNsfw ? 'type="' . $m->mime_type . '"' : 'data-media-mime="' . $m->mime_type . '"' }}>
                            </video>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    </div>
@endif
