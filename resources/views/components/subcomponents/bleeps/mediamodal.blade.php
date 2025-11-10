@vite('resources/js/bleep/modals/mediamodal.js')

{{-- Media Viewer Modal --}}
<div id="media-modal" class="!m-0 hidden fixed inset-0 z-50 items-center justify-center bg-black/95 backdrop-blur-sm">
    {{-- Top controls --}}
    <div class="absolute top-4 right-4 z-50 flex items-center gap-2">
        {{-- Zoom controls --}}
        <div class="flex items-center gap-1 bg-black/50 rounded-full px-2 py-1">
            <button id="media-modal-zoom-out" class="btn btn-circle btn-ghost btn-sm text-white hover:bg-white/10">
                <i data-lucide="zoom-out" class="w-4 h-4"></i>
            </button>
            <span id="media-modal-zoom-level" class="text-white text-xs px-2">100%</span>
            <button id="media-modal-zoom-in" class="btn btn-circle btn-ghost btn-sm text-white hover:bg-white/10">
                <i data-lucide="zoom-in" class="w-4 h-4"></i>
            </button>
            <button id="media-modal-zoom-reset" class="btn btn-circle btn-ghost btn-sm text-white hover:bg-white/10" title="Reset zoom">
                <i data-lucide="maximize-2" class="w-4 h-4"></i>
            </button>
        </div>

        {{-- Close button --}}
        <button id="media-modal-close" class="btn btn-circle btn-ghost text-white hover:bg-white/10">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>
    </div>

    {{-- Navigation buttons --}}
    <button id="media-modal-prev" class="hidden absolute left-4 top-1/2 -translate-y-1/2 z-50 btn btn-circle btn-ghost text-white hover:bg-white/10">
        <i data-lucide="chevron-left" class="w-6 h-6"></i>
    </button>

    <button id="media-modal-next" class="hidden absolute right-4 top-1/2 -translate-y-1/2 z-50 btn btn-circle btn-ghost text-white hover:bg-white/10">
        <i data-lucide="chevron-right" class="w-6 h-6"></i>
    </button>

    {{-- Media container --}}
    <div id="media-modal-container" class="flex items-center justify-center max-w-5xl max-h-[90vh] w-full select-none px-8">
        {{-- Image --}}
        <img id="media-modal-image"
             src=""
             alt=""
             class="hidden max-w-full max-h-full object-contain"
             draggable="false"
             style="transform-origin: center center;">

        {{-- Video --}}
        <video id="media-modal-video"
               class="hidden max-w-full max-h-full object-contain"
               controls
               autoplay>
            <source id="media-modal-video-source" src="" type="">
        </video>
    </div>

    {{-- Counter --}}
    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 text-white text-sm bg-black/50 px-4 py-2 rounded-full">
        <span id="media-modal-counter">1 / 4</span>
    </div>
</div>
