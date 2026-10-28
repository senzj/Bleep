<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { REPORT_REASONS } from './boardLayout';

const props = defineProps({
    note: { type: Object, required: true },
    endpoint: { type: String, required: true },
});

const emit = defineEmits(['close', 'reported']);

const dialog = ref(null);
const submitting = ref(null);
const error = ref('');

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const close = () => emit('close');

async function submit(reason) {
    if (submitting.value) {
        return;
    }

    submitting.value = reason;
    error.value = '';

    try {
        const response = await fetch(props.endpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ reason }),
        });

        if (response.status === 404) {
            error.value = 'That note is no longer on the board.';

            return;
        }

        if (response.status === 429) {
            error.value = 'You are reporting too quickly. Please try again later.';

            return;
        }

        if (!response.ok) {
            error.value = 'That report could not be saved. Please try again.';

            return;
        }

        const payload = await response.json();

        emit('reported', { already: Boolean(payload?.data?.already_reported) });
    } catch {
        error.value = 'That report could not be saved. Please try again.';
    } finally {
        submitting.value = null;
    }
}

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
        :aria-label="`Report the note from ${note.anonymous_name}`"
        @close="close"
        @click.self="close"
    >
        <div class="modal-box max-w-sm text-base">
            <button
                type="button"
                class="btn btn-ghost btn-sm btn-circle absolute right-2 top-2"
                aria-label="Close report dialog"
                @click="close"
            >
                ✕
            </button>

            <h2 class="pr-8 text-lg font-bold">Report this note</h2>
            <p class="mt-1 text-sm opacity-70">
                Reports are anonymous. Pick the closest reason; nobody is notified.
            </p>

            <div class="mt-4 flex flex-col gap-2">
                <button
                    v-for="reason in REPORT_REASONS"
                    :key="reason.key"
                    type="button"
                    class="btn btn-outline btn-sm w-full justify-start text-sm"
                    :disabled="Boolean(submitting)"
                    @click="submit(reason.key)"
                >
                    <span v-if="submitting === reason.key" class="loading loading-spinner loading-xs"></span>
                    {{ reason.label }}
                </button>
            </div>

            <p v-if="error" class="alert alert-error mt-3 py-2 text-sm" role="alert">{{ error }}</p>

            <button type="button" class="btn btn-ghost btn-sm mt-3" @click="close">Cancel</button>
        </div>
    </dialog>
</template>
