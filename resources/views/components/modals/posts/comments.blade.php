<div
    x-data="commentSheet"
    x-cloak
    @open-comments.window="openSheet($event.detail.bleepId)"
    @keydown.escape.window="close()"
>
    <div
        x-show="open"
        style="display:none"
        @click.stop
        class="fixed z-50 bg-base-300 shadow-2xl border border-base-300/50 flex flex-col overflow-hidden max-w-full overflow-x-hidden will-change-transform overflow-y-auto"
        :class="isMobile
            ? 'left-0 right-0 bottom-0 mx-auto rounded-t-2xl'
            : 'top-1/2 right-4 -translate-y-1/2 rounded-2xl'"
        :style="isMobile ? mobileStyle : desktopStyle"
    >
        {{-- Drag Handle --}}
        <div
            class="shrink-0 flex justify-center py-3 select-none touch-none"
            :class="[isMobile ? 'block' : 'hidden', dragging ? 'cursor-grabbing' : 'cursor-grab']"
            @mousedown="startDrag($event)"
            @touchstart.passive="startDrag($event)"
        >
            <div
                class="h-1.5 rounded-full bg-base-content/30 transition-all duration-200"
                :class="dragging ? 'w-14 bg-base-content/60' : 'w-10'"
            ></div>
        </div>

        {{-- Content --}}
        <div class="flex-1 min-h-0">
            <x-subcomponents.comments.layout :layoutmode="'modal'" />
        </div>
    </div>

    {{-- Overlay --}}
    <div
        x-show="open"
        style="display:none"
        class="fixed inset-0 z-40"
        :class="isMobile ? 'bg-black/40' : 'bg-transparent pointer-events-none'"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
    ></div>

</div>

@push('scripts')
    @vite(['resources/js/bleep/comments/commentSheet.js'])
@endpush

{{-- Dispatch close to app.js --}}
