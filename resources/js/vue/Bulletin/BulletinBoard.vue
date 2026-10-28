<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DeleteNoteDialog from './DeleteNoteDialog.vue';
import EditNoteDialog from './EditNoteDialog.vue';
import NoteComposer from './NoteComposer.vue';
import NoteDialog from './NoteDialog.vue';
import ReportDialog from './ReportDialog.vue';
import StickyNote from './StickyNote.vue';
import {
    BOARD_PADDING,
    DESKTOP_MAX_ROWS,
    MIN_NOTE_CELL,
    NOTE_GAP,
    boardMetrics,
    composerRect,
    noteFontSize,
    packNotes,
} from './boardLayout';

const props = defineProps({
    initialPosts: { type: Array, default: () => [] },
    nextCursor: { type: String, default: null },
    hasMore: { type: Boolean, default: false },
    identity: { type: String, default: '' },
    homeUrl: { type: String, default: '/' },
    endpoints: {
        type: Object,
        required: true,
    },
});

const notes = ref([...props.initialPosts]);
const selectedNoteId = ref(null);
const selectedNote = computed(
    () => notes.value.find((note) => note.id === selectedNoteId.value) ?? null
);
const reportingNoteId = ref(null);
const reportingNote = computed(
    () => notes.value.find((note) => note.id === reportingNoteId.value) ?? null
);
const editingNoteId = ref(null);
const editingNote = computed(
    () => notes.value.find((note) => note.id === editingNoteId.value) ?? null
);
const deletingNoteId = ref(null);
const deletingNote = computed(
    () => notes.value.find((note) => note.id === deletingNoteId.value) ?? null
);
const cursor = ref(props.nextCursor);
const moreAvailable = ref(props.hasMore);
const loading = ref(false);
const loadError = ref('');
const toastMessage = ref('');
const pendingReactions = ref({});
const openMenu = ref(null);
const newNoteId = ref(null);
const scroller = ref(null);
const chromeElement = ref(null);
const viewportWidth = ref(1200);
const viewportHeight = ref(700);
const chromeHeight = ref(64);
const hasOverflow = ref(false);
const showMobileComposer = ref(false);

const desktopQuery = window.matchMedia('(min-width: 768px)');
const isDesktop = ref(desktopQuery.matches);

const board = computed(() =>
    boardMetrics(
        viewportWidth.value,
        isDesktop.value ? availableHeight.value : null,
        rows.value
    )
);
const cell = computed(() => board.value.cell);
const boardPadding = computed(() => board.value.padding);
const columnCount = computed(() => board.value.columns);
const pitch = computed(() => cell.value + NOTE_GAP);

/**
 * Desktop scrollbars take real height away from the viewport. Reserving a
 * little space keeps a horizontal board from sprouting a second scrollbar.
 */
const scrollAllowance = computed(() => (isDesktop.value ? 16 : 4));

/**
 * The page padding is derived from the width only. Reading it from `board`
 * would make the height depend on `board`, while `board` already depends on the
 * height, and that cycle leaves the cell size one resize behind.
 */
const pagePadding = computed(() => (viewportWidth.value < 640 ? 12 : BOARD_PADDING));

const availableHeight = computed(
    () =>
        viewportHeight.value -
        chromeHeight.value -
        pagePadding.value * 2 -
        scrollAllowance.value
);

/**
 * Four rows on a typical 1080p laptop, fewer on shorter screens. The composer
 * and in-board header are measured, so they never sit on top of a note.
 */
const rows = computed(() => {
    if (!isDesktop.value) {
        return 2;
    }

    const fit = Math.floor((availableHeight.value + NOTE_GAP) / (MIN_NOTE_CELL + NOTE_GAP));

    return Math.max(2, Math.min(DESKTOP_MAX_ROWS, fit));
});

const reservedRects = computed(() => {
    const rect = isDesktop.value
        ? composerRect(rows.value, 2, 2)
        : { x: 0, y: 0, w: 1, h: 1 };

    return [rect];
});

const layout = computed(() =>
    packNotes(notes.value, {
        rows: rows.value,
        reserved: reservedRects.value,
    })
);

const gridHeight = computed(() => rows.value * pitch.value - NOTE_GAP);

const gridTop = computed(() => {
    const leftover = Math.max(0, availableHeight.value - gridHeight.value);

    return chromeHeight.value + boardPadding.value + Math.round(leftover / 2);
});

