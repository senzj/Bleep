<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { PAPER_COLORS, PAPER_TEXTURES, inkForColor } from './boardLayout';

const props = defineProps({
    identity: { type: String, default: '' },
    storeUrl: { type: String, required: true },
    variant: { type: String, default: 'board' },
});

const emit = defineEmits(['created', 'cancel']);

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
    'video/mp4',
    'video/webm',
    'audio/mpeg',
    'audio/wav',
    'audio/ogg',
];

const MEDIA_ACCEPT = MEDIA_TYPES.join(',');
const MAX_MEDIA_BYTES = 50 * 1024 * 1024;

const randomPaperColor = () => PAPER_COLORS[Math.floor(Math.random() * PAPER_COLORS.length)].value;
const randomPaperTexture = () =>
    PAPER_TEXTURES[Math.floor(Math.random() * PAPER_TEXTURES.length)].value;

/**
 * Quality-of-life helpers: shuffle the size/colour/texture without repeating the
 * value the visitor already has selected, so every click visibly changes the
 * note.
 */
const randomSize = () => {
    const choices = SIZE_VALUES.filter((value) => value !== size.value);
    size.value = choices[Math.floor(Math.random() * choices.length)];
};

const randomColor = () => {
    const choices = PAPER_COLORS.filter((color) => color.value !== paperColor.value);
    paperColor.value = choices[Math.floor(Math.random() * choices.length)].value;
};

const randomTexture = () => {
    const choices = PAPER_TEXTURES.filter((texture) => texture.value !== paperTexture.value);
    paperTexture.value = choices[Math.floor(Math.random() * choices.length)].value;
};

const formElement = ref(null);
const content = ref('');
const size = ref('1x1');
const paperColor = ref(randomPaperColor());
const paperTexture = ref(randomPaperTexture());
const linkUrl = ref('');
const mediaFile = ref(null);
const mediaPreview = ref('');
const mediaKind = ref(null);
const showExtras = ref(false);
const submitting = ref(false);
const errors = ref({});
const formError = ref('');
const mediaError = ref('');
const justPinned = ref(false);

const selectedSize = computed(
    () => SIZES.find((option) => option.value === size.value) ?? SIZES[0]
);

/**
 * Preview the chosen paper colour on the composer itself so the visitor can see
 * how their note will look before pinning it.
 */
const paperStyle = computed(() => ({
    '--paper': paperColor.value,
    '--paper-ink': inkForColor(paperColor.value),
}));

const remaining = computed(() => 2000 - content.value.length);

const canSubmit = computed(
    () => content.value.trim().length > 0 && content.value.length <= 2000 && !submitting.value
);

const mediaLabel = computed(() => {
    if (!mediaFile.value) {
        return '';
    }

    const bytes = mediaFile.value.size;
    const readable =
        bytes >= 1024 * 1024
            ? `${(bytes / (1024 * 1024)).toFixed(1)} MB`
            : `${Math.max(1, Math.round(bytes / 1024))} KB`;

    return `${mediaFile.value.name} · ${readable}`;
});

const fieldError = (field) => errors.value[field]?.[0] ?? '';

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const releasePreview = () => {
    if (mediaPreview.value) {
        URL.revokeObjectURL(mediaPreview.value);
    }

    mediaPreview.value = '';
    mediaKind.value = null;
};

const clearMedia = () => {
    releasePreview();
    mediaFile.value = null;
    mediaError.value = '';

    const input = document.getElementById('bulletin-media');

    if (input) {
        input.value = '';
    }
};

const onMediaChange = (event) => {
    const file = event.target.files?.[0] ?? null;

    mediaError.value = '';
    releasePreview();
    mediaFile.value = null;

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
    mediaPreview.value = URL.createObjectURL(file);
    mediaKind.value = file.type.startsWith('image/')
        ? 'image'
        : file.type.startsWith('video/')
          ? 'video'
          : 'audio';

    nextTick(() => {
        document
            .getElementById('bulletin-media-preview')
            ?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
};

const reset = () => {
    content.value = '';
    size.value = '1x1';
    paperColor.value = randomPaperColor();
    paperTexture.value = randomPaperTexture();
    linkUrl.value = '';
    clearMedia();
    showExtras.value = false;
};

async function submit() {
    if (!canSubmit.value) {
        return;
    }

    submitting.value = true;
    errors.value = {};
    formError.value = '';
    justPinned.value = false;

    try {
        const body = new FormData();
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
        }

        const response = await fetch(props.storeUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });

        if (response.status === 422) {
            const payload = await response.json();
            errors.value = payload.errors ?? {};
            formError.value = payload.message ?? 'Please check the note and try again.';

            return;
        }

        if (response.status === 429) {
            const payload = await response.json().catch(() => ({}));
            formError.value =
                payload.message ?? 'You are posting too quickly. Please try again later.';

            return;
        }

        if (!response.ok) {
            formError.value = 'The note could not be pinned. Please try again.';

            return;
        }

        const payload = await response.json();
        reset();
        formElement.value?.scrollTo({ top: 0 });
        justPinned.value = true;
        window.setTimeout(() => {
            justPinned.value = false;
        }, 4000);

        if (payload?.data) {
            emit('created', payload.data);
        }
    } catch {
        formError.value = 'The note could not be pinned. Check your connection and try again.';
    } finally {
        submitting.value = false;
    }
}

