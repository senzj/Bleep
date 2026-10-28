<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { formatEditedTimestamp, formatNoteTimestamp, noteVariant } from './boardLayout';

const props = defineProps({
    note: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const dialog = ref(null);
const mediaFailed = ref(false);

const variant = noteVariant(props.note.id, props.note.color, props.note.texture);

const paperClass = variant.pattern
    ? `bulletin-paper--${variant.style}-${variant.pattern}`
    : `bulletin-paper--${variant.style}`;

const timestamp = formatNoteTimestamp(props.note.created_at);
const editedTimestamp = formatEditedTimestamp(props.note.created_at, props.note.edited_at);

const media = computed(() => {
    if (props.note.media_url) {
        return {
            url: props.note.media_url,
            kind: props.note.media_kind ?? 'image',
        };
    }

    if (props.note.image_url) {
        return { url: props.note.image_url, kind: 'image' };
    }

    return null;
});

const close = () => emit('close');

onMounted(() => {
    dialog.value?.showModal();
    document.body.classList.add('bulletin-dialog-open');
});

onBeforeUnmount(() => {
    document.body.classList.remove('bulletin-dialog-open');
});
</script>

<template>
    <dialog
        ref="dialog"
        class="modal"
        :aria-label="`Full note from ${note.anonymous_name}`"
        @close="close"
        @click.self="close"
    >
        <div
            class="modal-box bulletin-paper max-w-2xl p-5 text-base"
            :class="paperClass"
            :style="{ backgroundColor: variant.color, color: variant.ink }"
        >
            <button
                type="button"
                class="btn btn-ghost btn-sm btn-circle absolute right-2 top-2"
                aria-label="Close note"
                @click="close"
            >
                ✕
            </button>

            <p class="whitespace-pre-wrap break-words pr-8 text-base leading-relaxed">
                {{ note.content }}
            </p>

            <template v-if="media && !mediaFailed">
                <img
                    v-if="media.kind === 'image'"
                    :src="media.url"
                    :alt="`Image attached to ${note.anonymous_name}'s note`"
                    class="mt-3 max-h-[45dvh] w-full rounded object-contain"
                    loading="lazy"
                    decoding="async"
                    referrerpolicy="no-referrer"
                    @error="mediaFailed = true"
                >
                <video
                    v-else-if="media.kind === 'video'"
                    :src="media.url"
                    class="mt-3 max-h-[50dvh] w-full rounded bg-black"
                    controls
                    playsinline
                    preload="metadata"
                    @error="mediaFailed = true"
                ></video>
                <audio
                    v-else
                    :src="media.url"
                    class="mt-3 w-full"
                    controls
                    preload="metadata"
                    @error="mediaFailed = true"
                ></audio>
            </template>

            <a
                v-if="note.link_url"
                :href="note.link_url"
                class="btn btn-primary btn-sm mt-3 w-fit"
                target="_blank"
                rel="nofollow noopener noreferrer"
            >
                Open link ↗
            </a>

            <footer class="mt-4 flex flex-col gap-0.5 border-t border-base-content/15 pt-2 text-xs">
                <span class="font-bold">{{ note.anonymous_name }}</span>
                <time :datetime="note.created_at" class="opacity-70">
                    {{ timestamp }}<template v-if="editedTimestamp"> · {{ editedTimestamp }}</template>
                </time>
            </footer>
        </div>
    </dialog>
</template>
