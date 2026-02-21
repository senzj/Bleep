<div
    x-data="{ open: false, bleepId: null }"
    @open-comments.window="bleepId = $event.detail.bleepId; open = true"
    @close-comments.window="open = false"
    @keydown.escape.window="open = false"
>
    {{-- Comments Modal Container --}}
    <div
        id="floating-comments-modal"
        x-cloak
        x-show="open"
        x-transition.opacity
        class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-50 w-[50vw] lg:w-[35vw] max-w-2xl h-[85vh] bg-base-300 rounded-2xl shadow-2xl border border-base-300/50 flex flex-col overflow-hidden lg:left-auto lg:right-6 lg:translate-x-0"
        @click.stop
    >
        <x-subcomponents.comments.layout :layoutmode="'modal'" />
    </div>

    {{-- Overlay for modals --}}
    <div
        id="comments-overlay"
        x-cloak
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-40"
        @click="open = false"
    ></div>

</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {

        function removeBleepHighlight() {
            document.querySelectorAll('[data-bleep-card]').forEach(el => el.classList.remove('bleep-selected'));
        }

        // Open — highlight selected card
        window.addEventListener('open-comments', (e) => {
            removeBleepHighlight();
            const bleepId = String(e.detail.bleepId ?? '').trim();
            if (!bleepId) return;

            requestAnimationFrame(() => {
                const card = document.querySelector(`[data-bleep-card="${bleepId}"]`);
                if (card) card.classList.add('bleep-selected');
            });
        });

        // Close — remove highlight (covers overlay click, escape, X button, scroll away)
        window.addEventListener('close-comments', removeBleepHighlight);

        // Escape fallback
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') removeBleepHighlight();
        });

        // Overlay click — use event delegation since Alpine renders it late
        document.addEventListener('click', (e) => {
            if (e.target.id === 'comments-overlay') {
                removeBleepHighlight();
            }
        });

    });
</script>
@endpush
