<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { PAPER_COLORS, PAPER_TEXTURES, inkForColor } from './boardLayout';

const props = defineProps({
    note: { type: Object, required: true },
    endpoint: { type: String, required: true },
});

const emit = defineEmits(['close', 'updated']);

const SIZE_VALUES = ['1x1', '1x2', '1x3', '1x4', '2x1', '2x2', '2x3', '2x4'];

const SIZES = SIZE_VALUES.map((value) => {
    const [width, height] = value.split('x').map(Number);

    return { value, width, height };
});

const MEDIA_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'image/avif',
    'video/mp4',
    'video/webm',
    'video/quicktime',
    'video/x-matroska',
    'video/ogg',
    'video/x-m4v',
    'audio/mpeg',
    'audio/mp3',
    'audio/wav',
    'audio/x-wav',
    'audio/flac',
    'audio/x-flac',
    'audio/aac',
    'audio/mp4',
    'audio/x-m4a',
    'audio/ogg',
    'audio/opus',
    'audio/aiff',
    'audio/x-ms-wma',
];

const MEDIA_ACCEPT = MEDIA_TYPES.join(',');
const MAX_MEDIA_BYTES = 50 * 1024 * 1024;

const dialog = ref(null);
const content = ref(props.note.content ?? '');
const linkUrl = ref(props.note.link_url ?? '');
const size = ref(
    SIZE_VALUES.includes(`${props.note.width}x${props.note.height}`)
        ? `${props.note.width}x${props.note.height}`
        : '1x1'
);
const paperColor = ref(props.note.color ?? '#fef08a');
const paperTexture = ref(props.note.texture ?? 'plain');
const errors = ref({});
const formError = ref('');
const mediaError = ref('');
const submitting = ref(false);
const contentInput = ref(null);
const mediaInput = ref(null);

const existingMediaUrl = ref(props.note.media_url ?? props.note.image_url ?? null);
const existingMediaKind = ref(
    props.note.media_kind ?? (props.note.image_url ? 'image' : null)
);
const mediaFile = ref(null);
const mediaPreview = ref('');
const mediaKind = ref(null);
const mediaFileName = ref('');
const removeMedia = ref(false);

let closed = false;
let ownedPreviewUrl = '';

const selectedSize = computed(
    () => SIZES.find((option) => option.value === size.value) ?? SIZES[0]
);

const paperClass = computed(() => `bulletin-paper--${paperTexture.value}`);

const paperStyle = computed(() => ({
    '--paper': paperColor.value,
    '--paper-ink': inkForColor(paperColor.value),
}));

const remaining = computed(() => 2000 - content.value.length);

const canSubmit = computed(
    () => content.value.trim().length > 0 && content.value.length <= 2000 && !submitting.value
);

const currentMediaKind = computed(() => mediaKind.value ?? existingMediaKind.value);
const currentMediaUrl = computed(() => mediaPreview.value || existingMediaUrl.value);
const currentMediaName = computed(() => mediaFileName.value || 'Current attachment');

const fieldError = (field) => errors.value[field]?.[0] ?? '';

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const releasePreview = () => {
    if (ownedPreviewUrl) {
        URL.revokeObjectURL(ownedPreviewUrl);
        ownedPreviewUrl = '';
    }

    mediaPreview.value = '';
    mediaKind.value = null;
    mediaFileName.value = '';
};

const clearNewMedia = () => {
    releasePreview();
    mediaFile.value = null;
    mediaError.value = '';

    if (mediaInput.value) {
        mediaInput.value.value = '';
    }
};

const onMediaChange = (event) => {
    const file = event.target.files?.[0] ?? null;

    mediaError.value = '';
    clearNewMedia();
    removeMedia.value = false;

    if (!file) {
        return;
    }

    if (!MEDIA_TYPES.includes(file.type)) {
        mediaError.value = 'Attach an image, GIF, video, or audio file.';
        event.target.value = '';

        return;
    }

    if (file.size > MAX_MEDIA_BYTES) {
        mediaError.value = 'Attachments must be 50MB or smaller.';
        event.target.value = '';

        return;
    }

    mediaFile.value = file;
    mediaFileName.value = file.name;
    ownedPreviewUrl = URL.createObjectURL(file);
    mediaPreview.value = ownedPreviewUrl;
    mediaKind.value = file.type.startsWith('image/')
        ? 'image'
        : file.type.startsWith('video/')
          ? 'video'
          : 'audio';
};

