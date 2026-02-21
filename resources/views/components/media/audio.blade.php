@push('scripts')
    @vite([
        'resources/js/bleep/posts/media/audio.js',
    ])
@endpush

@props([
    'src',
    'mime',
    'audioId' => null,
    'downloadUrl' => null,
    'downloadName' => null,
    'alt' => '',
    'nsfw' => false,
])

@php
    $audioId = $audioId ?: ('audio-' . uniqid());
@endphp

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
                <span class="audio-current-time" data-audio-id="{{ $audioId }}">0:00</span>
                <span class="mx-1">/</span>
                <span class="audio-total-time" data-audio-id="{{ $audioId }}">0:00</span>
            </div>

            {{-- Center: Play/Pause Only --}}
            <div class="flex items-center gap-1">
                <button type="button"
                        class="audio-play-btn btn btn-primary btn-sm btn-circle shadow-md hover:scale-105 active:scale-95 transition-transform"
                    data-audio-id="{{ $audioId }}">
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
                            data-audio-id="{{ $audioId }}"
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
                @if($downloadUrl)
                    <a href="{{ $downloadUrl }}"
                        @if($downloadName) download="{{ $downloadName }}" @endif
                        class="btn btn-ghost btn-xs btn-circle tooltip tooltip-left hidden sm:flex"
                        data-tip="Download">
                        <i data-lucide="download" class="w-4 h-4"></i>
                    </a>
                @endif

                {{-- Volume Control --}}
                <div class="audio-volume-wrapper flex items-center gap-1">
                    <button type="button"
                            class="audio-volume-btn btn btn-ghost btn-xs btn-circle"
                            data-audio-id="{{ $audioId }}">
                        <span class="volume-high-icon pointer-events-none"><i data-lucide="volume-2" class="w-4 h-4 pointer-events-none"></i></span>
                        <span class="volume-low-icon pointer-events-none" style="display: none;"><i data-lucide="volume-1" class="w-4 h-4 pointer-events-none"></i></span>
                        <span class="volume-mute-icon pointer-events-none" style="display: none;"><i data-lucide="volume-x" class="w-4 h-4 pointer-events-none"></i></span>
                    </button>

                    {{-- Volume Slider (Desktop) --}}
                    <div class="audio-volume-slider-container hidden sm:flex items-center w-16 group">
                        <input type="range"
                                class="audio-volume-slider range range-xs range-primary w-full"
                            data-audio-id="{{ $audioId }}"
                                min="0"
                                max="100"
                                value="100">
                    </div>
                </div>
            </div>
        </div>

        {{-- Hidden Audio Element - deferred loading --}}
        <audio class="hidden audio-element"
            id="{{ $audioId }}"
            preload="none"
            data-src="{{ $src }}">
        </audio>
    </div>
</div>
