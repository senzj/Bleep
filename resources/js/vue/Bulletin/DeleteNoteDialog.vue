<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    note: { type: Object, required: true },
    endpoint: { type: String, required: true },
});

const emit = defineEmits(['close', 'deleted']);

const dialog = ref(null);
const submitting = ref(false);
const error = ref('');

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const close = () => emit('close');

async function submit() {
    if (submitting.value) {
        return;
    }

    submitting.value = true;
    error.value = '';

    try {
        const response = await fetch(props.endpoint, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.status === 403) {
            error.value = 'Only the visitor who pinned this note can delete it.';

            return;
        }

        if (response.status === 404) {
            error.value = 'That note is no longer on the board.';

            return;
        }

        if (!response.ok) {
            error.value = 'The note could not be deleted. Please try again.';

            return;
        }

        emit('deleted', props.note.id);
    } catch {
        error.value = 'The note could not be deleted. Check your connection and try again.';
    } finally {
        submitting.value = false;
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
        :aria-label="`Delete the note from ${note.anonymous_name}`"
        @close="close"
        @click.self="close"
    >
        <div class="modal-box max-w-sm text-base">
            <button
                type="button"
                class="btn btn-ghost btn-sm btn-circle absolute right-2 top-2"
                aria-label="Close delete dialog"
                @click="close"
            >
                ✕
            </button>

            <h2 class="pr-8 text-lg font-bold">Delete this note?</h2>
            <p class="mt-1 text-sm opacity-70">
                The note and all of its reactions will be removed for everyone. This cannot be
                undone.
            </p>

            <p class="mt-3 line-clamp-3 rounded-box bg-base-200 p-2 text-sm opacity-80">
                {{ note.content }}
            </p>

            <p v-if="error" class="alert alert-error mt-3 py-2 text-sm" role="alert">{{ error }}</p>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="btn btn-ghost btn-sm" @click="close">Cancel</button>
                <button
                    type="button"
                    class="btn btn-error btn-sm"
                    :disabled="submitting"
                    @click="submit"
                >
                    <span v-if="submitting" class="loading loading-spinner loading-xs"></span>
                    {{ submitting ? 'Deleting…' : 'Delete note' }}
                </button>
            </div>
        </div>
    </dialog>
</template>