const metrics = computed(() => ({
    cell: cell.value,
    gap: NOTE_GAP,
    offsetX: boardPadding.value,
    offsetY: gridTop.value,
    fontSize: noteFontSize(cell.value),
}));

const worldStyle = computed(() => ({
    width: `${Math.max(columnCount.value, layout.value.columns) * pitch.value - NOTE_GAP + boardPadding.value * 2}px`,
    height: `${Math.max(
        viewportHeight.value - scrollAllowance.value,
        gridTop.value + gridHeight.value + boardPadding.value
    )}px`,
}));

const composerStyle = computed(() => {
    const rect = reservedRects.value[0];
    const reservedWidth = rect.w * cell.value + (rect.w - 1) * NOTE_GAP;

    return {
        left: `${boardPadding.value + rect.x * pitch.value}px`,
        top: `${gridTop.value + rect.y * pitch.value}px`,
        width: `${reservedWidth}px`,
        height: `${rect.h * cell.value + (rect.h - 1) * NOTE_GAP}px`,
    };
});

const measure = () => {
    const element = scroller.value;

    if (!element) {
        return;
    }

    viewportWidth.value = element.clientWidth;
    viewportHeight.value = element.clientHeight;

    if (chromeElement.value) {
        chromeHeight.value = chromeElement.value.offsetHeight;
    }
};

/**
 * A mouse wheel should move the board sideways without asking anyone to hold
 * Shift. Vertical scrolling is only kept for screens too short to show a row.
 */
const onWheel = (event) => {
    if (!isDesktop.value || event.ctrlKey || event.shiftKey) {
        return;
    }

    if (
        event.target instanceof Element &&
        event.target.closest('.bulletin-composer, textarea, input, select, .dropdown-content')
    ) {
        return;
    }

    const element = scroller.value;

    if (!element || Math.abs(event.deltaY) <= Math.abs(event.deltaX)) {
        return;
    }

    if (element.scrollWidth <= element.clientWidth) {
        return;
    }

    if (element.scrollHeight > element.clientHeight + 2) {
        return;
    }

    event.preventDefault();
    element.scrollLeft += event.deltaY;
};

const onBoardKeydown = (event) => {
    if (event.target !== event.currentTarget) {
        return;
    }

    const element = scroller.value;

    if (!element) {
        return;
    }

    const step = pitch.value;
    const behavior = prefersReducedMotion() ? 'auto' : 'smooth';

    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
        event.preventDefault();
        element.scrollBy({
            left: event.key === 'ArrowRight' ? step : -step,
            behavior,
        });

        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        if (element.scrollHeight <= element.clientHeight + 2) {
            return;
        }

        event.preventDefault();
        element.scrollBy({
            top: event.key === 'ArrowDown' ? step : -step,
            behavior,
        });
    }
};

const checkOverflow = () => {
    const element = scroller.value;

    if (!element) {
        return;
    }

    hasOverflow.value = element.scrollWidth > element.clientWidth + 4;
};