const removeCurrentMedia = () => {
    clearNewMedia();
    existingMediaUrl.value = null;
    existingMediaKind.value = null;
    removeMedia.value = true;
};

/**
 * Pause every player inside the dialog before it disappears. A playing video
 * can keep pointer capture and leave the board feeling unresponsive.
 */
const pauseMedia = () => {
    dialog.value?.querySelectorAll('video, audio').forEach((player) => {
        try {
            player.pause();
        } catch {
            /* no-op */
        }
    });
};

const cleanup = () => {
    pauseMedia();
    document.body.classList.remove('bulletin-dialog-open');
    releasePreview();
};

const finishClose = () => {
    if (closed) {
        return;
    }

    closed = true;
    cleanup();
    emit('close');
};

const requestClose = () => {
    if (closed) {
        return;
    }

    cleanup();

    if (dialog.value?.open) {
        dialog.value.close();
    }

    finishClose();
};

const onNativeClose = () => finishClose();

async function submit() {
    if (!canSubmit.value) {
        return;
    }

    submitting.value = true;
    errors.value = {};
    formError.value = '';

    try {
        const body = new FormData();
        body.append('_method', 'PATCH');
        body.append('content', content.value);
        body.append('width', String(selectedSize.value.width));
        body.append('height', String(selectedSize.value.height));
        body.append('paper_color', paperColor.value);
        body.append('paper_texture', paperTexture.value);

        if (linkUrl.value.trim()) {
            body.append('link_url', linkUrl.value.trim());
        }

        if (mediaFile.value) {
            body.append('media', mediaFile.value);
        } else if (removeMedia.value) {
            body.append('remove_media', '1');
        }

        const response = await fetch(props.endpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });

        if (response.status === 404) {
            formError.value = 'That note is no longer on the board.';

            return;
        }

        if (response.status === 403) {
            const payload = await response.json().catch(() => ({}));
            formError.value =
                payload.message ?? 'You can only edit a note shortly after pinning it.';

            return;
        }

        if (response.status === 422) {
            const payload = await response.json();
            errors.value = payload.errors ?? {};
            formError.value = payload.message ?? 'Please check the note and try again.';

            return;
        }

        if (response.status === 429) {
            const payload = await response.json().catch(() => ({}));
            formError.value =
                payload.message ?? 'You are making changes too quickly. Please try again.';

            return;
        }

        if (!response.ok) {
            formError.value = 'The note could not be updated. Please try again.';

            return;
        }

        const payload = await response.json();
        emit('updated', payload.data);
        requestClose();
    } catch {
        formError.value = 'The note could not be updated. Check your connection and try again.';
    } finally {
        submitting.value = false;
    }
}

onMounted(() => {
    try {
        dialog.value?.showModal();
    } catch {
        /* already open */
    }

    document.body.classList.add('bulletin-dialog-open');

    nextTick(() => contentInput.value?.focus());
});

onBeforeUnmount(() => {
    cleanup();
    document.body.classList.remove('bulletin-dialog-open');
});
</script>

