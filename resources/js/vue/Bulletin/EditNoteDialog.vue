<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { noteVariant } from './boardLayout';

const props = defineProps({
    note: { type: Object, required: true },
    endpoint: { type: String, required: true },
});

const emit = defineEmits(['close', 'updated']);

const dialog = ref(null);
const content = ref(props.note.content ?? '');
const linkUrl = ref(props.note.link_url ?? '');
const errors = ref({});
const formError = ref('');
const submitting = ref(false);
const contentInput = ref(null);

const variant = noteVariant(props.note.id, props.note.color, props.note.texture);

const paperClass = variant.pattern
    ? `bulletin-paper--${variant.style}-${variant.pattern}`
    : `bulletin-paper--${variant.style}`;

const paperStyle = {
    '--paper': variant.color,
    '--paper-ink': variant.ink,
};

const remaining = computed(() => 2000 - content.value.length);

const canSubmit = computed(
    () => content.value.trim().length > 0 && content.value.length <= 2000 && !submitting.value
);

const fieldError = (field) => errors.value[field]?.[0] ?? '';

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const close = () => emit('close');

async function submit() {
    if (!canSubmit.value) {
        return;
    }

    submitting.value = true;
    errors.value = {};
    formError.value = '';

    try {
        const response = await fetch(props.endpoint, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                content: content.value,
                link_url: linkUrl.value.trim() || null,
            }),
        });

        if (response.status === 404) {
            formError.value = 'That note is no longer on the board.';

            return;
        }

        if (response.status === 403) {
            const payload = await response.json().catch(() => ({}));
            formError.value =
                payload.message ?? 'You can only edit a note within 24 hours of pinning it.';

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
    } catch {
        formError.value = 'The note could not be updated. Check your connection and try again.';
    } finally {
        submitting.value = false;
    }
}

onMounted(() => {
    dialog.value?.showModal();
    document.body.classList.add('bulletin-dialog-open');

    nextTick(() => contentInput.value?.focus());
});

onBeforeUnmount(() => {
    document.body.classList.remove('bulletin-dialog-open');
});
</script>

<template>
    <dialog
        ref="dialog"
        class="modal"
        :aria-label="`Edit the note from ${note.anonymous_name}`"
        @close="close"
        @click.self="close"
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
                @click="close"
            >
                ✕
            </button>

            <h2 class="pr-8 text-lg font-bold">Edit note</h2>
            <p class="mt-1 text-xs opacity-70">
                Notes can be edited for 24 hours after they are pinned.
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
                    <button type="button" class="btn btn-ghost btn-sm" @click="close">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" :disabled="!canSubmit">
                        <span v-if="submitting" class="loading loading-spinner loading-xs"></span>
                        {{ submitting ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </dialog>
</template>