async function loadMore() {
    if (loading.value || !moreAvailable.value) {
        return false;
    }

    loading.value = true;
    loadError.value = '';

    try {
        const params = new URLSearchParams({ limit: '30' });

        if (cursor.value) {
            params.set('before', cursor.value);
        }

        const response = await fetch(`${props.endpoints.posts}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('bulletin listing failed');
        }

        const payload = await response.json();
        const known = new Set(notes.value.map((note) => note.id));
        const incoming = (payload.data ?? []).filter((note) => !known.has(note.id));

        notes.value = [...notes.value, ...incoming];
        cursor.value = payload.meta?.next_cursor ?? null;
        moreAvailable.value = Boolean(payload.meta?.has_more);

        return true;
    } catch {
        loadError.value = 'Older notes could not be loaded.';
        moreAvailable.value = true;

        return false;
    } finally {
        loading.value = false;
    }
}

let scrollFrame = 0;

const onScroll = () => {
    closeMenu();

    if (scrollFrame) {
        return;
    }

    scrollFrame = window.requestAnimationFrame(() => {
        scrollFrame = 0;

        const element = scroller.value;

        if (!element || loading.value || !moreAvailable.value || loadError.value) {
            return;
        }

        if (element.scrollLeft + element.clientWidth >= element.scrollWidth - 360) {
            loadMore();
        }
    });
};

async function ensureOverflow() {
    await nextTick();
    checkOverflow();

    let guard = 0;

    while (moreAvailable.value && !hasOverflow.value && guard < 3) {
        guard += 1;
        const loaded = await loadMore();

        if (!loaded) {
            break;
        }

        await nextTick();
        checkOverflow();
    }
}

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const applyOptimisticReaction = (note, type) => {
    const counts = { ...(note.reactions?.counts ?? {}) };
    let mine = note.reactions?.mine ?? null;

    if (mine === type) {
        counts[type] = Math.max(0, (counts[type] ?? 0) - 1);
        mine = null;
    } else {
        if (mine) {
            counts[mine] = Math.max(0, (counts[mine] ?? 0) - 1);
        }

        counts[type] = (counts[type] ?? 0) + 1;
        mine = type;
    }

    note.reactions = { counts, mine };
};

let toastMessageTimer = 0;

const showToast = (message) => {
    toastMessage.value = message;
    window.clearTimeout(toastMessageTimer);
    toastMessageTimer = window.setTimeout(() => {
        toastMessage.value = '';
    }, 5000);
};

async function onReact(note, type) {
    if (pendingReactions.value[note.id]) {
        return;
    }

    const previous = note.reactions;
    applyOptimisticReaction(note, type);
    pendingReactions.value = { ...pendingReactions.value, [note.id]: type };
    toastMessage.value = '';

    try {
        const response = await fetch(`${props.endpoints.posts}/${note.id}/reactions`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ reaction_type: type }),
        });

        if (response.status === 404) {
            notes.value = notes.value.filter((item) => item.id !== note.id);
            showToast('That note is no longer on the board.');

            return;
        }

        if (!response.ok) {
            throw new Error(
                response.status === 429
                    ? 'You are reacting too quickly. Give it a moment.'
                    : 'That reaction could not be saved. Please try again.'
            );
        }

        const payload = await response.json();
        const target = notes.value.find((item) => item.id === note.id);

        if (target && payload?.data?.reactions) {
            target.reactions = payload.data.reactions;
        }
    } catch (error) {
        note.reactions = previous;
        showToast(error?.message ?? 'That reaction could not be saved.');
    } finally {
        const pending = { ...pendingReactions.value };
        delete pending[note.id];
        pendingReactions.value = pending;
    }
}

const closeMenu = () => {
    openMenu.value = null;
};

const onMenuToggle = ({ noteId, menu, open }) => {
    if (!open) {
        if (openMenu.value?.noteId === noteId && openMenu.value?.menu === menu) {
            closeMenu();
        }

        return;
    }

    openMenu.value = { noteId, menu };
};

const openNote = (noteId) => {
    closeMenu();
    selectedNoteId.value = noteId;
};

/**
 * Only one note menu may be open at a time. Clicking anywhere outside a menu,
 * pressing Escape, or scrolling the board closes it.
 */
const onDocumentPointerDown = (event) => {
    if (!openMenu.value) {
        return;
    }

    if (event.target instanceof Element && event.target.closest('.bulletin-note__menu')) {
        return;
    }

    closeMenu();
};

const onDocumentKeydown = (event) => {
    if (event.key === 'Escape') {
        closeMenu();
    }
};

const onReported = (payload) => {
    reportingNoteId.value = null;

    showToast(
        payload?.already
            ? 'You already reported that note. Thanks again.'
            : 'Thanks — that note is now in the review queue.'
    );
};

const onUpdated = (note) => {
    notes.value = notes.value.map((item) => (item.id === note.id ? note : item));
    editingNoteId.value = null;

    showToast('Note updated.');
};

const onDeleted = (noteId) => {
    notes.value = notes.value.filter((item) => item.id !== noteId);
    deletingNoteId.value = null;

    if (selectedNoteId.value === noteId) {
        selectedNoteId.value = null;
    }

    if (reportingNoteId.value === noteId) {
        reportingNoteId.value = null;
    }

    showToast('Note deleted.');

    nextTick(checkOverflow);
};

async function shareBoard() {
    const url = new URL(window.location.href);
    url.search = '';
    url.hash = '';

    if (navigator.share) {
        try {
            await navigator.share({
                title: 'Bleep Bulletin',
                text: 'Anonymous notes on the Bleep bulletin board.',
                url: url.toString(),
            });

            return;
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }
        }
    }

    try {
        await navigator.clipboard.writeText(url.toString());
        showToast('Board link copied.');
    } catch {
        showToast('Copy the address bar to share this board.');
    }
}

const onCreated = (note) => {
    notes.value = [note, ...notes.value];
    newNoteId.value = note.id;
    showMobileComposer.value = false;

    window.setTimeout(() => {
        if (newNoteId.value === note.id) {
            newNoteId.value = null;
        }
    }, 4000);

    nextTick(() => {
        const element = scroller.value;
        const created = element?.querySelector(`[data-note-id="${note.id}"]`);

        if (element && created) {
            element.scrollTo({
                left: Math.max(0, created.offsetLeft - 40),
                behavior: prefersReducedMotion() ? 'auto' : 'smooth',
            });
        }

        checkOverflow();
    });
};

const onMediaChange = (event) => {
    isDesktop.value = event.matches;

    if (event.matches) {
        showMobileComposer.value = false;
    }

    nextTick(() => {
        measure();
        ensureOverflow();
    });
};

let resizeObserver = null;

onMounted(() => {
    measure();
    ensureOverflow();
    checkOverflow();

    resizeObserver = new ResizeObserver(() => {
        measure();
        checkOverflow();
    });

    if (scroller.value) {
        resizeObserver.observe(scroller.value);
    }

    if (chromeElement.value) {
        resizeObserver.observe(chromeElement.value);
    }

    desktopQuery.addEventListener('change', onMediaChange);
    scroller.value?.addEventListener('wheel', onWheel, { passive: false });
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    desktopQuery.removeEventListener('change', onMediaChange);
    scroller.value?.removeEventListener('wheel', onWheel);
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onDocumentKeydown);
    window.clearTimeout(toastMessageTimer);

    if (scrollFrame) {
        window.cancelAnimationFrame(scrollFrame);
    }
});

watch(layout, () => {
    nextTick(checkOverflow);
});
</script>

<template>
    <section class="flex h-full min-h-0 flex-1 flex-col p-1.5 md:p-2">
        <div class="bulletin-frame relative min-h-0 flex-1 overflow-hidden rounded-box">
            <div
                ref="scroller"
                class="bulletin-scroller absolute inset-0"
                tabindex="0"
                role="region"
                aria-label="Bulletin board of anonymous notes"
                aria-describedby="bulletin-board-help"
                @scroll.passive="onScroll"
                @keydown="onBoardKeydown"
            >
                <div class="bulletin-world" :style="worldStyle">
                    <StickyNote
                        v-for="(item, index) in layout.placed"
                        :key="item.note.id"
                        :note="item.note"
                        :placement="item"
                        :metrics="metrics"
                        :delay="Math.min(index, 9) * 22"
                        :is-new="item.note.id === newNoteId"
                        :pending-reaction="pendingReactions[item.note.id] ?? null"
                        :open-menu="openMenu"
                        @react="onReact(item.note, $event)"
                        @open="openNote(item.note.id)"
                        @report="reportingNoteId = item.note.id"
                        @edit="editingNoteId = item.note.id"
                        @delete="deletingNoteId = item.note.id"
                        @menu-toggle="onMenuToggle"
                    />

                    <div class="bulletin-composer-slot" :style="composerStyle">
                        <NoteComposer
                            v-if="isDesktop"
                            :identity="identity"
                            :store-url="endpoints.store"
                            @created="onCreated"
                        />

                        <button
                            v-else
                            type="button"
                            class="bulletin-pin-button bulletin-paper"
                            :style="{ '--paper': '#fef9c3', '--paper-ink': '#37341c' }"
                            @click="showMobileComposer = true"
                        >
                            <span class="bulletin-pin" aria-hidden="true"></span>
                            <span class="text-2xl leading-none" aria-hidden="true">+</span>
                            <span class="px-2 text-center text-sm font-bold leading-tight">
                                Pin a note
                            </span>
                        </button>
                    </div>
                </div>

                <p id="bulletin-board-help" class="sr-only">
                    The board scrolls sideways. Focus it and use the arrow keys to move through
                    the notes.
                </p>
            </div>

            <div
                ref="chromeElement"
                class="pointer-events-none absolute inset-x-0 top-0 px-2 pt-2 md:px-3 md:pt-3"
            >
                <div class="relative flex min-h-12 items-center justify-center md:min-h-14">
                    <div
                        class="bulletin-board-header bulletin-paper bulletin-paper--perforated max-w-[calc(100%-9.5rem)] px-4 py-1.5 text-center"
                    >
                        <span class="bulletin-pin" aria-hidden="true"></span>
                        <span class="bulletin-board-header__fold" aria-hidden="true"></span>
                        <h1 class="truncate text-base font-bold tracking-tight md:text-lg">
                            Bleep Bulletin
                        </h1>
                        <p class="truncate text-[11px] opacity-70 md:text-xs">
                            Anonymous notes. No sign-up.
                        </p>
                    </div>

                    <div class="pointer-events-auto absolute right-0 top-0 z-10 flex items-center gap-1 md:gap-2">
                        <button
                            type="button"
                            class="btn btn-ghost btn-xs md:btn-sm"
                            aria-label="Share this bulletin board"
                            @click="shareBoard"
                        >
                            <span class="hidden sm:inline">Share</span>
                            <span class="sm:hidden" aria-hidden="true">↗</span>
                        </button>

                        <a
                            :href="homeUrl"
                            class="btn btn-neutral btn-xs md:btn-sm"
                            aria-label="Back to Bleep"
                        >
                            ← <span class="hidden sm:inline">Back</span>
                        </a>
                    </div>
                </div>
            </div>

            <div
                v-if="notes.length === 0"
                class="pointer-events-none absolute left-1/2 top-1/2 z-10 w-[min(18rem,70%)] -translate-x-1/2 -translate-y-1/2 -rotate-1"
            >
                <div class="bulletin-paper bulletin-paper--plain p-5 text-center text-sm">
                    The board is empty. Pin the first note.
                </div>
            </div>

            <div class="pointer-events-none absolute bottom-3 right-3 z-30">
                <button
                    v-if="moreAvailable"
                    type="button"
                    class="btn btn-neutral btn-sm pointer-events-auto gap-2 shadow-lg"
                    :disabled="loading"
                    @click="loadMore"
                >
                    <span v-if="loading" class="loading loading-spinner loading-xs"></span>
                    {{ loading ? 'Loading…' : 'Load older notes' }}
                </button>
            </div>

            <div v-if="loadError" class="toast toast-end toast-bottom z-40">
                <div class="alert alert-error py-2 text-sm" role="alert">{{ loadError }}</div>
            </div>

            <div v-if="toastMessage" class="toast toast-end toast-bottom z-40">
                <div class="alert alert-neutral py-2 text-sm shadow-lg" role="status">
                    {{ toastMessage }}
                </div>
            </div>
        </div>

        <div
            v-if="!isDesktop && showMobileComposer"
            class="modal modal-open"
            role="dialog"
            aria-modal="true"
            aria-label="Pin a note"
        >
            <div
                class="modal-box w-[calc(100%-2rem)] max-w-md min-w-0 overflow-visible bg-transparent p-0 shadow-none"
            >
                <NoteComposer
                    variant="modal"
                    :identity="identity"
                    :store-url="endpoints.store"
                    @created="onCreated"
                    @cancel="showMobileComposer = false"
                />
            </div>

            <button
                type="button"
                class="modal-backdrop bg-black/50"
                aria-label="Close composer"
                @click="showMobileComposer = false"
            ></button>
        </div>

        <NoteDialog v-if="selectedNote" :note="selectedNote" @close="selectedNoteId = null" />

        <ReportDialog
            v-if="reportingNote"
            :note="reportingNote"
            :endpoint="`${endpoints.posts}/${reportingNote.id}/reports`"
            @close="reportingNoteId = null"
            @reported="onReported"
        />

        <EditNoteDialog
            v-if="editingNote"
            :note="editingNote"
            :endpoint="`${endpoints.posts}/${editingNote.id}`"
            @close="editingNoteId = null"
            @updated="onUpdated"
        />

        <DeleteNoteDialog
            v-if="deletingNote"
            :note="deletingNote"
            :endpoint="`${endpoints.posts}/${deletingNote.id}`"
            @close="deletingNoteId = null"
            @deleted="onDeleted"
        />
    </section>
</template>