watch(formError, (message) => {
    if (!message) {
        return;
    }

    nextTick(() => {
        document
            .getElementById('bulletin-form-error')
            ?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
});

onBeforeUnmount(releasePreview);
</script>

<template>
    <div
        class="bulletin-composer-shell flex min-w-0 max-w-full"
        :class="variant === 'modal' ? 'w-full' : 'bulletin-composer h-full w-full'"
        :style="paperStyle"
    >
        <span class="bulletin-pin" aria-hidden="true"></span>

        <form
            ref="formElement"
                class="bulletin-paper bulletin-composer-form flex w-full min-w-0 max-w-full flex-col gap-2 overflow-x-hidden overflow-y-auto text-base"
                :class="[
                    `bulletin-paper--${paperTexture}`,
                    variant === 'modal'
                        ? 'max-h-[85dvh] rounded-box p-5 shadow-2xl'
                        : 'h-full p-3',
                ]"
                aria-label="Leave an anonymous note"
                novalidate
                @submit.prevent="submit"
            >

            <div class="flex min-w-0 items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-lg font-bold leading-tight">Pin a note</h2>
                    <p v-if="justPinned" class="text-xs font-semibold leading-snug text-success" role="status">
                        Pinned to the board.
                    </p>
                    <p v-else class="text-xs leading-snug opacity-70">
                        Be kind. It stays anonymous.
                    </p>
                </div>

                <div class="flex min-w-0 shrink items-start justify-end gap-2">
                    <div class="min-w-0 text-right">
                        <p class="text-xs leading-tight opacity-70">Pinning as</p>
                        <p
                            class="max-w-[10rem] truncate font-mono text-xs font-semibold leading-snug"
                            :title="identity"
                        >
                            {{ identity }}
                        </p>
                    </div>

                    <button
                        v-if="variant === 'modal'"
                        type="button"
                        class="btn btn-ghost btn-sm shrink-0"
                        @click="emit('cancel')"
                    >
                        Close
                    </button>
                </div>
            </div>

            <div class="min-w-0">
                <label for="bulletin-content" class="sr-only">Note</label>
                <textarea
                    id="bulletin-content"
                    v-model="content"
                    class="textarea textarea-bordered w-full min-w-0 resize-none bg-base-100 text-base leading-snug"
                    name="content"
                    rows="2"
                    maxlength="2000"
                    required
                    placeholder="What do you want to pin to the board?"
                    aria-describedby="bulletin-content-help"
                    :aria-invalid="Boolean(fieldError('content'))"
                    @keydown.ctrl.enter.prevent="submit"
                    @keydown.meta.enter.prevent="submit"
                ></textarea>

                <div
                    id="bulletin-content-help"
                    class="mt-1 flex items-center justify-between gap-2 text-xs opacity-70"
                >
                    <span>2,000 characters max</span>
                    <span class="tabular-nums font-semibold" :class="{ 'text-error': remaining < 100 }">
                        {{ content.length.toLocaleString() }} / 2,000
                    </span>
                </div>
            </div>

            <p v-if="fieldError('content')" class="text-sm font-semibold text-error" role="alert">
                {{ fieldError('content') }}
            </p>

            <fieldset class="min-w-0">
                <legend
                    class="mb-1.5 flex w-full items-center justify-between gap-2 text-[11px] font-semibold uppercase tracking-wide opacity-70"
                >
                    <span>Note size</span>
                    <button
                        type="button"
                        class="btn btn-ghost btn-xs h-5 min-h-0 gap-1 px-1.5 text-[10px] normal-case tracking-normal"
                        title="Pick a random note size"
                        @click="randomSize"
                    >
                        <span aria-hidden="true">🎲</span> Random
                    </button>
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
                            name="bulletin_size"
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
                    class="mb-1.5 flex w-full items-center justify-between gap-2 text-[11px] font-semibold uppercase tracking-wide opacity-70"
                >
                    <span>Paper colour</span>
                    <button
                        type="button"
                        class="btn btn-ghost btn-xs h-5 min-h-0 gap-1 px-1.5 text-[10px] normal-case tracking-normal"
                        title="Pick a random paper colour"
                        @click="randomColor"
                    >
                        <span aria-hidden="true">🎲</span> Random
                    </button>
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
                            'ring-2 ring-[var(--paper-ink)]':
                                paperColor === color.value,
                        }"
                        :style="{ backgroundColor: color.value }"
                        :aria-label="`${color.label} paper`"
                        :aria-pressed="paperColor === color.value"
                        :title="color.label"
                        @click="paperColor = color.value"
                    ></button>
                </div>
            </fieldset>

            <p v-if="fieldError('paper_color')" class="text-sm font-semibold text-error" role="alert">
                {{ fieldError('paper_color') }}
            </p>

            <fieldset class="min-w-0">
                <legend
                    class="mb-1.5 flex w-full items-center justify-between gap-2 text-[11px] font-semibold uppercase tracking-wide opacity-70"
                >
                    <span>Paper texture</span>
                    <button
                        type="button"
                        class="btn btn-ghost btn-xs h-5 min-h-0 gap-1 px-1.5 text-[10px] normal-case tracking-normal"
                        title="Pick a random paper texture"
                        @click="randomTexture"
                    >
                        <span aria-hidden="true">🎲</span> Random
                    </button>
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
                            name="paper_texture"
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

            <details
                class="min-w-0 rounded-box border border-base-content/15 bg-black/5 px-3 py-2"
                :open="showExtras || Boolean(mediaPreview)"
                @toggle="showExtras = $event.target.open"
            >
                <summary class="cursor-pointer text-sm font-semibold opacity-90">
                    Attach media <span class="font-normal opacity-60">(optional)</span>
                </summary>

                <div class="mt-2 flex min-w-0 flex-col gap-2">
                    <input
                        id="bulletin-media"
                        type="file"
                        class="file-input file-input-bordered w-full min-w-0 max-w-full bg-base-100"
                        name="media"
                        :accept="MEDIA_ACCEPT"
                        aria-describedby="bulletin-media-help"
                        @change="onMediaChange"
                    >

                    <p id="bulletin-media-help" class="text-xs opacity-60">
                        Photo, GIF, video, or audio up to 50MB.
                    </p>

                    <div
                        v-if="mediaPreview"
                        id="bulletin-media-preview"
                        class="flex min-w-0 scroll-mb-14 items-center gap-2 rounded-box border border-base-content/15 bg-base-100 p-2"
                    >
                        <img
                            v-if="mediaKind === 'image'"
                            :src="mediaPreview"
                            alt=""
                            class="h-16 w-16 shrink-0 rounded object-cover"
                        >
                        <video
                            v-else-if="mediaKind === 'video'"
                            :src="mediaPreview"
                            class="h-16 w-24 shrink-0 rounded object-cover"
                            muted
                            playsinline
                            preload="metadata"
                        ></video>
                        <audio
                            v-else
                            :src="mediaPreview"
                            class="min-w-0 w-full"
                            controls
                        ></audio>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold" :title="mediaLabel">
                                {{ mediaLabel }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="btn btn-ghost btn-xs shrink-0"
                            @click="clearMedia"
                        >
                            Remove
                        </button>
                    </div>

                    <p v-if="mediaError" class="text-xs font-semibold text-error" role="alert">
                        {{ mediaError }}
                    </p>

                    <p v-if="fieldError('media')" class="text-xs font-semibold text-error" role="alert">
                        {{ fieldError('media') }}
                    </p>

                    <div class="min-w-0">
                        <label for="bulletin-link" class="block text-xs font-semibold opacity-80">
                            Link (optional)
                        </label>
                        <input
                            id="bulletin-link"
                            v-model="linkUrl"
                            class="input input-bordered w-full min-w-0 bg-base-100"
                            type="url"
                            name="link_url"
                            maxlength="2048"
                            placeholder="https://example.com"
                            :aria-invalid="Boolean(fieldError('link_url'))"
                        >
                        <p
                            v-if="fieldError('link_url')"
                            class="text-xs font-semibold text-error"
                            role="alert"
                        >
                            {{ fieldError('link_url') }}
                        </p>
                    </div>
                </div>
            </details>

            <p
                v-if="formError"
                id="bulletin-form-error"
                class="scroll-mb-14 text-sm font-semibold text-error"
                role="alert"
            >
                {{ formError }}
            </p>

            <div class="sticky bottom-0 z-10 mt-auto bg-[var(--paper)] pt-2">
                <button
                    type="submit"
                    class="btn btn-primary w-full disabled:bg-base-300 disabled:text-base-content/60 disabled:opacity-100"
                    :disabled="!canSubmit"
                >
                    <span v-if="submitting" class="loading loading-spinner loading-sm"></span>
                    {{ submitting ? 'Pinning…' : 'Pin note' }}
                </button>
            </div>
        </form>
    </div>
</template>