<template>
    <dialog
        ref="dialog"
        class="modal"
        :aria-label="`Edit the note from ${note.anonymous_name}`"
        @close="onNativeClose"
        @cancel.prevent="requestClose"
        @click.self="requestClose"
    >
        <div
            class="modal-box bulletin-paper max-w-lg p-5 text-base"
            :class="paperClass"
            :style="paperStyle"
        >
            <button
                type="button"
                class="btn btn-ghost btn-sm btn-circle absolute right-2 top-2"
                aria-label="Close edit dialog"
                @click="requestClose"
            >
                ✕
            </button>

            <h2 class="pr-8 text-lg font-bold">Edit note</h2>
            <p class="mt-1 text-xs opacity-70">
                Notes can only be edited for a short time after they are pinned.
            </p>

            <form class="mt-3 flex flex-col gap-2" @submit.prevent="submit">
                <label for="bulletin-edit-content" class="sr-only">Note</label>
                <textarea
                    id="bulletin-edit-content"
                    ref="contentInput"
                    v-model="content"
                    class="textarea textarea-bordered w-full resize-none bg-base-100 text-base leading-snug"
                    rows="4"
                    maxlength="2000"
                    required
                    :aria-invalid="Boolean(fieldError('content'))"
                    @keydown.ctrl.enter.prevent="submit"
                    @keydown.meta.enter.prevent="submit"
                ></textarea>

                <div class="flex items-center justify-between gap-2 text-xs opacity-70">
                    <span>2,000 characters max</span>
                    <span
                        class="tabular-nums font-semibold"
                        :class="{ 'text-error': remaining < 100 }"
                    >
                        {{ content.length.toLocaleString() }} / 2,000
                    </span>
                </div>

                <p v-if="fieldError('content')" class="text-sm font-semibold text-error" role="alert">
                    {{ fieldError('content') }}
                </p>

                <fieldset class="min-w-0">
                    <legend
                        class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide opacity-70"
                    >
                        Note size
                    </legend>

                    <div
                        class="bulletin-strip flex w-full min-w-0 max-w-full gap-1.5 overflow-x-auto pb-1"
                        role="radiogroup"
                        aria-label="Note size"
                    >
                        <label
                            v-for="option in SIZES"
                            :key="option.value"
                            class="btn btn-sm shrink-0 font-mono focus-within:outline focus-within:outline-2 focus-within:outline-offset-1"
                            :class="
                                size === option.value
                                    ? 'btn-primary'
                                    : 'btn-ghost border border-base-content/25'
                            "
                            :title="`${option.width} cell${option.width > 1 ? 's' : ''} wide, ${option.height} tall`"
                        >
                            <input
                                v-model="size"
                                type="radio"
                                name="bulletin_edit_size"
                                :value="option.value"
                                class="sr-only"
                            >
                            {{ option.value }}
                        </label>
                    </div>
                </fieldset>

                <p
                    v-if="fieldError('width') || fieldError('height')"
                    class="text-sm font-semibold text-error"
                    role="alert"
                >
                    {{ fieldError('width') || fieldError('height') }}
                </p>

                <fieldset class="min-w-0">
                    <legend
                        class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide opacity-70"
                    >
                        Paper colour
                    </legend>

                    <div class="flex w-full min-w-0 max-w-full flex-wrap items-center gap-1">
                        <span class="bulletin-color-picker h-7 w-9 shrink-0" title="Custom colour">
                            <input
                                v-model="paperColor"
                                type="color"
                                class="bulletin-color-input"
                                :style="{ backgroundColor: paperColor }"
                                aria-label="Pick a custom paper colour"
                            >
                        </span>

                        <button
                            v-for="color in PAPER_COLORS"
                            :key="color.value"
                            type="button"
                            class="btn btn-sm btn-circle h-6 min-h-0 w-6 shrink-0 border border-base-content/25 p-0"
                            :class="{
                                'ring-2 ring-[var(--paper-ink)]': paperColor === color.value,
                            }"
                            :style="{ backgroundColor: color.value }"
                            :aria-label="`${color.label} paper`"
                            :aria-pressed="paperColor === color.value"
                            :title="color.label"
                            @click="paperColor = color.value"
                        ></button>
                    </div>
                </fieldset>

                <p
                    v-if="fieldError('paper_color')"
                    class="text-sm font-semibold text-error"
                    role="alert"
                >
                    {{ fieldError('paper_color') }}
                </p>

                <fieldset class="min-w-0">
                    <legend
                        class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide opacity-70"
                    >
                        Paper texture
                    </legend>

                    <div
                        class="bulletin-strip flex w-full min-w-0 max-w-full gap-1 overflow-x-auto pb-1"
                        role="radiogroup"
                        aria-label="Paper texture"
                    >
                        <label
                            v-for="texture in PAPER_TEXTURES"
                            :key="texture.value"
                            class="btn btn-sm h-8 min-h-0 shrink-0 justify-start gap-1.5 px-1.5 text-[11px] font-medium normal-case focus-within:outline focus-within:outline-2 focus-within:outline-offset-1"
                            :class="
                                paperTexture === texture.value
                                    ? 'btn-primary'
                                    : 'btn-ghost border border-base-content/25'
                            "
                            :title="`${texture.label} paper`"
                        >
                            <input
                                v-model="paperTexture"
                                type="radio"
                                name="bulletin_edit_texture"
                                :value="texture.value"
                                class="sr-only"
                            >
                            <span
                                class="bulletin-texture-swatch bulletin-paper shrink-0"
                                :class="`bulletin-paper--${texture.value}`"
                                aria-hidden="true"
                            ></span>
                            <span class="whitespace-nowrap">{{ texture.label }}</span>
                        </label>
                    </div>
                </fieldset>

                <p
                    v-if="fieldError('paper_texture')"
                    class="text-sm font-semibold text-error"
                    role="alert"
                >
                    {{ fieldError('paper_texture') }}
                </p>

                <div class="min-w-0 rounded-box border border-base-content/15 bg-black/5 px-3 py-2">
                    <p class="text-sm font-semibold opacity-90">
                        Attachment <span class="font-normal opacity-60">(optional)</span>
                    </p>

                    <div class="mt-2 flex min-w-0 flex-col gap-2">
                        <div
                            v-if="currentMediaUrl"
                            class="flex min-w-0 items-center gap-2 rounded-box border border-base-content/15 bg-base-100 p-2"
                        >
                            <img
                                v-if="currentMediaKind === 'image'"
                                :src="currentMediaUrl"
                                alt=""
                                class="h-16 w-16 shrink-0 rounded object-contain"
                            >
                            <video
                                v-else-if="currentMediaKind === 'video'"
                                :src="currentMediaUrl"
                                class="h-16 w-24 shrink-0 rounded object-contain"
                                muted
                                playsinline
                                preload="metadata"
                            ></video>
                            <audio
                                v-else
                                :src="currentMediaUrl"
                                class="min-w-0 w-full"
                                controls
                                preload="metadata"
                            ></audio>

                            <p class="min-w-0 flex-1 truncate text-xs font-semibold">
                                {{ currentMediaName }}
                            </p>

                            <button
                                type="button"
                                class="btn btn-ghost btn-xs shrink-0"
                                @click="removeCurrentMedia"
                            >
                                Remove
                            </button>
                        </div>

                        <input
                            id="bulletin-edit-media"
                            ref="mediaInput"
                            type="file"
                            class="file-input file-input-bordered w-full min-w-0 max-w-full bg-base-100"
                            name="media"
                            :accept="MEDIA_ACCEPT"
                            @change="onMediaChange"
                        >

                        <p class="text-xs opacity-60">
                            Photo, GIF, video, or audio up to 50MB.
                        </p>

                        <p v-if="mediaError" class="text-xs font-semibold text-error" role="alert">
                            {{ mediaError }}
                        </p>

                        <p
                            v-if="fieldError('media')"
                            class="text-xs font-semibold text-error"
                            role="alert"
                        >
                            {{ fieldError('media') }}
                        </p>
                    </div>
                </div>

                <div>
                    <label for="bulletin-edit-link" class="block text-xs font-semibold opacity-80">
                        Link (optional)
                    </label>
                    <input
                        id="bulletin-edit-link"
                        v-model="linkUrl"
                        class="input input-bordered w-full bg-base-100"
                        type="url"
                        maxlength="2048"
                        placeholder="https://example.com"
                        :aria-invalid="Boolean(fieldError('link_url'))"
                    >
                    <p
                        v-if="fieldError('link_url')"
                        class="mt-1 text-xs font-semibold text-error"
                        role="alert"
                    >
                        {{ fieldError('link_url') }}
                    </p>
                </div>

                <p v-if="formError" class="alert alert-error py-2 text-sm" role="alert">
                    {{ formError }}
                </p>

                <div class="mt-1 flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm" @click="requestClose">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm" :disabled="!canSubmit">
                        <span v-if="submitting" class="loading loading-spinner loading-xs"></span>
                        {{ submitting ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </dialog>
</template>
