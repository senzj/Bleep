<div
    x-data="commentSheet"
    x-cloak
    @open-comments.window="openSheet($event.detail.bleepId)"
    @keydown.escape.window="close()"
>

    {{-- Single Modal --}}
    <div
        x-cloak
        x-show="open"
        @click.stop
        class="fixed z-50 bg-base-300 shadow-2xl border border-base-300/50 flex flex-col overflow-hidden max-w-full overflow-x-hidden will-change-transform overflow-y-auto"
        :class="isMobile
            ? 'left-0 right-0 bottom-0 mx-auto rounded-t-2xl'
            : 'top-1/2 right-4 -translate-y-1/2 rounded-2xl'"
        :style="isMobile ? mobileStyle : desktopStyle"
    >
        {{-- Drag Handle — both touch and mouse --}}
        <div
            class="shrink-0 flex justify-center py-3 select-none touch-none"
            :class="[isMobile ? 'block' : 'hidden', dragging ? 'cursor-grabbing' : 'cursor-grab']"
            @mousedown="startDrag($event)"
            @touchstart.passive="startDrag($event)"
        >
            <div class="h-1.5 rounded-full bg-base-content/30 transition-all duration-200"
                :class="dragging ? 'w-14 bg-base-content/60' : 'w-10'">
            </div>
        </div>

        {{-- Content --}}
        <div class="flex-1 min-h-0">
            <x-subcomponents.comments.layout :layoutmode="'modal'" />
        </div>
    </div>

    {{-- Overlay — use native window.dispatchEvent, not Alpine $dispatch --}}
    <div
        x-cloak
        x-show="open"
        class="fixed inset-0 z-40"
        :class="isMobile ? 'bg-black/40' : 'bg-transparent'"
        x-transition.opacity
        @click="close()"
    ></div>

</div>

@push('scripts')
    @vite([
        'resources/js/bleep/comments/commentSheet.js',
    ])
@endpush

{{-- Dispatch close to app.js --}}
