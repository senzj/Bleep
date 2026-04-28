@once
    @push('scripts')
        @vite([
            'resources/js/bleep/posts/media/audio.js',
        ])
    @endpush
@endonce

@props([
    'src',
    'mime',
    'audioId'      => null,
    'downloadUrl'  => null,
    'downloadName' => null,
    'alt'          => '',
    'nsfw'         => false,
])

@php
    $audioId = $audioId ?: ('audio-' . uniqid());
@endphp

<div class="mt-2 rounded-xl border border-base-300 bg-base-200" data-bleep-media data-audio-player>
    <div class="p-4">

        {{-- Progress Bar --}}
        <div class="relative bg-base-300 rounded-full overflow-hidden h-3 sm:h-2 cursor-pointer group select-none mb-3"
             data-audio-progress-track>
            <div class="audio-buffered absolute left-0 top-0 h-full bg-base-content/10 rounded-full transition-all duration-300" style="width:0%"></div>
            <div class="audio-progress absolute left-0 top-0 h-full bg-primary rounded-full transition-all duration-300"   style="width:0%"></div>
            <div class="audio-hover-progress absolute left-0 top-0 h-full bg-primary/30 rounded-full
                        opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none"
                 style="width:0%"></div>
        </div>

        {{-- Controls Row --}}
        <div class="flex items-center justify-between gap-2">

            {{-- Left: Time --}}
            <div class="text-xs text-base-content/60 min-w-[70px] tabular-nums font-mono">
                <span class="audio-current-time" data-audio-id="{{ $audioId }}">0:00</span>
                <span class="mx-1">/</span>
                <span class="audio-total-time"   data-audio-id="{{ $audioId }}">0:00</span>
            </div>

            {{-- Center: Play/Pause --}}
            <div class="flex items-center gap-1">
                <button type="button"
                        class="audio-play-btn btn btn-primary btn-sm btn-circle shadow-md hover:scale-105 active:scale-95 transition-transform"
                        data-audio-id="{{ $audioId }}">
                    <span class="play-icon    pointer-events-none"><i data-lucide="play"  class="w-5 h-5 pointer-events-none"></i></span>
                    <span class="pause-icon   pointer-events-none" style="display:none;"><i data-lucide="pause" class="w-5 h-5 pointer-events-none"></i></span>
                    <span class="loading-icon pointer-events-none" style="display:none;"><span class="loading loading-spinner loading-sm"></span></span>
                </button>
            </div>

            {{-- Right: Speed + Download + Volume --}}
            <div class="flex items-center gap-3 min-w-[70px] justify-end">

                {{-- Speed --}}
                <div class="relative" x-data="{ open: false }">
                    <button type="button"
                            class="audio-speed-btn btn btn-ghost btn-xs"
                            data-audio-id="{{ $audioId }}"
                            @click="open = !open">
                        <span class="audio-speed-label text-xs font-medium">1x</span>
                    </button>
                    <div x-show="open"
                        x-cloak
                        @click.outside="open = false"
                        class="absolute bottom-full right-0 mb-2 bg-base-100 border border-base-300 rounded-lg shadow-lg p-1 w-20 z-50">

                        @foreach([0.5, 0.75, 1, 1.25, 1.5, 2] as $speed)
                            <button type="button"
                                    class="audio-speed-option text-xs w-full text-left px-2 py-1 rounded hover:bg-base-200 {{ $speed == 1 ? 'bg-primary/20 font-semibold' : '' }}"
                                    data-speed="{{ $speed }}"
                                    @click="open = false">{{ $speed }}x</button>
                        @endforeach
                    </div>
                </div>

                {{-- Download — mirrors Vue: always uses $src, derives filename by stripping the query string then taking the last path segment. `download` attribute always present so browser saves instead of navigates. --}}
                @php
                    $dlName = $downloadName ?: (
                        ($base = strtok($src, '?')) && ($parts = explode('/', $base))
                            ? (end($parts) ?: 'audio')
                            : 'audio'
                    );
                @endphp
                <a href="{{ $src }}"
                   download="{{ $dlName }}"
                   class="btn btn-ghost btn-xs btn-circle hidden sm:flex"
                   title="Download">
                    <i data-lucide="download" class="w-4 h-4"></i>
                </a>

                {{-- Volume — horizontal slider popup, fully reactive via Alpine --}}
                <div class="relative"
                    x-cloak
                    x-data="{
                        open: false,
                        isMuted: false,
                        icon: 'volume-2',
                        volume: Math.round((parseFloat(localStorage.getItem('bleepAudioVolume') ?? '1') || 1) * 100),
                        init() {
                            // Keep icon in sync when audio.js fires bleep:volume-icon
                            this.$el.addEventListener('bleep:volume-icon', (e) => {
                                this.icon   = e.detail.icon;
                                this.volume = e.detail.volumePct;
                                this.isMuted = e.detail.volume === 0;
                            });
                            // Set initial icon from stored volume
                            const v = this.volume;
                            this.icon = v === 0 ? 'volume-x' : v <= 50 ? 'volume-1' : 'volume-2';
                        }
                    }">

                    {{-- Toggle-popup button: reflects live volume icon --}}
                    <button type="button"
                            data-audio-volume-btn
                            class="btn btn-ghost btn-xs btn-circle"
                            @click="open = !open"
                            aria-label="Volume control">
                        <i :data-lucide="icon" class="w-4 h-4 pointer-events-none" data-audio-volume-icon></i>
                    </button>

                    {{-- Horizontal volume popup --}}
                    <div x-show="open"
                         x-transition
                         @click.outside="open = false"
                         class="absolute bottom-full right-0 mb-2 z-50
                                bg-base-100 border border-base-300 rounded-xl shadow-lg
                                flex items-center gap-2 px-3 py-2"
                         style="min-width:160px;">

                        {{-- Mute toggle button (inside popup) --}}
                        <button type="button"
                                class="shrink-0 transition-colors"
                                :class="isMuted ? 'text-error' : 'text-base-content/60 hover:text-base-content'"
                                @click="
                                    isMuted = !isMuted;
                                    if (isMuted) {
                                        $dispatch('audio-volume-change', 0);
                                    } else {
                                        $dispatch('audio-volume-change', volume || 100);
                                    }
                                "
                                :aria-label="isMuted ? 'Unmute' : 'Mute'">
                            <i :data-lucide="isMuted ? 'volume-x' : icon" class="w-4 h-4 pointer-events-none" data-audio-volume-icon></i>
                        </button>

                        {{-- Horizontal range slider --}}
                        <input type="range"
                               class="audio-volume-slider range range-xs range-primary flex-1"
                               data-audio-id="{{ $audioId }}"
                               min="0" max="100"
                               x-model="volume"
                               @input="isMuted = (volume == 0); $dispatch('audio-volume-change', volume)"
                               @change="isMuted = (volume == 0); $dispatch('audio-volume-change', volume)" />

                        {{-- Percentage label --}}
                        <span class="text-xs text-base-content/60 w-8 text-right tabular-nums" x-text="volume + '%'"></span>
                    </div>
                </div>

            </div>
        </div>

        {{-- Hidden Audio Element --}}
        <audio class="hidden audio-element"
               id="{{ $audioId }}"
               preload="none"
               data-src="{{ $src }}">
        </audio>
    </div>
</div>
